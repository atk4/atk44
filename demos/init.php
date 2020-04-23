<?php

date_default_timezone_set('UTC');

require_once __DIR__ . '/../vendor/autoload.php';

/* START - PHPUNIT & COVERAGE SETUP */
if (file_exists(__DIR__ . '/coverage.php')) {
    include_once __DIR__ . '/coverage.php';
}

require_once __DIR__ . '/somedatadef.php';

class Demo extends \atk4\ui\Columns
{
    public $left;
    public $right;
    public static $isInitialized = false;
    public $highlightDefaultStyle = 'dark';
    public $left_width = 8;
    public $right_width = 8;

    public function init(): void
    {
        parent::init();
        $this->addClass('celled');

        $this->left = $this->addColumn($this->left_width);
        $this->right = $this->addColumn($this->right_width);
    }

    public function setCode($code, $lang = 'php')
    {
        $this->highLightCode();
        \atk4\ui\View::addTo(\atk4\ui\View::addTo($this->left, ['element'=>'pre']), ['element' => 'code'])->addClass($lang)->set($code);
        $app = $this->right;
        $app->db = $this->app->db;
        eval($code);
    }

    public function highLightCode()
    {
        if (!self::$isInitialized) {
            $this->app->requireCSS('//cdn.jsdelivr.net/gh/highlightjs/cdn-release@9.16.2/build/styles/' . $this->highlightDefaultStyle . '.min.css');
            $this->app->requireJS('//cdn.jsdelivr.net/gh/highlightjs/cdn-release@9.16.2/build/highlight.min.js');
            $this->js(true, (new \atk4\ui\jsChain('hljs'))->initHighlighting());
            self::$isInitialized = true;
        }
    }
}

class PromotionText extends \atk4\ui\View
{
    public function init(): void
    {
        parent::init();

        $t = \atk4\ui\Text::addTo($this);
        $t->addParagraph(
            <<< 'EOF'
Agile Toolkit base package includes:
EOF
        );

        $t->addHTML(
            <<< 'HTML'
<ul>
<li>Over 40 ready-to-use and nicely styled UI components</li>
<li>Over 10 ways to build interraction</li>
<li>Over 10 configurable field types, relations, aggregation and much more</li>
<li>Over 5 SQL and some NoSQL vendors fully supported</li>
</ul>

HTML
        );

        $gl = \atk4\ui\GridLayout::addTo($this, [null, 'stackable divided', 'columns'=>4]);
        \atk4\ui\Button::addTo($gl, ['Explore UI components', 'primary basic fluid', 'iconRight'=>'right arrow'], ['r1c1'])
            ->link('https://github.com/atk4/ui/#bundled-and-planned-components');
        \atk4\ui\Button::addTo($gl, ['Try out interactive features', 'primary basic fluid', 'iconRight'=>'right arrow'], ['r1c2'])
            ->link(['loader', 'begin'=>false, 'layout'=>false]);
        \atk4\ui\Button::addTo($gl, ['Dive into Agile Data', 'primary basic fluid', 'iconRight'=>'right arrow'], ['r1c3'])
            ->link('https://git.io/ad');
        \atk4\ui\Button::addTo($gl, ['More ATK Add-ons', 'primary basic fluid', 'iconRight'=>'right arrow'], ['r1c4'])
            ->link('https://github.com/atk4/ui/#add-ons-and-integrations');


        \atk4\ui\View::addTo($this, ['ui'=>'divider']);

        \atk4\ui\Message::addTo($this, ['Cool fact!', 'info', 'icon'=>'book'])->text
            ->addParagraph('This entire demo is coded in Agile Toolkit and takes up less than 300 lines of very simple code code!');
    }
}



$app = new \atk4\ui\App([
    'call_exit'        => isset($_GET['APP_CALL_EXIT']) && $_GET['APP_CALL_EXIT'] == 0 ? false : true,
    'catch_exceptions' => isset($_GET['APP_CATCH_EXCEPTIONS']) && $_GET['APP_CATCH_EXCEPTIONS'] == 0 ? false : true,
]);

if ($app->call_exit !== true) {
    $app->stickyGet('APP_CALL_EXIT');
}

