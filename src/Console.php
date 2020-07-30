<?php

declare(strict_types=1);

namespace atk4\ui;

/**
 * Console is a black square component resembling terminal window. It can be programmed
 * to run a job and output results to the user.
 */
class Console extends View implements \Psr\Log\LoggerInterface
{
    public $ui = 'inverted black segment';
    public $element = 'pre';

    /**
     * Specify which event will trigger this console. Set to 'false'
     * to disable automatic triggering if you need to trigger it
     * manually.
     *
     * @var bool
     */
    public $event = true;

    /**
     * Will be set to $true while executing callback. Some methods
     * will use this to automatically schedule their own callback
     * and allowing you a cleaner syntax, such as.
     *
     * $console->setModel($user, 'generateReport');
     *
     * @var bool
     */
    protected $sseInProgress = false;

    /**
     * Stores object JsSse which is used for communication.
     *
     * @var JsSse
     */
    public $sse;

    /**
     * Bypass is used internally to capture and wrap direct output, but prevent JsSse from
     * triggering output recursively.
     *
     * @var bool
     */
    public $_output_bypass = false;

    /**
     * Set a callback method which will be executed with the output sent back to the terminal.
     *
     * Argument passed to your callback will be $this Console. You may perform calls
     * to methods such as
     *
     *   $console->output()
     *   $console->outputHtml()
     *
     * If you are using setModel, and if your model implements \atk4\core\DebugTrait,
     * then you you will see debug information generated by $this->debug() or $this->log().
     *
     * This intercepts default application logging for the duration of the process.
     *
     * If you are using runCommand, then server command will be executed with it's output
     * (STDOUT and STDERR) redirected to the console.
     *
     * While inside a callback you may execute runCommand or setModel multiple times.
     *
     * @param \Closure    $callback callback which will be executed while displaying output inside console
     * @param bool|string $event    "true" would mean to execute on page load, string would indicate
     *                              js event. See first argument for View::js()
     *
     * @return $this
     */
    public function set($callback = null, $event = null)
    {
        if (!($callback instanceof \Closure)) {
            throw new Exception('Please specify the $callback argument');
        }

        if (isset($event)) {
            $this->event = $event;
        }

        if (!$this->sse) {
            $this->sse = JsSse::addTo($this);
        }

        $this->sse->set(function () use ($callback) {
            $this->sseInProgress = true;

            if (isset($this->app)) {
                $old_logger = $this->app->logger;
                $this->app->logger = $this;
            }

            ob_start(function ($content) {
                if ($this->_output_bypass) {
                    return $content;
                }

                $output = '';
                $this->sse->echoFunction = function ($str) use (&$output) {
                    $output .= $str;
                };
                $this->output($content);
                $this->sse->echoFunction = false;

                return $output;
            }, 1);

            try {
                $callback($this);
            } catch (\Throwable $e) {
                $this->output('');
                $this->outputHtml('<div class="ui segment" style="white-space: normal; font-family: Lato,\'Helvetica Neue\',Arial,Helvetica,sans-serif;">{0}</div>', [$this->app->renderExceptionHtml($e)]);
            }

            if (isset($this->app)) {
                $this->app->logger = $old_logger;
            }

            $this->sseInProgress = false;
        });

        if ($this->event) {
            $this->js($this->event, $this->jsExecute());
        }

        return $this;
    }

    /**
     * Return JavaScript expression to execute console.
     *
     * @return JsExpressionable
     */
    public function jsExecute()
    {
        return $this->sse;
    }

    /**
     * Output a single line to the console.
     *
     * @param string $message
     *
     * @return $this
     */
    public function output($message, array $context = [])
    {
        $this->outputHtml(htmlspecialchars($message), $context);

        return $this;
    }

    /**
     * Output un-escaped HTML line. Use this to send HTML.
     *
     * @todo Use $message as template and fill values from $context in there.
     *
     * @return $this
     */
    public function outputHtml(string $message, array $context = [])
    {
        $message = preg_replace_callback('/{([a-z0-9_-]+)}/i', function ($match) use ($context) {
            if (isset($context[$match[1]])) {
                return $context[$match[1]];
            }

            return '{' . $match[1] . '}'; // don't change the original message
        }, $message);

        $this->_output_bypass = true;
        $this->sse->send($this->js()->append($message . '<br/>'));
        $this->_output_bypass = false;

        return $this;
    }

    protected function renderView(): void
    {
        $this->addStyle('overflow-x', 'auto');

        parent::renderView();
    }

    /**
     * Executes a JavaScript action.
     *
     * @param JsExpressionable $js
     *
     * @return $this
     */
    public function send($js)
    {
        $this->_output_bypass = true;
        $this->sse->send($js);
        $this->_output_bypass = false;

        return $this;
    }

    public $last_exit_code;

    /**
     * Executes command passing along escaped arguments.
     *
     * Will also stream stdout / stderr as the comand executes.
     * once command terminates method will return the exit code.
     *
     * This method can be executed from inside callback or
     * without it.
     *
     * Example: runCommand('ping', ['-c', '5', '8.8.8.8']);
     *
     * All arguments are escaped.
     */
    public function exec($exec, $args = [])
    {
        if (!$this->sseInProgress) {
            $this->set(function () use ($exec, $args) {
                $a = $args ? (' with ' . count($args) . ' arguments') : '';
                $this->output('--[ Executing ' . $exec . $a . ' ]--------------');

                $this->exec($exec, $args);

                $this->output('--[ Exit code: ' . $this->last_exit_code . ' ]------------');
            });

            return;
        }

        [$proc, $pipes] = $this->execRaw($exec, $args);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        // $pipes contain streams that are still open and not EOF
        while ($pipes) {
            $read = $pipes;
            $j1 = $j2 = null;
            if (stream_select($read, $j1, $j2, 2) === false) {
                throw new Exception('stream_select() returned false.');
            }

            $stat = proc_get_status($proc);
            if (!$stat['running']) {
                proc_close($proc);

                break;
            }

            foreach ($read as $f) {
                $data = rtrim((string) fgets($f));
                if ($data === '') {
                    continue;
                }

                if ($f === $pipes[2]) {
                    // STDERR
                    $this->warning($data);
                } else {
                    // STDOUT
                    $this->output($data);
                }
            }
        }

        $this->last_exit_code = $stat['exitcode'];

        return $this->last_exit_code ? false : $this;
    }

