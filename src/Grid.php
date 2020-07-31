<?php

declare(strict_types=1);

namespace atk4\ui;

use atk4\core\HookTrait;
use atk4\data\Model;

/**
 * Implements a more sophisticated and interactive Data-Table component.
 */
class Grid extends View
{
    use HookTrait;

    /** @const string not used, make it public if needed or drop it */
    private const HOOK_ON_USER_ACTION = self::class . '@onUserAction';

    /**
     * Will be initialized to Menu object, however you can set this to false to disable menu.
     *
     * @var Menu|false
     */
    public $menu;

    /**
     * Calling addQuickSearch will create a form with a field inside $menu to perform quick searches.
     *
     * If you pass this property as array of field names while creating Grid, then when you will call
     * setModel() QuickSearch object will be created automatically and these model fields will be used
     * for filtering.
     *
     * @var array|Form\Control
     */
    public $quickSearch;

    /**
     * Paginator is automatically added below the table and will divide long tables into pages.
     *
     * You can provide your own Paginator object here to customize.
     *
     * @var Paginator|false
     */
    public $paginator;

    /**
     * Number of items per page to display.
     *
     * @var int
     */
    public $ipp = 50;

    /**
     * Calling addActionButton will add a new column inside $table, and will be re-used
     * for next addActionButton().
     *
     * @var Table\Column\ActionButtons
     */
    public $actionButtons;

    /**
     * Calling addAction will add a new column inside $table with dropdown menu,
     * and will be re-used for next addActionMenuItem().
     *
     * @var Table\Column
     */
    public $actionMenu;

    /**
     * Calling addSelection will add a new column inside $table, containing checkboxes.
     * This column will be stored here, in case you want to access it.
     *
     * @var Table\Column\Checkbox
     */
    public $selection;

    /**
     * Grid can be sorted by clicking on column headers. This will be automatically enabled
     * if Model supports ordering. You may override by setting true/false.
     *
     * @var bool
     */
    public $sortable;

    /**
     * Set this if you want GET argument name to look beautifully for sorting.
     *
     * @var string|null
     */
    public $sortTrigger;

    /**
     * Component that actually renders data rows / columns and possibly totals.
     *
     * @var Table|false
     */
    public $table;

    public $executor_class = UserAction\BasicExecutor::class;

    /**
     * The container for table and paginator.
     *
     * @var View
     */
    public $container;

    public $defaultTemplate = 'grid.html';

    /**
     * Defines which Table Decorator to use for ActionButtons.
     *
     * @var string
     */
    protected $actionButtonsDecorator = [Table\Column\ActionButtons::class];

    /**
     * Defines which Table Decorator to use for ActionMenu.
     *
     * @var array
     */
    protected $actionMenuDecorator = [Table\Column\ActionMenu::class, 'label' => 'Actions...'];

    public function init(): void
    {
        parent::init();
        $this->container = View::addTo($this, ['template' => $this->template->cloneRegion('Container')]);
        $this->template->del('Container');

        if (!$this->sortTrigger) {
            $this->sortTrigger = $this->name . '_sort';
        }

        // if menu not disabled ot not already assigned as existing object
        if ($this->menu !== false && !is_object($this->menu)) {
            $this->menu = $this->add($this->factory([Menu::class, 'activate_on_click' => false], $this->menu), 'Menu');
        }

        $this->table = $this->container->add($this->factory([Table::class, 'very compact very basic striped single line', 'reload' => $this->container], $this->table), 'Table');

        if ($this->paginator !== false) {
            $seg = View::addTo($this->container, [], ['Paginator'])->addStyle('text-align', 'center');
            $this->paginator = $seg->add($this->factory([Paginator::class, 'reload' => $this->container], $this->paginator));
            $this->app ? $this->app->stickyGet($this->paginator->name) : $this->stickyGet($this->paginator->name);
        }

        $this->app ? $this->app->stickyGet('_q') : $this->stickyGet('_q');
    }

