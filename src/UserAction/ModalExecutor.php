<?php

declare(strict_types=1);

namespace Atk4\Ui\UserAction;

use Atk4\Core\Factory;
use Atk4\Core\HookTrait;
use Atk4\Data\Model;
use Atk4\Data\ValidationException;
use Atk4\Ui\Button;
use Atk4\Ui\Exception;
use Atk4\Ui\Form;
use Atk4\Ui\JsExpressionable;
use Atk4\Ui\JsFunction;
use Atk4\Ui\JsToast;
use Atk4\Ui\Message;
use Atk4\Ui\Modal;
use Atk4\Ui\View;

/**
 * Modal executor for action.
 * These are special modal that will divide a model action into steps
 * and run each step accordingly via a loader setup in modal view.
 * The step orders are Argument, Field and Preview, prior to execute the model action.
 *
 * It will first determine the number of step necessary to run the model
 * action. When a step is running through the view loader, data collect for each step
 * are store in browser session storage via javascript. Thus, each request to execute loader,
 * include step data within the request.
 *
 * ModalExecutor modal view may be generated via callbacks.
 * These modal are added to app->html view if not already added
 * and the api service take care of generating them when output
 * in json via callback. It is important that these ModalExecutor modals
 * stay within the page html content for loader to run each steps properly.
 */
class ModalExecutor extends Modal implements JsExecutorInterface
{
    use HookTrait;

    /** @const string */
    public const HOOK_STEP = self::class . '@onStep';

    /**
     * @var JsExpressionable array|\Closure JsExpression to return if action was successful, e.g "new JsToast('Thank you')"
     */
    public $jsSuccess;

    /**
     * @var array will collect action data while doing action step
     */
    private $actionData = [];
    protected $actionInitialized = false;

    /**
     * The action to execute.
     *
     * @var Model\UserAction
     */
    public $action;

    /**
     * The action steps.
     *
     * @var string[]
     */
    private $steps;
    private $step;

    /**
     * The action step button.
     *
     * @var Button
     */
    private $prevStepBtn;
    private $nextStepBtn;
    private $execActionBtn;
    private $btns;

    /**
     * A form for action argument and fields user entry.
     *
     * @var string
     */
    public $form = [Form::class];

    /**
     * @var string can be "console", "text", or "html"
     */
    public $previewType = 'html';

    /**
     * View seed for displaying title for each step.
     *
     * @var array
     */
    public $stepTitle = ['args' => null, 'fields' => null, 'preview' => null];

    /**
     * The Loader that will execute all action step.
     *
     * @var \Atk4\Ui\Loader
     */
    public $loader;
    public $loaderUi = 'ui basic segment';
    public $loaderShim = [];

    protected function init(): void
    {
        parent::init();
        $this->observeChanges();
    }

    public function getAction(): Model\UserAction
    {
        return $this->action;
    }

    /**
     * Make sure modal id is unique.
     * Since User action can be added via callbacks, we need
     * to make sure that view id is properly set for loader and button
     * js action to run properly.
     */
    public function afterActionInit(Model\UserAction $action)
    {
        $getTableName = function ($arr) {
            foreach ($arr as $k => $v) {
                return is_numeric($k) ? $v : $k;
            }
        };

        $table_name = is_array($action->getModel()->table) ? $getTableName($action->getModel()->table) : $action->getModel()->table;

        $this->id = preg_replace('~[^A-Za-z0-9\-]~', '_', mb_strtolower($this->name . '_' . $table_name . '_' . $action->short_name));
        $this->name = $this->id;

        // Add buttons to modal for next and previous.
        $this->btns = (new View())->addStyle(['min-height' => '24px']);
        $this->prevStepBtn = Button::addTo($this->btns, ['Prev'])->addStyle(['float' => 'left !important']);
        $this->nextStepBtn = Button::addTo($this->btns, ['Next', 'blue']);
        $this->addButtonAction($this->btns);

        $this->loader = \Atk4\Ui\Loader::addTo($this, ['ui' => $this->loaderUi, 'shim' => $this->loaderShim]);
        $this->loader->loadEvent = false;
        $this->loader->addClass('atk-hide-loading-content');
        $this->actionData = $this->loader->jsGetStoreData()['session'];
    }

