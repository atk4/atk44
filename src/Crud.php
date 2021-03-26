<?php

declare(strict_types=1);

namespace Atk4\Ui;

use Atk4\Core\Factory;
use Atk4\Data\Model;

/**
 * Implements a more sophisticated and interactive Data-Table component.
 */
class Crud extends Grid
{
    /** @var array of fields to display in Grid */
    public $displayFields;

    /** @var array|null of fields to edit in Form for Model edit action */
    public $editFields;

    /** @var array|null of fields to edit in Form for Model add action */
    public $addFields;

    /** @var array Default notifier to perform when adding or editing is successful * */
    public $notifyDefault = [JsToast::class];

    /** @var bool|null should we use table column drop-down menu to display user actions? */
    public $useMenuActions;

    /** @var array Collection of APPLIES_TO_NO_RECORDS Scope Model action menu item */
    private $menuItems = [];

    /** @var array Model single scope action to include in table action column. Will include all single scope actions if empty. */
    public $singleScopeActions = [];

    /** @var array Model no_record scope action to include in menu. Will include all no record scope actions if empty. */
    public $noRecordScopeActions = [];

    /** @var string Message to display when record is add or edit successfully. */
    public $saveMsg = 'Record has been saved!';

    /** @var string Message to display when record is delete successfully. */
    public $deleteMsg = 'Record has been deleted!';

    /** @var string Generic display message for no record scope action where model is not loaded. */
    public $defaultMsg = 'Done!';

    /** @var array Callback containers for model action. */
    public $onActions = [];

    /** @var mixed recently deleted record id. */
    private $deletedId;

    /**
     * @var array Action name container that will reload Table after executing
     *
     * @deprecated use action modifier instead, will be removed in v2.5
     */
    public $reloadTableActions = [];

    /**
     * @var array Action name container that will remove the corresponding table row after executing
     *
     * @deprecated use action modifier instead, will be removed in v2.5
     */
    public $removeRowActions = [];

    protected function init(): void
    {
        parent::init();

        if ($sortBy = $this->getSortBy()) {
            $this->issetApp() ? $this->getApp()->stickyGet($this->name . '_sort') : $this->stickyGet($this->name . '_sort', $sortBy);
        }
    }

    /**
     * Apply ordering to the current model as per the sort parameters.
     */
    public function applySort()
    {
        parent::applySort();

        if ($this->getSortBy() && !empty($this->menuItems)) {
            foreach ($this->menuItems as $item) {
                // Remove previous click handler and attach new one using sort argument.
                $this->container->js(true, $item['item']->js()->off('click.atk_crud_item'));
                $ex = $item['executor'];
                if ($ex instanceof UserAction\JsExecutorInterface) {
                    $ex->stickyGet($this->name . '_sort', $this->getSortBy());
                    $this->container->js(true, $item['item']->js()->on('click.atk_crud_item', new JsFunction($ex->jsExecute())));
                }
            }
        }
    }

    /**
     * Sets data model of Crud.
     *
     * @param array|null $fields
     */
    public function setModel(Model $model, $fields = null): Model
    {
        if ($fields !== null) {
            $this->displayFields = $fields;
        }

        parent::setModel($model, $this->displayFields);

        // Grab model id when using delete. Must be set before delete action execute.
        $this->model->onHook(Model::HOOK_AFTER_DELETE, function ($model) {
            $this->deletedId = $model->getId();
        });

        $this->model->unload();

        if ($this->useMenuActions === null) {
            $this->useMenuActions = count($model->getUserActions()) > 4;
        }

        foreach ($this->filterModelActions(Model\UserAction::APPLIES_TO_SINGLE_RECORD) as $action) {
            $executor = $this->initActionExecutor($action);
            if ($this->useMenuActions) {
                $this->addExecutorMenuItem($executor);
            } else {
                $this->addExecutorButton($executor);
            }
        }

        if ($this->menu) {
            foreach ($this->filterModelActions(Model\UserAction::APPLIES_TO_NO_RECORDS) as $action) {
                if ($action->enabled) {
                    $this->initActionExecutor($action);

                    $menuItem = $this->menu->addItem(
                            $this->getExecutorFactory()->createTrigger($action, $this->getExecutorFactory()::MENU_ITEM)
                            );

                    // Setup js for firing menu action
                    $this->container->js(true, $menuItem->on('click.atk_crud_item', $action));

                    $this->menuItems[$action->short_name] = $menuItem;
                }
            }
        }

        return $this->model;
    }

    public function getUserActionMenuItem(string $actionName): ?Item
    {
        return $this->menuItems[$actionName] ?? null;
    }