    protected function execRaw($exec, $args = [])
    {
        // Escape arguments
        foreach ($args as $key => $val) {
            if (!is_scalar($val)) {
                throw (new Exception('Arguments must be scalar'))
                    ->addMoreInfo('arg', $val);
            }
            $args[$key] = escapeshellarg($val);
        }

        $exec = escapeshellcmd($exec);
        $spec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']]; // we want stdout and stderr
        $pipes = null;
        $proc = proc_open($exec . ' ' . implode(' ', $args), $spec, $pipes);
        if (!is_resource($proc)) {
            throw (new Exception('Command failed to execute'))
                ->addMoreInfo('exec', $exec)
                ->addMoreInfo('args', $args);
        }

        return [$proc, $pipes];
    }

    /**
     * This method is obsolete. Use Console::runMethod() instead.
     */
    public function setModel(\atk4\data\Model $model, $method = null, $args = [])
    {
        $this->runMethod($model, $method, $args);

        return $model;
    }

    /**
     * Execute method of a certain object. If object uses atk4/core/DebugTrait,
     * then debugging will also be used.
     *
     * During the invocation, Console will substitute $app->logger with itself,
     * capturing all debug/info/log messages generated by your code and displaying
     * it inside console.
     *
     * // Runs $user_model->generateReport('pdf')
     * Console::addTo($app)->runMethod($user_model, 'generateReports', ['pdf']);
     *
     * // Runs PainFactory::lastStaticMethod()
     * Console::addTo($app)->runMethod('PainFactory', 'lastStaticMethod');
     *
     * To produce output:
     *  - use $this->debug() or $this->info() (see documentation on DebugTrait)
     *
     * NOTE: debug() method will only output if you set debug=true. That is done
     * for the $user_model automatically, but for any nested objects you would have
     * to pass on the property.
     *
     * @param object|string $object
     *
     * @return $this
     */
    public function runMethod($object, string $method, array $args = [])
    {
        if (!$this->sseInProgress) {
            $this->set(function () use ($object, $method, $args) {
                $this->runMethod($object, $method, $args);
            });

            return $this;
        }

        if (is_object($object)) {
            // temporarily override app logging
            if (isset($object->app)) {
                $loggerBak = $object->app->logger;
                $object->app->logger = $this;
            }
            if (isset($object->_debugTrait)) {
                $debugBak = $object->debug;
                $object->debug = true;
            }

            $this->output('--[ Executing ' . get_class($object) . '->' . $method . ' ]--------------');

            try {
                $result = call_user_func_array([$object, $method], $args);
            } finally {
                if (isset($object->app)) {
                    $object->app->logger = $loggerBak;
                }
                if (isset($object->_debugTrait)) {
                    $object->debug = $debugBak;
                }
            }
        } elseif (is_string($object)) {
            $static = $object . '::' . $method;
            $this->output('--[ Executing ' . $static . ' ]--------------');
            $result = call_user_func_array($object . '::' . $method, $args);
        } else {
            throw (new Exception('Incorrect value for an object'))
                ->addMoreInfo('object', $object);
        }
        $this->output('--[ Result: ' . json_encode($result) . ' ]------------');

        return $this;
    }

    // Methods below implements \Psr\Log\LoggerInterface

    /**
     * System is unusable.
     *
     * @param string $message
     */
    public function emergency($message, array $context = [])
    {
        $this->outputHtml('<font color="pink">' . htmlspecialchars($message) . '</font>', $context);
    }

    /**
     * Action must be taken immediately.
     *
     * @param string $message
     */
    public function alert($message, array $context = [])
    {
        $this->outputHtml('<font color="pink">' . htmlspecialchars($message) . '</font>', $context);
    }

    /**
     * Critical conditions.
     *
     * @param string $message
     */
    public function critical($message, array $context = [])
    {
        $this->outputHtml('<font color="pink">' . htmlspecialchars($message) . '</font>', $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     */
    public function error($message, array $context = [])
    {
        $this->outputHtml('<font color="pink">' . htmlspecialchars($message) . '</font>', $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * @param string $message
     */
    public function warning($message, array $context = [])
    {
        $this->outputHtml('<font color="pink">' . htmlspecialchars($message) . '</font>', $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     */
    public function notice($message, array $context = [])
    {
        $this->outputHtml('<font color="yellow">' . htmlspecialchars($message) . '</font>', $context);
    }

    /**
     * Interesting events.
     *
     * @param string $message
     */
    public function info($message, array $context = [])
    {
        $this->outputHtml('<font color="gray">' . htmlspecialchars($message) . '</font>', $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     */
    public function debug($message, array $context = [])
    {
        $this->outputHtml('<font color="cyan">' . htmlspecialchars($message) . '</font>', $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     */
    public function log($level, $message, array $context = [])
    {
        $this->{$level}($message, $context);
    }
}
