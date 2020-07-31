<?php

declare(strict_types=1);
/**
 * A collection of Card set from a model.
 */

namespace atk4\ui;

use atk4\data\Model;
use atk4\ui\Component\ItemSearch;

class CardDeck extends View
{
    public $ui = '';

    /** @var string|View Card type inside this deck. */
    public $card = [Card::class];

    /** @var string default template file. */
    public $defaultTemplate = 'card-deck.html';

    /** @var bool Whether card should use table display or not. */
    public $useTable = false;

    /** @var bool Whether card should use label display or not. */
    public $useLabel = false;

    /** @var string|null If using extra field in Card, glue, join them using extra glue. */
    public $extraGlue = ' - ';

    /** @var bool If each card should use action or not. */
    public $useAction = true;

    /** @var View|null The container view. The view that is reload when page or data changed. */
    public $container = [View::class, 'ui' => 'basic segment'];

    /** @var View The view containing Cards. */
    public $cardHolder = [View::class, 'ui' => 'cards'];

    /** @var Paginator|null The paginator view. */
    public $paginator = [Paginator::class];

    /** @var int The number of cards to be displayed per page. */
    public $ipp = 9;

    /** @var array|null A menu seed for displaying button inside. */
    public $menu = [View::class, 'ui' => 'stackable grid'];

    /** @var array|ItemSearch */
    public $search = [ItemSearch::class, 'ui' => 'ui compact basic segment'];

    /** @var View|null A view container for buttons. Added into menu when menu is set. */
    private $btns;

    /** @var string Button css class for menu. */
    public $menuBtnStyle = 'primary';

    /** @var string Default executor class. */
    public $executor = [UserAction\ModalExecutor::class];

    /** @var string Default jsExecutor class. */
    public $jsExecutor = [UserAction\JsCallbackExecutor::class];

    /** @var array Default notifier to perform when model action is successful * */
    public $notifyDefault = [JsToast::class, 'settings' => ['displayTime' => 5000]];

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

    /** @var array seed to create View for displaying when search result is empty. */
    public $noRecordDisplay = [
        Message::class,
        'content' => 'Result empty!',
        'icon' => 'info circle',
        'text' => 'Your search did not return any record or there is no record available.',
    ];

    /** @var array A collection of menu button added in Menu. */
    private $menuActions = [];

    /** @var string|null The current search query string. */
    private $query;

    public function init(): void
    {
        parent::init();
        $this->container = $this->add($this->container);

        if ($this->menu !== false) {
            $this->addMenuBar();
        }

        $this->cardHolder = $this->container->add($this->cardHolder);

        if ($this->paginator !== false) {
            $this->addPaginator();
        }
    }

    /**
     * Add menu bar view to CardDeck.
     */
    protected function addMenuBar()
    {
        $this->menu = $this->add($this->factory($this->menu), 'Menu');

        $left = View::addTo($this->menu, ['ui' => $this->search !== false ? 'twelve wide column' : 'sixteen wide column']);
        $this->btns = View::addTo($left, ['ui' => 'buttons']);

        if ($this->search !== false) {
            $right = View::addTo($this->menu, ['ui' => 'four wide column']);
            $this->search = $right->add($this->factory($this->search, ['context' => '#' . $this->container->name]));
            $this->search->reload = $this->container;
            $this->query = $this->app->stickyGet($this->search->queryArg);
        }
    }

    /**
     * Add Paginator view to card deck.
     */
    protected function addPaginator()
    {
        $seg = View::addTo($this->container, ['ui' => 'basic segment'])->addStyle('text-align', 'center');
        $this->paginator = $seg->add($this->factory($this->paginator, ['reload' => $this->container]));
    }