    /**
     * Set Table\Column\Actions seed.
     */
    public function setActionDecorator($seed)
    {
        $this->actionButtonsDecorator = $seed;
    }

    /**
     * Set Table\Column\ActionMenu seed.
     */
    public function setActionMenuDecorator($seed)
    {
        $this->actionMenuDecorator = $seed;
    }

    /**
     * Add new column to grid. If column with this name already exists,
     * an. Simply calls Table::addColumn(), so check that method out.
     *
     * @param string                   $name            Data model field name
     * @param array|string|object|null $columnDecorator
     * @param array|string|object|null $field
     *
     * @return Table\Column
     */
    public function addColumn($name, $columnDecorator = null, $field = null)
    {
        return $this->table->addColumn($name, $columnDecorator, $field);
    }

    /**
     * Add additional decorator for existing column.
     *
     * @param string             $name      Column name
     * @param Table\Column|array $decorator Seed or object of the decorator
     */
    public function addDecorator($name, $decorator)
    {
        return $this->table->addDecorator($name, $decorator);
    }

    /**
     * Add a new buton to the Grid Menu with a given text.
     *
     * WARNING: needs to be reviewed!
     *
     * @param mixed $text
     */
    public function addButton($text)
    {
        if (!$this->menu) {
            throw new Exception('Unable to add Button without Menu');
        }

        return Button::addTo($this->menu->addItem(), [$text]);
    }

    /**
     * Set item per page value.
     *
     * if an array is passed, it will also add an ItemPerPageSelector to paginator.
     *
     * @param int|array $ipp
     * @param string    $label
     */
    public function setIpp($ipp, $label = 'Items per page:')
    {
        if (is_array($ipp)) {
            $this->addItemsPerPageSelector($ipp, $label);

            $this->ipp = $_GET['ipp'] ?? $ipp[0];
        } else {
            $this->ipp = $ipp;
        }
    }

    /**
     * Add ItemsPerPageSelector View in grid menu or paginator in order to dynamically setup number of item per page.
     *
     * @param array  $items an array of item's per page value
     * @param string $label the memu item label
     *
     * @return $this
     */
    public function addItemsPerPageSelector($items = [10, 25, 50, 100], $label = 'Items per page:')
    {
        if ($ipp = $this->container->stickyGet('ipp')) {
            $this->ipp = $ipp;
        } else {
            $this->ipp = $items[0];
        }

        $pageLength = ItemsPerPageSelector::addTo($this->paginator, ['pageLengthItems' => $items, 'label' => $label, 'currentIpp' => $this->ipp], ['afterPaginator']);
        $this->paginator->template->trySet('PaginatorType', 'ui grid');

        if ($sortBy = $this->getSortBy()) {
            $pageLength->stickyGet($this->sortTrigger, $sortBy);
        }

        $pageLength->onPageLengthSelect(function ($ipp) use ($pageLength) {
            $this->ipp = $ipp;
            $this->setModelLimitFromPaginator();
            //add ipp to quicksearch
            if ($this->quickSearch instanceof JsSearch) {
                $this->container->js(true, $this->quickSearch->js()->atkJsSearch('setUrlArgs', ['ipp', $this->ipp]));
            }
            $this->applySort();

            //return the view to reload.
            return $this->container;
        });

        return $this;
    }

    /**
     * Add dynamic scrolling paginator.
     *
     * @param int    $ipp          number of item per page to start with
     * @param array  $options      an array with js Scroll plugin options
     * @param View   $container    The container holding the lister for scrolling purpose. Default to view owner.
     * @param string $scrollRegion A specific template region to render. Render output is append to container html element.
     *
     * @return $this
     */
    public function addJsPaginator($ipp, $options = [], $container = null, $scrollRegion = 'Body')
    {
        if ($this->paginator) {
            $this->paginator->destroy();
            //prevent action(count) to be output twice.
            $this->paginator = null;
        }

        if ($sortBy = $this->getSortBy()) {
            $this->stickyGet($this->sortTrigger, $sortBy);
        }
        $this->applySort();

        $this->table->addJsPaginator($ipp, $options, $container, $scrollRegion);

        return $this;
    }

