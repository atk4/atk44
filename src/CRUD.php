<?php

declare(strict_types=1);

namespace atk4\ui;

use atk4\data\Model;

/**
 * Implements a more sophisticated and interactive Data-Table component.
 */
class CRUD extends Grid
{
    /** @var array of fields to display in Grid */
    public $displayFields;

    /** @var array|null of fields to edit in Form for Model edit action */
    public $editFields;

    /** @var array|null of fields to edit in Form for Model add action */
    public $addFields;

    /** @var array Default notifier to perform when adding or editing is successful * */
    public $notifyDefault = [jsToast::class];

    /** @var string default js action executor class in UI for model action. */
    public $jsExecutor = [UserAction\JsCallbackExecutor::class];

    /** @var string default action executor class in UI for model action. */
    public $executor = [UserAction\ModalExecutor::class];

    /** @var bool|null should we use table column drop-down menu to display user actions? */
    public $useMenuActions;

    /** @var array Collection of SCOPE_NONE Scope Model action menu item */
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
     * @deprecated use action modifier instead
     */
    public $reloadTableActions = [];

    /**
     * @var array Action name container that will remove the corresponding table row after executing
     *
     * @deprecated use action modifier instead
     */
    public $removeRowActions = [];

    public function init(): void
    {
        parent::init();

        if ($sortBy = $this->getSortBy()) {
            $this->app ? $this->app->stickyGet($this->name . '_sort') : $this->stickyGet($this->name . '_sort', $sortBy);
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
                //Remove previous click handler and attach new one using sort argument.
                $this->container->js(true, $item['item']->js()->off('click.atk_crud_item'));
                $ex = $item['action']->ui['executor'];
                if ($ex instanceof UserAction\JsExecutorInterface) {
                    $ex->stickyGet($this->name . '_sort', $this->getSortBy());
                    $this->container->js(true, $item['item']->js()->on('click.atk_crud_item', new jsFunction($ex->jsExecute())));
                }
            }
        }
    }

    /**
     * Sets data model of CRUD.
     *
     * @param array|null $fields
     */
    public function setModel(Model $m, $fields = null): Model
    {
        if ($fields !== null) {
            $this->displayFields = $fields;
        }

        parent::setModel($m, $this->displayFields);

        // Grab model id when using delete. Must be set before delete action execute.
        $this->model->onHook('afterDelete', function ($m) {
            $this->deletedId = $m->get($m->id_field);
        });

        $this->model->unload();

        if ($this->useMenuActions === null) {
            $this->useMenuActions = count($m->getUserActions()) > 4;
        }

        foreach ($this->_getModelActions(Model\UserAction::SCOPE_SINGLE) as $action) {
            $action->ui['executor'] = $this->initActionExecutor($action);
            if ($this->useMenuActions) {
                $this->addActionMenuItem($action);
            } else {
                $this->addActionButton($action);
            }
        }

        if ($this->menu) {
            foreach ($this->_getModelActions(Model\UserAction::SCOPE_NONE) as $k => $action) {
                if ($action->enabled) {
                    $action->ui['executor'] = $this->initActionExecutor($action);
                    $this->menuItems[$k]['item'] = $this->menu->addItem([$action->getDescription(), 'icon' => 'plus']);
                    $this->menuItems[$k]['action'] = $action;
                }
            }
            $this->setItemsAction();
        }

        return $this->model;
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
     *
     * @return object
     */
    protected function initActionExecutor(Model\UserAction $action)
    {
        $executor = $this->getExecutor($action);
        $executor->onHook(UserAction\BasicExecutor::HOOK_AFTER_EXECUTE, function ($ex, $return, $id) use ($action) {
            return $this->jsExecute($return, $action);
        });

        if ($executor instanceof UserAction\ModalExecutor) {
            foreach ($this->onActions as $onAction) {
                $executor->onHook(UserAction\ModalExecutor::HOOK_STEP, function ($ex, $step, $form) use ($onAction, $action) {
                    $key = key($onAction);
                    if ($key === $action->short_name && $step === 'fields') {
                        return call_user_func($onAction[$key], $form, $ex);
                    }
                });
            }
        }

        return $executor;
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
    protected function getJsGridAction(Model\UserAction $action): ?jsExpressionable
    {
        switch ($action->modifier) {
            case Model\UserAction::MODIFIER_UPDATE:
            case Model\UserAction::MODIFIER_CREATE:
                $js = $this->container->jsReload($this->_getReloadArgs());

                break;
            case Model\UserAction::MODIFIER_DELETE:
                // use deleted record id to remove row, fallback to closest tr if id is not available.
                $js = $this->deletedId ?
                    (new jQuery('tr[data-id="' . $this->deletedId . '"]'))->transition('fade left') :
                    (new jQuery())->closest('tr')->transition('fade left');

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
        $notifier = $this->factory($this->notifyDefault);
        if ($msg) {
            $notifier->setMessage($msg);
        }

        return $notifier;
    }

    /**
     * Setup js for firing menu action.
     */
    protected function setItemsAction()
    {
        foreach ($this->menuItems as $k => $item) {
            $this->container->js(true, $item['item']->on('click.atk_crud_item', $item['action']));
        }
    }

    /**
     * Return proper action executor base on model action.
     *
     * @return object
     */
    protected function getExecutor(Model\UserAction $action)
    {
        if (isset($action->ui['executor'])) {
            return $this->factory($action->ui['executor']);
        }

        // prioritize CRUD addFields over action->fields for Model add action.
        if ($action->short_name === 'add' && $this->addFields) {
            $action->fields = $this->addFields;
        }

        // prioritize CRUD editFields over action->fields for Model edit action.
        if ($action->short_name === 'edit' && $this->editFields) {
            $action->fields = $this->editFields;
        }

        // setting right action fields is based on action fields.
        $executor = (!$action->args && !$action->fields && !$action->preview) ? $this->jsExecutor : $this->executor;

        return $this->factory($executor);
    }

    /**
     * Return reload argument based on CRUD condition.
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
    private function _getModelActions(string $scope): array
    {
        $actions = [];
        if ($scope === Model\UserAction::SCOPE_SINGLE && !empty($this->singleScopeActions)) {
            foreach ($this->singleScopeActions as $action) {
                $actions[] = $this->model->getUserAction($action);
            }
        } elseif ($scope === Model\UserAction::SCOPE_NONE && !empty($this->noRecordScopeActions)) {
            foreach ($this->noRecordScopeActions as $action) {
                $actions[] = $this->model->getUserAction($action);
            }
        } else {
            $actions = $this->model->getUserActions($scope);
        }

        return $actions;
    }

    /**
     * Set callback for edit action in CRUD.
     * Callback function will receive the Edit Form and Executor as param.
     */
    public function onFormEdit(callable $fx)
    {
        $this->setOnActions('edit', $fx);
    }

    /**
     * Set callback for add action in CRUD.
     * Callback function will receive the Add Form and Executor as param.
     */
    public function onFormAdd(callable $fx)
    {
        $this->setOnActions('add', $fx);
    }

    /**
     * Set callback for both edit and add action form.
     * Callback function will receive Forms and Executor as param.
     */
    public function onFormAddEdit(callable $fx)
    {
        $this->onFormEdit($fx);
        $this->onFormAdd($fx);
    }

    /**
     * Set onActions.
     *
     * @return mixed|null
     */
    public function setOnActions(string $actionName, callable $fx)
    {
        $this->onActions[] = [$actionName => $fx];
    }
}