    public function setModel(Model $model, array $fields = null, array $extra = null): Model
    {
        parent::setModel($model);

        if ($this->search !== false) {
            $this->model = $this->search->setModelCondition($this->model);
        }

        if ($count = $this->initPaginator()) {
            $this->model->each(function ($m) use ($fields, $extra) {
                // need model clone in order to keep it's loaded values
                $m = clone $m;
                $c = $this->cardHolder->add($this->factory($this->card, ['useLabel' => $this->useLabel, 'useTable' => $this->useTable]))->addClass('segment');
                $c->setModel($m, $fields);
                if ($extra) {
                    $c->addExtraFields($m, $extra, $this->extraGlue);
                }
                if ($this->useAction) {
                    if ($singleActions = $this->_getModelActions(Model\UserAction::APPLIES_TO_SINGLE_RECORD)) {
                        $args = $this->_getReloadArgs();
                        $id_arg = [];
                        foreach ($singleActions as $action) {
                            $action->ui['executor'] = $this->initActionExecutor($action);
                            if ($action->ui['executor'] instanceof UserAction\JsCallbackExecutor) {
                                $id_arg[0] = (new Jquery())->parents('.atk-card')->data('id');
                            }
                            $c->addClickAction($action, null, array_merge($id_arg, $args));
                        }
                    }
                }
            });
        } else {
            $this->cardHolder->addClass('centered')->add($this->factory($this->noRecordDisplay));
        }

        // add no record scope action to menu
        if ($this->useAction && $this->menu) {
            foreach ($this->_getModelActions(Model\UserAction::APPLIES_TO_NO_RECORDS) as $k => $action) {
                $action->ui['executor'] = $this->initActionExecutor($action);
                $this->menuActions[$k]['btn'] = $this->addMenuButton($action, null, false, false, $this->_getReloadArgs());
                $this->menuActions[$k]['action'] = $action;
            }
        }

        return $this->model;
    }

    /**
     * Reset Menu button js event when reloading occur in order
     * to have their arguments always in sync after container reload.
     */
    protected function applyReload()
    {
        foreach ($this->menuActions as $menuAction) {
            $ex = $menuAction['action']->ui['executor'];
            if ($ex instanceof UserAction\JsExecutorInterface) {
                $this->container->js(true, $menuAction['btn']->js()->off('click'));
                $this->container->js(true, $menuAction['btn']->js()->on('click', new JsFunction($ex->jsExecute($this->_getReloadArgs()))));
            }
        }
    }

    /**
     * Setup executor for an action.
     * First determine what fields action needs,
     * then setup executor based on action fields, args and/or preview.
     *
     * Single record scope action use jsSuccess instead of afterExecute hook
     * because hook will keep adding for every cards, thus repeating jsExecute multiple time,
     * i.e. once for each card, unless hook is break.
     *
     * @return object
     */
    protected function initActionExecutor(Model\UserAction $action)
    {
        $action->fields = $this->editFields ?? $action->fields;
        $executor = $this->getExecutor($action);
        if ($action->appliesTo === Model\UserAction::APPLIES_TO_SINGLE_RECORD) {
            $executor->jsSuccess = function ($x, $m, $id, $return) use ($action) {
                return $this->jsExecute($return, $action);
            };
        } else {
            $executor->onHook(UserAction\BasicExecutor::HOOK_AFTER_EXECUTE, function ($ex, $return, $id) use ($action) {
                return $this->jsExecute($return, $action);
            });
        }

        return $executor;
    }

    /**
     * Return proper js statement for afterExecute hook on action executor
     * depending on return type, model loaded and action scope.
     *
     * @return array|object
     */
    protected function jsExecute($return, $action)
    {
        if (is_string($return)) {
            return  $this->getNotifier($return, $action);
        } elseif (is_array($return) || $return instanceof JsExpressionable) {
            return $return;
        } elseif ($return instanceof Model) {
            $msg = $return->loaded() ? $this->saveMsg : ($action->appliesTo === Model\UserAction::APPLIES_TO_SINGLE_RECORD ? $this->deleteMsg : $this->defaultMsg);

            return $this->jsModelReturn($action, $msg);
        }

        return $this->getNotifier($this->defaultMsg, $action);
    }

    /**
     * Return jsNotifier object.
     * Override this method for setting notifier based on action or model value.
     *
     * @param string|null           $msg    the message to display
     * @param Model\UserAction|null $action the model action
     *
     * @return object
     */
    protected function getNotifier($msg = null, $action = null)
    {
        $notifier = $this->factory($this->notifyDefault);
        if ($msg) {
            $notifier->setMessage($msg);
        }

        return $notifier;
    }

    /**
     * js expression return when action afterHook executor return a Model.
     */
    protected function jsModelReturn(Model\UserAction $action = null, string $msg = 'Done!'): array
    {
        $js[] = $this->getNotifier($msg, $action);
        if ($action->owner->loaded() && $card = $this->findCard($action->owner)) {
            $js[] = $card->jsReload($this->_getReloadArgs());
        } else {
            $js[] = $this->container->jsReload($this->_getReloadArgs());
        }

        return $js;
    }