    /**
     * Will associate executor with the action.
     *
     * @return ModalExecutor
     */
    public function setAction(Model\UserAction $action): View
    {
        $this->action = $action;
        $this->afterActionInit($action);

        // get necessary step need prior to execute action.
        if ($this->steps = $this->getSteps($action)) {
            $this->title = $this->title ?? $action->getDescription();

            $this->btns->add($this->execActionBtn = $this->getExecutorFactory()->createTrigger($action, $this->getExecutorFactory()::MODAL_BUTTON));

            // get current step.
            $this->step = $this->stickyGet('step') ?? $this->steps[0];
            // set initial button state
            $this->jsSetBtnState($this, $this->step);
        }

        $this->actionInitialized = true;

        return $this;
    }

    /**
     * Perform model action by stepping through args - fields - preview.
     */
    public function executeModelAction()
    {
        $id = $this->stickyGet($this->name);
        if ($id && $this->action->appliesTo === Model\UserAction::APPLIES_TO_SINGLE_RECORD) {
            $this->action->setEntity($this->action->getModel()->tryLoad($id));
        }

        if ($this->action->fields === true) {
            $this->action->fields = array_keys($this->action->getModel()->getFields('editable'));
        }

        $this->loader->set(function ($modal) {
            $this->jsSetBtnState($modal, $this->step);

            try {
                switch ($this->step) {
                    case 'args':
                        $this->doArgs($modal);

                        break;
                    case 'fields':
                        $this->doFields($modal);

                        break;
                    case 'preview':
                        $this->doPreview($modal);

                        break;
                    case 'final':
                        $this->doFinal($modal);

                        break;
                }
            } catch (\Exception $e) {
                $this->_handleException($e, $modal, $this->step);
            }
        });
    }

    /**
     * Assign a View that will fire action execution.
     * If action require steps, it will automatically initialize
     * proper step to execute first.
     *
     * @return View
     */
    public function assignTrigger(View $view, array $urlArgs = [], string $when = 'click', $selector = null)
    {
        if (!$this->actionInitialized) {
            throw new Exception('Action must be set prior to assign trigger.');
        }

        if ($this->steps) {
            // use modal for stepping action.
            $urlArgs['step'] = $this->step;
            if ($this->action->enabled) {
                if ($selector) {
                    $view->on($when, $selector, [$this->show(), $this->loader->jsLoad($urlArgs, ['method' => 'post'])]);
                } else {
                    $view->on($when, [$this->show(), $this->loader->jsLoad($urlArgs, ['method' => 'post'])]);
                }
            } else {
                $view->addClass('disabled');
            }
        }

        return $this;
    }

    /**
     * Generate js for triggering action.
     *
     * @return array
     */
    public function jsExecute(array $urlArgs = [])
    {
        if (!$this->actionInitialized) {
            throw new Exception('Action must be set prior to assign trigger.');
        }

        $urlArgs['step'] = $this->step;

        return [$this->show(), $this->loader->jsLoad($urlArgs, ['method' => 'post'])];
    }

    /**
     * Do action args step.
     *
     * Will ask user to fill in arguments.
     */
    protected function doArgs(View $modal)
    {
        $this->_addStepTitle($modal, $this->step);

        $form = $this->addFormTo($modal);
        foreach ($this->action->args as $key => $val) {
            if (is_numeric($key)) {
                throw (new Exception('Action arguments must be named'))
                    ->addMoreInfo('args', $this->action->args);
            }

            if ($val instanceof Model) {
                $val = ['model' => $val];
            }

            if (isset($val['model'])) {
                $val['model'] = Factory::factory($val['model']);
                $form->addControl($key, [Form\Control\Lookup::class])->setModel($val['model']);
            } else {
                $form->addControl($key, null, $val);
            }
        }

        // set args value if available.
        $this->setFormField($form, $this->getActionData('args'), $this->step);

        // setup exec, next and prev button handler for this step.
        $this->jsSetSubmitBtn($modal, $form, $this->step);
        $this->jsSetPrevHandler($modal, $this->step);

        $form->onSubmit(function (Form $form) {
            // collect arguments.
            $this->actionData['args'] = $form->model->get();

            return $this->jsStepSubmit($this->step);
        });
    }