    /**
     * Add dynamic scrolling paginator in container.
     * Use this to make table headers fixed.
     *
     * @param int    $ipp             number of item per page to start with
     * @param int    $containerHeight number of pixel the table container should be
     * @param array  $options         an array with js Scroll plugin options
     * @param View   $container       The container holding the lister for scrolling purpose. Default to view owner.
     * @param string $scrollRegion    A specific template region to render. Render output is append to container html element.
     *
     * @return $this
     */
    public function addJsPaginatorInContainer($ipp, $containerHeight, $options = [], $container = null, $scrollRegion = 'Body')
    {
        $this->table->hasCollapsingCssActionColumn = false;
        $options = array_merge($options, [
            'hasFixTableHeader' => true,
            'tableContainerHeight' => $containerHeight,
        ]);
        //adding a state context to js scroll plugin.
        $options = array_merge(['stateContext' => '#' . $this->container->name], $options);

        return $this->addJsPaginator($ipp, $options, $container, $scrollRegion);
    }

    /**
     * Add Search input field using js action.
     * By default, will query server when using Enter key on input search field.
     * You can change it to query server on each keystroke by passing $autoQuery true,.
     *
     * @param array $fields       the list of fields to search for
     * @param bool  $hasAutoQuery will query server on each key pressed
     */
    public function addQuickSearch($fields = [], $hasAutoQuery = false)
    {
        if (!$this->model) {
            throw new Exception('Call setModel() before addQuickSearch()');
        }

        if (!$fields) {
            $fields = [$this->model->title_field];
        }

        if (!$this->menu) {
            throw new Exception('Unable to add QuickSearch without Menu');
        }

        $view = View::addTo($this->menu
            ->addMenuRight()->addItem()->setElement('div'));

        $this->quickSearch = JsSearch::addTo($view, ['reload' => $this->container, 'autoQuery' => $hasAutoQuery]);

        $q = trim($this->stickyGet('_q') ?? '');
        if ($q !== '') {
            $cond = [];
            foreach ($fields as $field) {
                $cond[] = [$field, 'like', '%' . $q . '%'];
            }
            $this->model->addCondition($cond);
        }
    }

    /**
     * Returns JS for reloading View.
     *
     * @param array             $args
     * @param JsExpression|null $afterSuccess
     * @param array             $apiConfig
     *
     * @return \atk4\ui\JsReload
     */
    public function jsReload($args = [], $afterSuccess = null, $apiConfig = [])
    {
        return new JsReload($this->container, $args, $afterSuccess, $apiConfig);
    }

    /**
     * Adds a new button into the action column on the right. For Crud this
     * column will already contain "delete" and "edit" buttons.
     *
     * @param string|array|View         $button Label text, object or seed for the Button
     * @param JsExpressionable|\Closure $action JavaScript action or callback
     *
     * @return object
     */
    public function addActionButton($button, $action = null, string $confirmMsg = '', $isDisabled = false)
    {
        if (!$this->actionButtons) {
            $this->actionButtons = $this->table->addColumn(null, $this->actionButtonsDecorator);
        }

        return $this->actionButtons->addButton($button, $action, $confirmMsg, $isDisabled);
    }

    /**
     * Similar to addAction. Will add Button that when click will display
     * a Dropdown menu.
     *
     * @param View $view
     *
     * @return mixed
     */
    public function addActionMenuItem($view, $action = null, string $confirmMsg = '', bool $isDisabled = false)
    {
        if (!$this->actionMenu) {
            $this->actionMenu = $this->table->addColumn(null, $this->actionMenuDecorator);
        }

        return $this->actionMenu->addActionMenuItem($view, $action, $confirmMsg, $isDisabled);
    }