    /**
     * Check if a card is still in current set and
     * return it. Otherwise return null.
     * After an action is execute and data is saved, the db result
     * set might be different than previous one, which represent cards displayed on page.
     *
     * For example, editing a card which does not fulfill search requirement after it has been saved.
     * Or when adding a new one.
     * Therefore if card, that was just save, is not present in db result set or deck then return null
     * otherwise return Card view.
     *
     * @return mixed|null
     */
    protected function findCard(Model $model)
    {
        $mapResults = function ($a) use ($model) {
            return $a[$model->id_field];
        };
        $deck = [];
        foreach ($this->cardHolder->elements as $v => $element) {
            if ($element instanceof $this->card) {
                $deck[$element->model->id] = $element;
            }
        }

        if (in_array($model->id, array_map($mapResults, $model->export([$model->id_field])), true)) {
            // might be in result set but not in deck, for example when adding a card.
            return $deck[$model->id] ?? null;
        }
    }

    /**
     * Return reload argument based on Deck condition.
     *
     * @return mixed
     */
    private function _getReloadArgs()
    {
        $args = [];
        if ($this->paginator !== false) {
            $args[$this->paginator->name] = $this->paginator->getCurrentPage();
        }
        if ($this->search !== false) {
            $args[$this->search->queryArg] = $this->query;
        }

        return $args;
    }

    /**
     * Add button to menu bar on top of deck card.
     *
     * @param Button|string|Model\UserAction                  $button   a button object, a model action or a string representing a model action
     * @param Model\UserAction|JsExpressionable|\Closure|null $callback an model action, js expression or callback function
     * @param string|array                                    $confirm  A confirmation string or View::on method defaults when passed has an array,
     *
     * @return mixed
     */
    public function addMenuButton($button, $callback = null, $confirm = null, bool $isDisabled = false, $args = null)
    {
        $defaults = [];

        if ($confirm) {
            $defaults['confirm'] = $confirm;
        }

        if ($args) {
            $defaults['args'] = $args;
        }

        // If action is not specified, perhaps it is defined in the model
        if (!$callback && is_string($button)) {
            $model_action = $this->model->getUserAction($button);
            if ($model_action) {
                $isDisabled = !$model_action->enabled;
                $callback = $model_action;
                $button = $callback->caption;
                if ($model_action->ui['confirm'] ?? null) {
                    $defaults['confirm'] = $model_action->ui['confirm'];
                }
            }
        } elseif (!$callback && $button instanceof Model\UserAction) {
            $isDisabled = !$button->enabled;
            if ($button->ui['confirm'] ?? null) {
                $defaults['confirm'] = $button->ui['confirm'];
            }
            $callback = $button;
            $button = $button->caption;
        }

        if ($callback instanceof Model\UserAction) {
            if (isset($callback->ui['button'])) {
                $button = $callback->ui['button'];
            }

            if (isset($callback->ui['confirm'])) {
                $defaults['confirm'] = $callback->ui['confirm'];
            }
        }

        if (!is_object($button)) {
            if (is_string($button)) {
                $button = [$button, 'ui' => 'button ' . $this->menuBtnStyle];
            }
            $button = $this->factory([Button::class], $button);
        }

        if ($button->icon && !is_object($button->icon)) {
            $button->icon = $this->factory([Icon::class], $button->icon);
        }

        if ($isDisabled) {
            $button->addClass('disabled');
        }

        $btn = $this->btns->add($button);
        $btn->on('click', $callback, $defaults);

        return $btn;
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

        $executor = (!$action->args && !$action->fields && !$action->preview) ? $this->jsExecutor : $this->executor;

        return $this->factory($executor);
    }

    protected function renderView(): void
    {
        if (($this->menu && count($this->menuActions) > 0) || $this->search !== false) {
            View::addTo($this, ['ui' => 'divider'], ['Divider']);
        }

        if ($this->container->name === ($_GET['__atk_reload'] ?? null)) {
            $this->applyReload();
        }
        parent::renderView();
    }

    /**
     * Return proper action need to setup menu or action column.
     */
    private function _getModelActions(string $appliesTo): array
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
     * Will set model limit according to paginator value.
     */
    protected function initPaginator()
    {
        $count = $this->model->toQuery('count')->getOne();
        if ($this->paginator) {
            if ($count > 0) {
                $this->paginator->setTotal((int) ceil($count / $this->ipp));
                $this->model->setLimit($this->ipp, ($this->paginator->page - 1) * $this->ipp);
            } else {
                $this->paginator->destroy();
            }
        }

        return $count;
    }
}