    /**
     * Do action Fields step.
     */
    protected function doFields(View $modal)
    {
        $this->_addStepTitle($modal, $this->step);
        $form = $this->addFormTo($modal);

        $form->setModel($this->action->getEntity(), $this->action->fields);
        // set Fields value if set from another step.
        $this->setFormField($form, $this->getActionData('fields'), $this->step);

        // setup exec, next and prev button handler for this step.
        $this->jsSetSubmitBtn($modal, $form, $this->step);
        $this->jsSetPrevHandler($modal, $this->step);

        if (!$form->hookHasCallbacks(Form::HOOK_SUBMIT)) {
            $form->onSubmit(function (Form $form) {
                // collect fields.
                $form_fields = $form->model->get();
                foreach ($this->action->fields as $field) {
                    $this->actionData['fields'][$field] = $form_fields[$field];
                }

                return $this->jsStepSubmit($this->step);
            });
        }
    }

    /**
     * Do action preview step.
     */
    protected function doPreview(View $modal)
    {
        $this->_addStepTitle($modal, $this->step);

        if ($fields = $this->getActionData('fields')) {
            $this->action->getEntity()->setMulti($fields);
        }

        if ($prev = $this->getPreviousStep($this->step)) {
            $chain = $this->loader->jsLoad([
                'step' => $prev,
                $this->name => $this->action->getEntity()->getId(),
            ], ['method' => 'post'], $this->loader->name);

            $modal->js(true, $this->prevStepBtn->js()->on('click', new JsFunction([$chain])));
        }

        // setup executor button to perform action.
        $modal->js(
            true,
            $this->execActionBtn->js()->on(
                'click',
                new JsFunction(
                    [
                        $this->loader->jsLoad(
                            [
                                'step' => 'final',
                                $this->name => $this->action->getEntity()->getId(),
                            ],
                            ['method' => 'post'],
                            $this->loader->name
                        ),
                    ]
                )
            )
        );

        $text = $this->getActionPreview();

        switch ($this->previewType) {
            case 'console':
                $preview = View::addTo($modal, ['ui' => 'inverted black segment', 'element' => 'pre']);
                $preview->set($text);

                break;
            case 'text':
                $preview = View::addTo($modal, ['ui' => 'basic segment']);
                $preview->set($text);

                break;
            case 'html':
                $preview = View::addTo($modal, ['ui' => 'basic segment']);
                $preview->template->dangerouslySetHtml('Content', $text);

                break;
        }
    }

    /**
     * Execute action when all step are completed.
     */
    protected function doFinal(View $modal)
    {
        foreach ($this->getActionData('fields') as $field => $value) {
            $this->action->getEntity()->set($field, $value);
        }

        $return = $this->action->execute(...$this->_getActionArgs($this->getActionData('args')));

        $this->_jsSequencer($modal, $this->jsGetExecute($return, $this->action->getEntity()->getId()));
    }

    protected function getActionData(string $step): array
    {
        return $this->actionData[$step] ?? [];
    }

    protected function getStep(): string
    {
        return $this->step;
    }

    /**
     * Return proper js statement need after action execution.
     *
     * @return array
     */
    protected function jsGetExecute($obj, $id)
    {
        $success = $this->jsSuccess instanceof \Closure
            ? ($this->jsSuccess)($this, $this->action->getModel(), $id, $obj)
            : $this->jsSuccess;

        return [
            $this->hide(),
            $this->hook(BasicExecutor::HOOK_AFTER_EXECUTE, [$obj, $id]) ?:
            $success ?: new JsToast('Success' . (is_string($obj) ? (': ' . $obj) : '')),
            $this->loader->jsClearStoreData(true),
        ];
    }

