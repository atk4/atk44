<?php

namespace atk4\ui\ActionExecutor;

use atk4\core\HookTrait;
use atk4\ui\Button;
use atk4\ui\Exception;
use atk4\ui\jsExpressionable;
use atk4\ui\jsToast;

class Basic extends \atk4\ui\View implements Interface_
{
    use HookTrait;

    /**
     * @var \atk4\data\UserAction\Generic
     */
    public $action = null;

    /**
     * @var bool Display header or not.
     */
    public $hasHeader = true;

    /**
     * @var null Header description.
     */
    public $description = null;

    /**
     * @var string Display message when action is disabled.
     */
    public $disableMsg = 'Action is disabled and cannot be executed';

    /**
     * @var Button | array  Button that trigger the action. Either as an array seed or object
     */
    public $executorButton = [Button::class, 'Confirm', 'primary'];

    /**
     * @var array
     */
    protected $arguments = [];

    /**
     * @var string Display message when missing arguments.
     */
    public $missingArgsMsg = 'Insufficient arguments';

    /**
     * @var array list of validated arguments
     */
    protected $validArguments = [];

    /**
     * @var jsExpressionable array|callable jsExpression to return if action was successful, e.g "new jsToast('Thank you')"
     */
    protected $jsSuccess = null;

    /**
     * Associate executor with action.
     *
     * @param \atk4\data\UserAction\Generic $action
     */
    public function setAction(\atk4\data\UserAction\Generic $action): void
    {
        $this->action = $action;
    }

    /**
     * Provide values for named arguments.
     *
     * @param array $arguments
     */
    public function setArguments(array $arguments)
    {
        // TODO: implement mechanism for validating arguments based on definition

        $this->arguments = array_merge($this->arguments, $arguments);
    }

    public function recursiveRender()
    {
        if (!$this->action) {
            throw new Exception(['Action is not set. Use setAction()']);
        }

        // check action can be called
        if ($this->action->enabled) {
            $this->initPreview();
        } else {
            $this->add(['Message', 'type'=>'error', $this->disableMsg]);

            return;
        }

        parent::recursiveRender(); // TODO: Change the autogenerated stub
    }

    /**
     * Check if all argument values have been provided.
     *
     * @throws Exception
     *
     * @return true
     */
    public function hasAllArguments()
    {
        foreach ($this->action->args as $key => $val) {
            if (!isset($this->arguments[$key])) {
                throw new Exception(['Argument is not provided', 'argument'=>$key]);
            }
        }

        return true;
    }

    protected function initPreview()
    {
        // lets make sure that all arguments are supplied
        if (!$this->hasAllArguments()) {
            $this->add(['Message', 'type'=>'error', $this->missingArgsMsg]);

            return;
        }

        $this->addHeader();

        $this->add($this->executorButton)->on('click', function () {
            return $this->jsExecute();
        });
    }

    /**
     * Will call $action->execute() with the correct arguments.
     *
     * @throws \atk4\core\Exception
     *
     * @return mixed
     */
    public function jsExecute()
    {
        $args = [];

        foreach ($this->action->args as $key => $val) {
            $args[] = $this->arguments[$key];
        }

        $return = $this->action->execute(...$args);

        $success = is_callable($this->jsSuccess) ? call_user_func_array($this->jsSuccess, [$this, $this->action->owner]) : $this->jsSuccess;

        return ($this->hook('afterExecute', [$return]) ?: $success) ?: new jsToast('Success' . (is_string($return) ? (': ' . $return) : ''));
    }

    /**
     * Will add header if set.
     *
     * @throws \atk4\core\Exception
     */
    public function addHeader()
    {
        if ($this->hasHeader) {
            $this->add(['Header', $this->action->caption, 'subHeader'=>$this->description ?: $this->action->getDescription()]);
        }
    }
}