if ($app->catch_exceptions !== true) {
    $app->stickyGet('APP_CATCH_EXCEPTIONS');
}

if (file_exists('coverage.php')) {
    $app->onHook('beforeExit', function () {
        coverage();
    });
}
/* END - PHPUNIT & COVERAGE SETUP */

$app->title = 'Agile UI Demo v' . $app->version;

if (file_exists('../public/atkjs-ui.min.js')) {
    $app->cdn['atk'] = '/public';
}

$app->initLayout($app->stickyGET('layout') ?: 'Maestro');

$layout = $app->layout;
// Need for phpUnit test only for producing right url.
$layout->name = 'atk_admin';
$layout->id = $layout->name;

if ($layout instanceof \atk4\ui\Layout\Navigable) {
    $layout->addMenuItem(['Welcome to Agile Toolkit', 'icon' => 'gift'], ['/demos/index']);

    $path = '/demos/layout/';
    $ly = $layout->addMenuGroup(['Layout', 'icon' => 'object group']);
    $layout->addMenuItem(['Layouts'], [$path . 'layouts'], $ly);
    $layout->addMenuItem(['Panel'], [$path . 'layout-panel'], $ly);

    $path = '/demos/basic/';
    $basic = $layout->addMenuGroup(['Basics', 'icon' => 'cubes']);
    $layout->addMenuItem('View', [$path . 'view'], $basic);
    $layout->addMenuItem('Button', [$path . 'button'], $basic);
    $layout->addMenuItem('Header', [$path . 'header'], $basic);
    $layout->addMenuItem('Message', [$path . 'message'], $basic);
    $layout->addMenuItem('Labels', [$path . 'label'], $basic);
    $layout->addMenuItem('Menu', [$path . 'menu'], $basic);
    $layout->addMenuItem('BreadCrumb', [$path . 'breadcrumb'], $basic);
    $layout->addMenuItem(['Columns'], [$path . 'columns'], $basic);
    $layout->addMenuItem(['Grid Layout'], [$path . 'grid-layout'], $basic);

    $path = '/demos/form/';
    $form = $layout->addMenuGroup(['Form', 'icon' => 'edit']);
    $layout->addMenuItem('Basics and Layouting', [$path . 'form'], $form);
    $layout->addMenuItem('Data Integration', [$path . 'form2'], $form);
    $layout->addMenuItem(['Form Sections'], [$path . 'form-section'], $form);
    $layout->addMenuItem('Form Multi-column layout', [$path . 'form3'], $form);
    $layout->addMenuItem(['Integration with Columns'], [$path . 'form5'], $form);
    $layout->addMenuItem(['Custom Layout'], [$path . 'form-custom-layout'], $form);
    $layout->addMenuItem(['Conditional Fields'], [$path . 'jscondform'], $form);

    $path = '/demos/input/';
    $in = $layout->addMenuGroup(['Input', 'icon' => 'keyboard outline']);
    $layout->addMenuItem(['Input Fields'], [$path . 'field2'], $in);
    $layout->addMenuItem('Input Field Decoration', [$path . 'field'], $in);
    $layout->addMenuItem(['Checkboxes'], [$path . 'checkbox'], $in);
    $layout->addMenuItem(['Value Selectors'], [$path . 'form6'], $in);
    $layout->addMenuItem(['Lookup'], [$path . 'lookup'], $in);
    $layout->addMenuItem(['Lookup Dependency'], [$path . 'lookup-dep'], $in);
    $layout->addMenuItem(['DropDown'], [$path . 'dropdown-plus'], $in);
    $layout->addMenuItem(['File Upload'], [$path . 'upload'], $in);
    $layout->addMenuItem(['Multi Line'], [$path . 'multiline'], $in);
    $layout->addMenuItem(['Tree Selector'], [$path . 'tree-item-selector'], $in);

    $path = '/demos/collection/';
    $g_t = $layout->addMenuGroup(['Data Collection', 'icon' => 'table']);
    $layout->addMenuItem(['Actions - Integration Examples'], [$path . 'actions'], $g_t);
    $layout->addMenuItem('Data table with formatted columns', [$path . 'table'], $g_t);
    $layout->addMenuItem(['Advanced table examples'], [$path . 'table2'], $g_t);
    $layout->addMenuItem('Table interractions', [$path . 'multitable'], $g_t);
    $layout->addMenuItem(['Column Menus'], [$path . 'tablecolumnmenu'], $g_t);
    $layout->addMenuItem(['Column Filters'], [$path . 'tablefilter'], $g_t);
    $layout->addMenuItem('Grid - Table+Bar+Search+Paginator', [$path . 'grid'], $g_t);
    $layout->addMenuItem('CRUD - Full editing solution', [$path . 'crud'], $g_t);
    $layout->addMenuItem(['CRUD with Array Persistence'], [$path . 'crud3'], $g_t);
    $layout->addMenuItem(['Lister'], [$path . 'lister-ipp'], $g_t);
    $layout->addMenuItem(['Table column decorator from model'], [$path . 'tablecolumns'], $g_t);
    $layout->addMenuItem(['Drag n Drop sorting'], [$path . 'jssortable'], $g_t);

    $path = '/demos/interactive/';
    $adv = $layout->addMenuGroup(['Interactive', 'icon' => 'talk']);
    $layout->addMenuItem('Tabs', [$path . 'tabs'], $adv);
    $layout->addMenuItem('Card', [$path . 'card'], $adv);
    $layout->addMenuItem(['Accordion'], [$path . 'accordion'], $adv);
    $layout->addMenuItem(['Wizard'], [$path . 'wizard'], $adv);
    $layout->addMenuItem(['Virtual Page'], [$path . 'virtual'], $adv);
    $layout->addMenuItem(['Modal'], [$path . 'modal2'], $adv);
    $layout->addMenuItem('Dynamic Modal', [$path . 'modal'], $adv);
    $layout->addMenuItem(['Loader'], [$path . 'loader'], $adv);
    $layout->addMenuItem(['Console'], [$path . 'console'], $adv);
    $layout->addMenuItem(['Dynamic scroll'], [$path . 'scroll-lister'], $adv);
    $layout->addMenuItem(['Background PHP Jobs (SSE)'], [$path . 'sse'], $adv);
    $layout->addMenuItem(['Progress Bar'], [$path . 'progress'], $adv);
    $layout->addMenuItem(['Pop-up'], [$path . 'popup'], $adv);
    $layout->addMenuItem(['Toast'], [$path . 'toast'], $adv);
    $layout->addMenuItem('Paginator', [$path . 'paginator'], $adv);

    $path = '/demos/javascript/';
    $js = $layout->addMenuGroup(['Javascript', 'icon' => 'code']);
    $layout->addMenuItem('Events', [$path . 'js'], $js);
    $layout->addMenuItem('Element Reloading', [$path . 'reloading'], $js);
    $layout->addMenuItem('Vue Integration', [$path . 'vue-component'], $js);

    $path = '/demos/others/';
    $other = $layout->addMenuGroup(['Others', 'icon' => 'plus']);
    $layout->addMenuItem('Sticky GET', [$path . 'sticky'], $other);
    $layout->addMenuItem('More Sticky', [$path . 'sticky2'], $other);
    $layout->addMenuItem('Recursive Views', [$path . 'recursive'], $other);


    $f = basename($_SERVER['PHP_SELF']);


    //$url = 'https://github.com/atk4/ui/blob/feature/grid-part2/demos/';
    $url = 'https://github.com/atk4/ui/blob/develop';

    $ex =[
        new \atk4\ui\jsExpression('const baseUrl = []', [$url]),
        new \atk4\ui\jsExpression('document.location = baseUrl + document.location.pathname'),
    ];

    // Would be nice if this would be a link.
    \atk4\ui\Button::addTo($layout->menu->addItem()->addClass('aligned right'), ['View Source', 'teal', 'icon' => 'github'])
        ->setAttr('target', '_blank')->on('click', $ex);

    $img = 'https://raw.githubusercontent.com/atk4/ui/07208a0af84109f0d6e3553e242720d8aeedb784/public/logo.png';
}