    /**
     * Get how many steps is required for this action.
     *
     * @param Model\UserAction $action the Model action
     */
    protected function getSteps(Model\UserAction $action): ?array
    {
        $steps = null;
        if ($action->args) {
            $steps[] = 'args';
        }
        if ($action->fields) {
            $steps[] = 'fields';
        }
        if ($action->preview) {
            $steps[] = 'preview';
        }

        return $steps;
    }

    /**
     * Get next step after $step.
     */
    protected function getNextStep(string $step): ?string
    {
        $next = null;
        if (!$this->isLastStep($step)) {
            foreach ($this->steps as $k => $s) {
                if ($step === $s) {
                    $next = $this->steps[$k + 1];

                    break;
                }
            }
        }

        return $next;
    }

    /**
     * Get previous step before $step.
     */
    protected function getPreviousStep(string $step): ?string
    {
        $prev = null;

        if (!$this->isFirstStep($step)) {
            foreach ($this->steps as $k => $s) {
                if ($s === $step) {
                    $prev = $this->steps[$k - 1];

                    break;
                }
            }
        }

        return $prev;
    }

    /**
     * Check if $step is last one.
     */
    protected function isLastStep(string $step): bool
    {
        $isLast = false;
        $step_count = count($this->steps);
        foreach ($this->steps as $k => $s) {
            if ($s === $step) {
                $isLast = $k === $step_count - 1;

                break;
            }
        }

        return $isLast;
    }

    /**
     * Check if step is first one.
     */
    protected function isFirstStep(string $step): bool
    {
        return $step === $this->steps[0];
    }

    /**
     * Will add field into form based on $fields array.
     */
    protected function setFormField(Form $form, array $fields, string $step): Form
    {
        foreach ($fields as $k => $val) {
            $form->getControl($k)->set($val);
        }
        $this->hook(self::HOOK_STEP, [$step, $form]);

        return $form;
    }

    /**
     * Get proper js after submitting a form in step.
     *
     * @return array
     */
    protected function jsStepSubmit(string $step)
    {
        try {
            if ($this->isLastStep($step)) {
                // collect argument and execute action.
                $return = $this->action->execute(...$this->_getActionArgs($this->getActionData('args')));
                $js = $this->jsGetExecute($return, $this->action->getEntity()->getId());
            } else {
                // store data and setup reload.
                $js = [
                    $this->loader->jsAddStoreData($this->actionData, true),
                    $this->loader->jsLoad([
                        'step' => $this->getNextStep($step),
                        $this->name => $this->action->getEntity()->getId(),
                    ], ['method' => 'post'], $this->loader->name),
                ];
            }

            return $js;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $msg = new Message('Error executing ' . $this->action->caption, 'red');
            $msg->invokeInit();
            $msg->text->content = $this->getApp()->renderExceptionHtml($e);

            return $msg;
        }
    }

    /**
     * Generate js for setting Buttons state based on current step.
     */
    protected function jsSetBtnState(View $view, string $step)
    {
        if (count($this->steps) === 1) {
            $view->js(true, $this->prevStepBtn->js()->hide());
            $view->js(true, $this->nextStepBtn->js()->hide());
        } else {
            $view->js(true, $this->jsSetPrevState($step));
            $view->js(true, $this->jsSetNextState($step));
            $view->js(true, $this->jsSetExecState($step));
        }

        // reset button handler.
        $view->js(true, $this->execActionBtn->js(true)->off());
        $view->js(true, $this->nextStepBtn->js(true)->off());
        $view->js(true, $this->prevStepBtn->js(true)->off());
        $view->js(true, $this->nextStepBtn->js()->removeClass('disabled'));
        $view->js(true, $this->execActionBtn->js()->removeClass('disabled'));
    }