    /**
     * Add action menu item using an array.
     */
    public function addActionMenuItems(array $actions = [])
    {
        foreach ($actions as $action) {
            $this->addActionMenuItem($action);
        }
    }

    /**
     * Add action menu items using Model.
     * You may specify the scope of actions to be added.
     *
     * @param string|null $appliesTo the scope of model action
     */
    public function addActionMenuFromModel(string $appliesTo = null)
    {
        if (!$this->model) {
            throw new Exception('Model not set, set it prior to add item.');
        }

        foreach ($this->model->getUserActions($appliesTo) as $action) {
            $this->addActionMenuItem($action);
        }
    }

    /**
     * An array of column name where filter is needed.
     * Leave empty to include all column in grid.
     *
     * @param array|null $names an array with the name of column
     *
     * @return $this
     */
    public function addFilterColumn($names = null)
    {
        if (!$this->menu) {
            throw new Exception('Unable to add Filter Column without Menu');
        }
        $this->menu->addItem(['Clear Filters'], new \atk4\ui\JsReload($this->table->reload, ['atk_clear_filter' => 1]));
        $this->table->setFilterColumn($names);

        return $this;
    }

    /**
     * Add a dropdown menu to header column.
     *
     * @param string   $columnName the name of column where to add dropdown
     * @param array    $items      the menu items to add
     * @param \Closure $fx         the callback function to execute when an item is selected
     * @param string   $icon       the icon
     * @param string   $menuId     the menu id return by callback
     */
    public function addDropdown($columnName, $items, \Closure $fx, $icon = 'caret square down', $menuId = null)
    {
        $column = $this->table->columns[$columnName];
        if (!isset($column)) {
            throw new Exception('The column where you want to add dropdown does not exist: ' . $columnName);
        }
        if (!$menuId) {
            $menuId = $columnName;
        }

        $column->addDropdown($items, function ($item) use ($fx) {
            return $fx([$item]);
        }, $icon, $menuId);
    }

    /**
     * Add a popup to header column.
     *
     * @param string $columnName the name of column where to add popup
     * @param Popup  $popup      popup view
     * @param string $icon       the icon
     *
     * @return mixed
     */
    public function addPopup($columnName, $popup = null, $icon = 'caret square down')
    {
        $column = $this->table->columns[$columnName];
        if (!isset($column)) {
            throw new Exception('The column where you want to add popup does not exist: ' . $columnName);
        }

        return $column->addPopup($popup, $icon);
    }

    /**
     * Similar to addAction but when button is clicked, modal is displayed
     * with the $title and $callback is executed through VirtualPage.
     *
     * @param string|array|View $button
     * @param string            $title
     * @param \Closure          $callback function($page) {...
     * @param array             $args     extra url argument for callback
     *
     * @return object
     */
    public function addModalAction($button, $title, \Closure $callback, $args = [])
    {
        if (!$this->actionButtons) {
            $this->actionButtons = $this->table->addColumn(null, $this->actionButtonsDecorator);
        }

        return $this->actionButtons->addModal($button, $title, $callback, $this, $args);
    }

    /**
     * Find out more about the nature of the action from the supplied object, use addAction().
     */
    public function addUserAction(Model\UserAction $action)
    {
        $executor = null;
        $args = [];
        $title = $action->caption;
        $button = $action->caption;

        if ($action->ui['Grid']['Button'] ?? null) {
            $button = $action->ui['Grid']['Button'];
        }

        if ($action->ui['Grid']['Executor'] ?? null) {
            $executor = $action->ui['Grid']['Executor'];
        }

        if (!$executor || is_string($executor)) {
            $class = $executor ?? $this->executor_class;
            $executor = new $class();
        }

        if ($this->paginator) {
            $args[$this->paginator->name] = $this->paginator->getCurrentPage();
        }

        $this->addModalAction($button, $title, function ($page, $id) use ($action, $executor) {
            $page->add($executor);

            $this->hook(self::HOOK_ON_USER_ACTION, [$page, $executor]);

            $action->owner->load($id);

            $executor->setAction($action);
        }, $args);
    }

