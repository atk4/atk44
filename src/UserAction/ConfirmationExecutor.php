<?php

declare(strict_types=1);

namespace Atk4\Ui\UserAction;

use Atk4\Core\HookTrait;
use Atk4\Data\Model;
use Atk4\Data\Model\UserAction;
use Atk4\Ui\Button;
use Atk4\Ui\Exception;
use Atk4\Ui\JsExpressionable;
use Atk4\Ui\JsFunction;
use Atk4\Ui\JsToast;
use Atk4\Ui\Loader;
use Atk4\Ui\Modal;
use Atk4\Ui\Text;
use Atk4\Ui\View;

/**
 * Modal executor for action that required a confirmation.
 */
class ConfirmationExecutor extends Modal implements JsExecutorInterface
{
    use HookTrait;

    /** @var Model\UserAction|null Action to execute */
    public $action;

    /** @var Loader|null Loader to add content to modal. */
    public $loader;

    /** @var string css class for loader. */
    public $loaderUi = 'ui basic segment';
    /** @var array|View|null Loader shim object or seed. */
    public $loaderShim;
    /** @var JsExpressionable */
    public $jsSuccess;

    /** @var string css class for modal size. */
    public $size = 'tiny';

    /** @var string|null */
    private $step;
    private $actionInitialized = false;

    /** @var Button Ok button */
    private $ok;
    /** @var Button Cancel button */
    private $cancel;

    protected function init(): void
    {
        parent::init();
        $this->observeChanges();
        $this->addClass($this->size);
    }

    /**
     * Properly set element id for this modal.
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
        $btns = (new View())->addStyle(['min-height' => '24px']);
        $this->ok = Button::addTo($btns, ['Ok', 'blue']);
        $this->cancel = Button::addTo($btns, ['Cancel']);
        $this->add($btns, 'actions');
        $this->showActions = true;

        $this->loader = Loader::addTo($this, ['ui' => $this->loaderUi, 'shim' => $this->loaderShim]);
        $this->loader->loadEvent = false;
        $this->loader->addClass('atk-hide-loading-content');
    }

    /**
     * Return js expression that will trigger action executor.
     *
     * @return mixed
     */
    public function jsExecute(array $urlArgs)
    {
        if (!$this->actionInitialized) {
            throw new Exception('Action must be set prior to assign trigger.');
        }

        return [$this->show(), $this->loader->jsLoad($urlArgs, ['method' => 'post'])];
    }

    public function getAction(): UserAction
    {
        return $this->action;
    }

    /**
     * Will associate executor with the action.
     *
     * @return ConfirmationExecutor
     */
    public function setAction(Model\UserAction $action): Modal
    {
        $this->action = $action;
        $this->afterActionInit($action);

        $this->title = $this->title ?? $action->getDescription();
        $this->step = $this->stickyGet('step');

        $this->actionInitialized = true;
        $this->jsSetBtnState($this);

        return $this;
    }

    /**
     * Perform this action steps.
     */
    public function executeModelAction()
    {
        $id = $this->stickyGet($this->name);
        if ($id && $this->action->appliesTo === Model\UserAction::APPLIES_TO_SINGLE_RECORD) {
            $this->action->setEntity($this->action->getModel()->tryLoad($id));
        }

        $this->loader->set(function ($modal) {
            $this->jsSetBtnState($modal);
            if ($this->step === 'exec') {
                $this->doFinal($modal);
            } else {
                $this->doConfirmation($modal);
            }
        });
    }

    /**
     * Reset button state.
     */
    protected function jsSetBtnState(View $view)
    {
        // reset button handler.
        $view->js(true, $this->ok->js(true)->off());
        $view->js(true, $this->cancel->js(true)->off());
    }

    /**
     * Set modal for displaying confirmation message.
     * Also apply proper javascript to each button.
     */
    public function doConfirmation(View $modal)
    {
        $this->addConfirmation($modal);

        $modal->js(
            true,
            $this->ok->js()->on(
                'click',
                new JsFunction(
                    [
                        $this->loader->jsLoad(
                            [
                                'step' => 'exec',
                                $this->name => $this->action->getEntity()->getId(),
                            ],
                            ['method' => 'post']
                        ),
                    ]
                )
            )
        );

        $modal->js(
            true,
            $this->cancel->js()->on(
                'click',
                new JsFunction(
                    [
                        $this->hide(),
                    ]
                )
            )
        );
    }

    /**
     * Add confirmation message to modal.
     */
    protected function addConfirmation(View $view)
    {
        Text::addTo($view)->set($this->action->getConfirmation());
    }

    /**
     * Execute action when all step are completed.
     */
    protected function doFinal(View $modal)
    {
        $return = $this->action->execute([]);

        $this->_jsSequencer($modal, $this->jsGetExecute($return, $this->action->getEntity()->getId()));
    }

    /**
     * Return proper js statement when action execute.
     */
    protected function jsGetExecute($obj, $id): array
    {
        $success = $this->jsSuccess instanceof \Closure
            ? ($this->jsSuccess)($this, $this->action->getModel(), $id)
            : $this->jsSuccess;

        return [
            $this->hide(),
            $this->ok->js(true)->off(),
            $this->cancel->js(true)->off(),
            $this->hook(BasicExecutor::HOOK_AFTER_EXECUTE, [$obj, $id]) ?: $success ?: new JsToast('Success' . (is_string($obj) ? (': ' . $obj) : '')),
        ];
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
}