    /**
     * Generate js for Next btn state.
     */
    protected function jsSetNextState(string $step): JsExpressionable
    {
        if ($this->isLastStep($step)) {
            return $this->nextStepBtn->js(true)->hide();
        }

        return $this->nextStepBtn->js(true)->show();
    }

    /**
     * Generated js for Prev btn state.
     */
    protected function jsSetPrevState(string $step): JsExpressionable
    {
        if ($this->isFirstStep($step)) {
            return $this->prevStepBtn->js(true)->hide();
        }

        return $this->prevStepBtn->js(true)->show();
    }

    /**
     * Generate js for Exec button state.
     */
    protected function jsSetExecState(string $step): JsExpressionable
    {
        if ($this->isLastStep($step)) {
            return $this->execActionBtn->js(true)->show();
        }

        return $this->execActionBtn->js(true)->hide();
    }

    /**
     * Determine which button is responsible for submitting form on a specific step.
     */
    protected function jsSetSubmitBtn(View $view, Form $form, string $step)
    {
        if ($this->isLastStep($step)) {
            $view->js(true, $this->execActionBtn->js()->on('click', new JsFunction([$form->js(null, null, $form->formElement)->form('submit')])));
        } else {
            // submit on next
            $view->js(true, $this->nextStepBtn->js()->on('click', new JsFunction([$form->js(null, null, $form->formElement)->form('submit')])));
        }
    }

    /**
     * Generate js function for Previous button.
     */
    protected function jsSetPrevHandler(View $view, string $step)
    {
        if ($prev = $this->getPreviousStep($step)) {
            $chain = $this->loader->jsLoad([
                'step' => $prev,
                $this->name => $this->action->getEntity()->getId(),
            ], ['method' => 'post'], $this->loader->name);

            $view->js(true, $this->prevStepBtn->js()->on('click', new JsFunction([$chain])));
        }
    }

    /**
     * Utility for setting form in each step.
     */
    protected function addFormTo(View $view): Form
    {
        $f = $view->add($this->form);
        $f->buttonSave->destroy();

        return $f;
    }

    /**
     * Utility for setting Title for each step.
     */
    private function _addStepTitle(View $view, string $step)
    {
        if ($title = $this->stepTitle[$step] ?? null) {
            $view->add($title);
        }
    }

    /**
     * Get action preview based on it's argument.
     *
     * @return mixed
     */
    protected function getActionPreview()
    {
        $args = [];

        foreach ($this->action->args as $key => $val) {
            $args[] = $this->getActionData('args')[$key];
        }

        return $this->action->preview(...$args);
    }

    /**
     * Utility for retrieving Argument.
     */
    private function _getActionArgs(array $data): array
    {
        $args = [];

        foreach ($this->action->args as $key => $val) {
            $args[] = $data[$key];
        }

        return $args;
    }

    /**
     * Create a sequence of js statement for a view.
     *
     * @param array|JsExpressionable $js
     */
    private function _jsSequencer(View $view, $js)
    {
        if (is_array($js)) {
            foreach ($js as $jq) {
                $this->_jsSequencer($view, $jq);
            }
        } else {
            $view->js(true, $js);
        }
    }

    private function _handleException(\Throwable $exception, $view, $step)
    {
        $msg = Message::addTo($view, ['Error:', 'type' => 'error']);
        $msg->text->addHtml($this->getApp()->renderExceptionHtml($exception));
        $view->js(true, $this->nextStepBtn->js()->addClass('disabled'));
        if (!$this->isFirstStep($step)) {
            $this->jsSetPrevHandler($view, $step);
        }
        if ($this->isLastStep($step)) {
            $view->js(true, $this->execActionBtn->js()->addClass('disabled'));
        }
        if ($step === 'final') {
            $this->jsSetPrevHandler($view, $this->steps[count($this->steps) - 1]);
        }
    }
}