    /**
     * Setup executor for an action.
     * First determine what fields action needs,
     * then setup executor based on action fields, args and/or preview.
     *
     * Add hook for onStep 'fields'" Hook can call a callback function
     * for UserAction onStep field. Callback will receive executor form where you
     * can setup Input field via javascript prior to display form or change form submit event
     * handler.
     */
    protected function initActionExecutor(Model\UserAction $action): void
    {
        $executor = $this->createActionExecutor($action);
        $executor->onHook(UserAction\BasicExecutor::HOOK_AFTER_EXECUTE, function ($ex, $return, $id) use ($action) {
            return $this->jsExecute($return, $action);
        });

        if ($executor instanceof UserAction\ModalExecutor) {
            foreach ($this->onActions as $onAction) {
                $executor->onHook(UserAction\ModalExecutor::HOOK_STEP, function ($ex, $step, $form) use ($onAction, $action) {
                    $key = key($onAction);
                    if ($key === $action->short_name && $step === 'fields') {
                        return $onAction[$key]($form, $ex);
                    }
                });
            }
        }

        $action->ui['executor'] = $executor;
    }

    /**
     * Return proper js statement for afterExecute hook on action executor
     * depending on return type, model loaded and action scope.
     */
    protected function jsExecute($return, Model\UserAction $action): array
    {
        $js = [];
        if ($jsAction = $this->getJsGridAction($action)) {
            $js[] = $jsAction;
        }

        // display msg return by action or depending on action modifier.
        if (is_string($return)) {
            $js[] = $this->getNotifier($return);
        } else {
            if ($action->modifier === Model\UserAction::MODIFIER_CREATE || $action->modifier === Model\UserAction::MODIFIER_UPDATE) {
                $js[] = $this->getNotifier($this->saveMsg);
            } elseif ($action->modifier === Model\UserAction::MODIFIER_DELETE) {
                $js[] = $this->getNotifier($this->deleteMsg);
            } else {
                $js[] = $this->getNotifier($this->defaultMsg);
            }
        }

        return $js;
    }

    /**
     * Return proper js actions depending on action modifier type.
     */
    protected function getJsGridAction(Model\UserAction $action): ?JsExpressionable
    {
        switch ($action->modifier) {
            case Model\UserAction::MODIFIER_UPDATE:
            case Model\UserAction::MODIFIER_CREATE:
                $js = $this->container->jsReload($this->_getReloadArgs());

                break;
            case Model\UserAction::MODIFIER_DELETE:
                // use deleted record id to remove row, fallback to closest tr if id is not available.
                $js = $this->deletedId ?
                (new Jquery('tr[data-id="' . $this->deletedId . '"]'))->transition('fade left') :
                (new Jquery())->closest('tr')->transition('fade left');

                break;
            default:
                $js = null;
        }

        return $js;
    }

    /**
     * Return jsNotifier object.
     * Override this method for setting notifier based on action or model value.
     *
     * @param string|null $msg the message to display
     *
     * @return object
     */
    protected function getNotifier(string $msg = null)
    {
        $notifier = Factory::factory($this->notifyDefault);
        if ($msg) {
            $notifier->setMessage($msg);
        }

        return $notifier;
    }

    /**
     * Return proper action executor base on model action.
     *
     * @return object
     */
    protected function createActionExecutor(Model\UserAction $action)
    {
        // prioritize Crud addFields over action->fields for Model add action.
        if ($action->short_name === 'add' && $this->addFields) {
            $action->fields = $this->addFields;
        }

        // prioritize Crud editFields over action->fields for Model edit action.
        if ($action->short_name === 'edit' && $this->editFields) {
            $action->fields = $this->editFields;
        }

        return $this->getExecutorFactory()->create($action, $this);
    }

    /**
     * Return reload argument based on Crud condition.
     *
     * @return mixed
     */
    private function _getReloadArgs()
    {
        $args[$this->name . '_sort'] = $this->getSortBy();
        if ($this->paginator) {
            $args[$this->paginator->name] = $this->paginator->getCurrentPage();
        }

        return $args;
    }

    /**
     * Return proper action need to setup menu or action column.
     */
    private function filterModelActions(string $appliesTo): array
    {
        $actions = [];
        if ($appliesTo === Model\UserAction::APPLIES_TO_SINGLE_RECORD && !empty($this->singleScopeActions)) {
            foreach ($this->singleScopeActions as $action) {
                $actions[] = $this->model->getUserAction($action);
            }
        } elseif ($appliesTo === Model\UserAction::APPLIES_TO_NO_RECORDS && !empty($this->noRecordScopeActions)) {
            foreach ($this->noRecordScopeActions as $action) {
                $actions[] = $this->model->getUserAction($action);
            }
        } else {
            $actions = $this->model->getUserActions($appliesTo);
        }

        return $actions;
    }

    /**
     * Set callback for edit action in Crud.
     * Callback function will receive the Edit Form and Executor as param.
     */
    public function onFormEdit(\Closure $fx)
    {
        $this->setOnActions('edit', $fx);
    }

    /**
     * Set callback for add action in Crud.
     * Callback function will receive the Add Form and Executor as param.
     */
    public function onFormAdd(\Closure $fx)
    {
        $this->setOnActions('add', $fx);
    }

    /**
     * Set callback for both edit and add action form.
     * Callback function will receive Forms and Executor as param.
     */
    public function onFormAddEdit(\Closure $fx)
    {
        $this->onFormEdit($fx);
        $this->onFormAdd($fx);
    }

    /**
     * Set onActions.
     *
     * @return mixed
     */
    public function setOnActions(string $actionName, \Closure $fx)
    {
        $this->onActions[] = [$actionName => $fx];
    }
}
