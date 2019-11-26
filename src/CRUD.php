<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\ui;

use atk4\data\UserAction\Action;
use atk4\data\UserAction\Generic;
use atk4\ui\ActionExecutor\jsInterface_;
use atk4\ui\ActionExecutor\jsUserAction;
use atk4\ui\ActionExecutor\UserAction;
use atk4\ui\ActionExecutor\UserConfirmation;

/**
 * Implements a more sophisticated and interactive Data-Table component.
 */
class CRUD extends Grid
{
    /** @var array of fields to display in Grid */
    public $displayFields = null;

    /** @var array of fields to edit in Form */
    public $editFields = null;

    /** @var array Default notifier to perform when adding or editing is successful * */
    public $notifyDefault = ['jsToast', 'settings' => ['message' => 'Data is saved!', 'class' => 'success']];

    /** @var string default js action executor class in UI for model action. */
    public $jsExecutor = jsUserAction::class;

    /** @var string default action executor class in UI for model action. */
    public $executor = UserAction::class;

    /** @var bool|null should we use drop-down menu to display user actions? */
    public $useMenuActions = null;

    /** @var array Collection of NO_RECORDS Scope Model action menu item */
    private $menuItems = [];

    public function init()
    {
        parent::init();

        if ($sortBy = $this->getSortBy()) {
            $this->app->stickyGet($this->name.'_sort', $sortBy);
        }
    }

    /**
     * Sets data model of CRUD.
     *
     * @param \atk4\data\Model $m
     * @param null|array       $fields
     *
     * @throws \atk4\core\Exception
     * @throws Exception
     *
     * @return \atk4\data\Model
     */
    public function setModel(\atk4\data\Model $m, $fields = null) : \atk4\data\Model
    {
        if ($fields !== null) {
            $this->displayFields = $fields;
        }

        parent::setModel($m, $this->displayFields);

        $this->model->unload();

        if (is_null($this->useMenuActions)) {
            $this->useMenuActions = count($m->getActions()) > 4;
        }

        foreach ($m->getActions(Generic::SINGLE_RECORD) as $single_record_action) {
            $executor = $this->getActionExecutor($single_record_action);
            $single_record_action->fields = ($executor instanceof jsUserAction || $executor instanceof UserConfirmation) ? false : ($this->editFields ?? []);
            $single_record_action->ui['executor'] = $executor;
            $executor->addHook('afterExecute', function ($x, $m, $id) {
                return $m->loaded() ? $this->jsSave($this->notifyDefault) : $this->jsDelete();
            });
            if ($this->useMenuActions) {
                $this->addActionMenuItem($single_record_action);
            } else {
                $this->addAction($single_record_action);
            }
        }

        foreach ($m->getActions(Generic::NO_RECORDS) as $k => $single_record_action) {
            $executor = $this->factory($this->getActionExecutor($single_record_action));
            if ($executor instanceof View) {
                $executor->stickyGet($this->name.'_sort', $this->getSortBy());
            }
            $single_record_action->fields = ($executor instanceof jsUserAction) ? false : ($this->editFields ?? []);
            $single_record_action->ui['executor'] = $executor;
            $executor->addHook('afterExecute', function ($x, $m, $id) {
                return $m->loaded() ? $this->jsSave($this->notifyDefault) : $this->jsDelete();
            });
            $this->menuItems[$k]['item'] = $this->menu->addItem([$single_record_action->getDescription(), 'icon' => 'plus']);
            $this->menuItems[$k]['action'] = $single_record_action;
        }
        $this->setItemsAction();

        return $this->model;
    }

    /**
     * Setup js for firing action.
     *
     * @throws \atk4\core\Exception
     */
    protected function setItemsAction()
    {
        foreach ($this->menuItems as $k => $item) {
            $this->container->js(true, $item['item']->on('click.atk_crud_item', $item['action']));
        }
    }

    public function renderView()
    {
        return parent::renderView(); // TODO: Change the autogenerated stub
    }

    /**
     * Return proper action executor base on model action.
     *
     * @param \atk4\data\UserAction\Generic $action
     *
     * @throws \atk4\core\Exception
     *
     * @return object
     */
    protected function getActionExecutor(\atk4\data\UserAction\Generic $action)
    {
        if (isset($action->ui['executor'])) {
            return $this->factory($action->ui['executor']);
        }

        $executor = (!$action->args && !$action->fields && !$action->preview) ? $this->jsExecutor : $this->executor;

        return $this->factory($executor);
    }

    /**
     * Apply ordering to the current model as per the sort parameters.
     */
    public function applySort()
    {
        parent::applySort();

        if ($this->getSortBy() && !empty($this->menuItems)) {
            foreach ($this->menuItems as $k => $item) {
                //Remove previous click handler and attach new one using sort argument.
                $this->container->js(true, $item['item']->js()->off('click.atk_crud_item'));
                $ex = $item['action']->ui['executor'];
                if ($ex instanceof jsInterface_) {
                    $ex->stickyGet($this->name.'_sort', $this->getSortBy());
                    $this->container->js(true, $item['item']->js()->on('click.atk_crud_item', new jsFunction($ex->jsExecute())));
                }
            }
        }
    }

    /**
     * Default js action when saving.
     *
     * @param mixed $notifier
     *
     * @throws \atk4\core\Exception
     *
     * @return array
     */
    public function jsSave($notifier) : array
    {
        return [
            $this->factory($notifier, null, 'atk4\ui'),
            // reload Grid Container.
            $this->container->jsReload([$this->name.'_sort' => $this->getSortBy()]),
        ];
    }

    /**
     *  Return js statement necessary to remove a row in Grid when
     *  use in $(this) context.
     *
     * @return jQuery
     */
    public function jsDelete() : jQuery
    {
        return (new jQuery())->closest('tr')->transition('fade left');
    }

    /**
     * Set callback for edit action in CRUD.
     * Callback function will receive the Edit Form and Executor as param.
     *
     * @param callable $fx
     *
     * @throws Exception
     */
    public function onEditAction(callable $fx)
    {
        $this->setOnActionForm($fx, 'edit');
    }

    /**
     * Set callback for add action in CRUD.
     * Callback function will receive the Edit Form and Executor as param.
     *
     * @param callable $fx
     *
     * @throws Exception
     */
    public function onAddAction(callable $fx)
    {
        $this->setOnActionForm($fx, 'add');
    }

    /**
     * Set callback for both edit and add action form.
     * Callback function will receive Forms and Executor as param.
     *
     * @param callable $fx
     *
     * @throws Exception
     */
    public function onAction(callable $fx)
    {
        $this->onEditAction($fx);
        $this->onAddAction($fx);
    }

    /**
     * Set onAction callback using UserAction executor.
     *
     * @param callable $fx
     * @param string   $actionName
     *
     * @throws Exception
     * @throws \atk4\core\Exception
     * @throws \atk4\data\Exception
     *
     * @return null|mixed
     */
    public function setOnActionForm(callable $fx, string $actionName)
    {
        if (!$this->model) {
            throw new Exception('Model need to be set prior to use on Form');
        }

        $ex = $this->model->getAction($actionName)->ui['executor'];
        if ($ex && $ex instanceof UserAction) {
            $ex->addHook('onStep', function ($ex, $step, $form) use ($fx) {
                if ($step === 'fields') {
                    return call_user_func($fx, $form, $ex);
                }
            });
        }
    }
}