    /**
     * Get sortBy value from url parameter.
     *
     * @return string|null
     */
    public function getSortBy()
    {
        return $_GET[$this->sortTrigger] ?? null;
    }

    /**
     * Apply ordering to the current model as per the sort parameters.
     */
    public function applySort()
    {
        if ($this->sortable === false) {
            return;
        }

        $sortBy = $this->getSortBy();

        if ($sortBy && $this->paginator) {
            $this->paginator->addReloadArgs([$this->sortTrigger => $sortBy]);
        }

        $desc = false;
        if ($sortBy && $sortBy[0] === '-') {
            $desc = true;
            $sortBy = substr($sortBy, 1);
        }

        $this->table->sortable = true;

        if ($sortBy && isset($this->table->columns[$sortBy]) && $this->model->hasField($sortBy)) {
            $this->model->setOrder($sortBy, $desc);
            $this->table->sort_by = $sortBy;
            $this->table->sort_order = $desc ? 'descending' : 'ascending';
        }

        $this->table->on(
            'click',
            'thead>tr>th.sortable',
            new JsReload($this->container, [$this->sortTrigger => (new Jquery())->data('sort')])
        );
    }

    /**
     * Sets data Model of Grid.
     *
     * If $columns is not defined, then automatically will add columns for all
     * visible model fields. If $columns is set to false, then will not add
     * columns at all.
     *
     * @param array|bool $columns
     *
     * @return \atk4\data\Model
     */
    public function setModel(Model $model, $columns = null)
    {
        $this->model = $this->table->setModel($model, $columns);

        if (is_array($this->quickSearch)) {
            $this->addQuickSearch($this->quickSearch);
        }

        return $this->model;
    }

    /**
     * Makes rows of this grid selectable by creating new column on the left with
     * checkboxes.
     *
     * @return Table\Column\Checkbox
     */
    public function addSelection()
    {
        $this->selection = $this->table->addColumn(null, [Table\Column\Checkbox::class]);

        // Move last column to the beginning in table column array.
        array_unshift($this->table->columns, array_pop($this->table->columns));

        return $this->selection;
    }

    /**
     * Add column with drag handler on each row.
     * Drag handler allow to reorder table via drag n drop.
     *
     * @return Table\Column
     */
    public function addDragHandler()
    {
        $handler = $this->table->addColumn(null, [Table\Column\DragHandler::class]);

        // Move last column to the beginning in table column array.
        array_unshift($this->table->columns, array_pop($this->table->columns));

        return $handler;
    }

    /**
     * Will set model limit according to paginator value.
     */
    private function setModelLimitFromPaginator()
    {
        $this->paginator->setTotal((int) ceil($this->model->toQuery('count')->getOne() / $this->ipp));
        $this->model->setLimit($this->ipp, ($this->paginator->page - 1) * $this->ipp);
    }

    /**
     * Before rendering take care of data sorting.
     */
    protected function renderView(): void
    {
        // take care of sorting
        $this->applySort();

        parent::renderView();
    }

    /**
     * Recursively renders view.
     */
    protected function recursiveRender(): void
    {
        // bind with paginator
        if ($this->paginator) {
            $this->setModelLimitFromPaginator();
        }

        if ($this->quickSearch instanceof JsSearch) {
            if ($sortBy = $this->getSortBy()) {
                $this->container->js(true, $this->quickSearch->js()->atkJsSearch('setUrlArgs', [$this->sortTrigger, $sortBy]));
            }
        }

        parent::recursiveRender();
    }

    /**
     * Proxy function for Table::jsRow().
     *
     * @return Jquery
     */
    public function jsRow()
    {
        return $this->table->jsRow();
    }
}
