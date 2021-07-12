<?php

declare(strict_types=1);

namespace Atk4\Ui;

use Atk4\Data\Model;
use Atk4\Data\Persistence\Static_;
use Atk4\Ui\UserAction\ExecutorFactory;

/**
 * Implements a most core view, which all of the other components descend
 * form.
 */
class View extends AbstractView implements JsExpressionable
{
    // {{{ Properties of the class

    /**
     * When you call render() this will be populated with JavaScript
     * chains.
     *
     * @internal must remain public so that child views could interact
     * with parent's $js
     *
     * @var array
     */
    public $_js_actions = [];

    /**
     * Data model.
     *
     * @var Model
     */
    public $model;

    /**
     * Name of the region in the parent's template where this object
     * will output itself.
     *
     * @var string
     */
    public $region; //'Content';

    /**
     * Enables UI keyword for Semantic UI indicating that this is a
     * UI element. If you set this variable value to string, it will
     * be appended at the end of the element class.
     *
     * @var bool|string
     */
    public $ui = false;

    /**
     * ID of the element, that's unique and is used in JS operations.
     *
     * @var string
     */
    public $id;

    /**
     * List of classes that needs to be added.
     *
     * @var array
     */
    public $class = [];

    /**
     * List of custom CSS attributes.
     *
     * @var array
     */
    public $style = [];

    /**
     * List of custom attributes.
     *
     * @var array
     */
    public $attr = [];

    /**
     * Just here temporarily, until App picks it up.
     *
     * @var string
     */
    protected $skin;

    /**
     * Template object, that, for most Views will be rendered to
     * produce HTML output. If you leave this object as "null" then
     * a new Template will be generated during init() based on the
     * value of $defaultTemplate.
     *
     * @var HtmlTemplate
     */
    public $template;

    /**
     * Specifies how to initialize $template.
     *
     * If you specify a string, then it will be considered a filename
     * from which to load the $template.
     *
     * @var string
     */
    public $defaultTemplate = 'element.html';

    /**
     * Set static contents of this view.
     *
     * @var string|false
     */
    public $content;

    /**
     * Change this if you want to substitute default "div" for something else.
     *
     * @var string
     */
    public $element;

    /** @var ExecutorFactory Seed class name */
    public $executorFactory;

    // }}}

    // {{{ Setting Things up

    /**
     * May accept properties of a class, but if property is not defined, it will
     * be used as a HTML class instead.
     *
     * @param array|string $label
     * @param array|string $class
     */
    public function __construct($label = null, $class = null)
    {
        if (is_array($label)) {
            // backwards mode
            $defaults = $label;
            if (isset($defaults[0])) {
                $label = $defaults[0];
                unset($defaults[0]);
            } else {
                $label = null;
            }

            if (isset($defaults[1])) {
                $class = $defaults[1];
                unset($defaults[1]);
            }
            $this->setDefaults($defaults);
        }

        if ($label !== null) {
            $this->content = $label;
        }

        if ($class) {
            $this->addClass($class);
        }
    }

    /**
     * Associate this view with a model. Do not place any logic in this class, instead take it
     * to renderView().
     *
     * Do not try to create your own "Model" implementation, instead you must be looking for
     * your own "Persistence" implementation.
     *
     * @return Model
     */
    public function setModel(Model $model)
    {
        $this->model = $model;

        return $model;
    }

    /**
     * Sets source of the View.
     *
     * @param array $data   Array of data
     * @param array $fields Limit model to particular fields
     *
     * @return Model
     */
    public function setSource(array $data, $fields = null)
    {
        // ID with zero value is not supported (at least in MySQL replaces it with next AI value)
        if (isset($data[0])) {
            if ($data === array_values($data)) {
                $oldData = $data;
                $data = [];
                foreach ($oldData as $k => $row) {
                    $data[$k + 1000 * 1000 * 1000] = $row; // large offset to prevent accessing wrong data by old key
                }
            } else {
                throw new Exception('Source data contains unsupported zero key');
            }
        }

        $this->setModel(new Model(new Static_($data)), $fields); // @phpstan-ignore-line
        $this->model->getField($this->model->id_field)->type = null; // TODO probably unwanted

        return $this->model;
    }

    /**
     * @param mixed $value
     */
    protected function setMissingProperty(string $propertyName, $value): void
    {
        if (is_bool($value)) {
            if ($value) {
                $this->addClass($propertyName);
            } else {
                $this->removeClass($propertyName);
            }

            return;
        }

        parent::setMissingProperty($propertyName, $value);
    }

    /**
     * Sets View element.
     *
     * @param string $element
     *
     * @return $this
     */
    public function setElement($element)
    {
        $this->element = $element;

        return $this;
    }

    /**
     * Makes view into a "<a>" element with a link.
     *
     * @param string|array $url
     * @param string       $target
     *
     * @return $this
     */
    public function link($url, $target = null)
    {
        $this->element = 'a';
        if (is_string($url)) {
            $this->setAttr('href', $url);
        } else {
            $this->setAttr('href', $this->url($url));
        }

        if ($target !== null) {
            $this->setAttr('target', $target);
        }

        return $this;
    }

    // }}}

    // {{{ Default init() method and add() logic

    /**
     * Called when view becomes part of render tree. You can override it but avoid
     * placing any "heavy processing" here.
     */
    protected function init(): void
    {
        $addLater = $this->_add_later;
        $this->_add_later = [];
        parent::init();

        if ($this->id === null) {
            $this->id = $this->name;
        }

        if ($this->region && !$this->template && !$this->defaultTemplate && $this->issetOwner() && $this->getOwner()->template) {
            $this->template = $this->getOwner()->template->cloneRegion($this->region);

            $this->getOwner()->template->del($this->region);
        } else {
            // set up template
            if (is_string($this->defaultTemplate) && $this->template === null) {
                $this->template = $this->getApp()->loadTemplate($this->defaultTemplate);
            }

            if (!$this->region) {
                $this->region = 'Content';
            }
        }

        if ($this->template && !$this->template->issetApp() && $this->issetApp()) {
            $this->template->setApp($this->getApp());
        }

        // add default objects
        foreach ($addLater as [$object, $region]) {
            $this->add($object, $region);
        }

        // allow for injecting the model with a seed
        if ($this->model) {
            $this->setModel($this->model);
        }
    }

    public function getExecutorFactory(): ExecutorFactory
    {
        return $this->executorFactory ?? $this->getApp()->getExecutorFactory();
    }

    /**
     * In addition to adding a child object, sets up it's template
     * and associate it's output with the region in our template.
     *
     * @param View              $object
     * @param string|array|null $region
     */
    public function add($object, $region = null): AbstractView
    {
        if (func_num_args() > 2) { // prevent bad usage
            throw new \Error('Too many method arguments');
        }

        if (!is_object($object)) {
            // for BC do not throw
            // later consider to accept strictly objects only
            $object = AbstractView::addToWithCl($this, $object, [], true);
        }

        if (!$this->issetApp()) {
            $this->_add_later[] = [$object, $region];

            return $object;
        }

        if (is_array($region)) {
            $args = $region;
            $region = $args['region'] ?? null;
            unset($args['region']);
        } else {
            $args = null;
        }

        // set region
        if ($region !== null) {
            if (!is_string($region)) {
                throw (new Exception('Region must be a string'))
                    ->addMoreInfo('region_type', gettype($region));
            }

            $object->setDefaults(['region' => $region]);
        }

        // will call init() of the object
        parent::add($object, $args);

        return $object;
    }

    /**
     * Get objects closest owner which is instance of particular class.
     *
     * If there are no such owner (or grand-owner etc.) object, then return.
     *
     * Note: this is internal method, but should be public because other objects
     *       should be able to call it.
     *
     * @param View   $object
     * @param string $class
     *
     * @return View|null
     */
    public function getClosestOwner(self $object, $class)
    {
        if ($object->issetOwner()) {
            return;
        }

        if ($object->getOwner() instanceof $class) {
            return $object->getOwner();
        }

        return $this->getClosestOwner($object->getOwner(), $class);
    }

    // }}}

    // {{{ Manipulating classes and view properties

    /**
     * Override this method without compatibility with parent, if you wish
     * to set your own things your own way for your view.
     *
     * @param mixed $arg1
     * @param mixed $arg2
     *
     * @return $this
     */
    public function set($arg1 = null, $arg2 = null)
    {
        if (is_string($arg1) && $arg2 !== null) {
            // must be initialized
            $this->template->set($arg1, $arg2);

            return $this;
        }

        if ($arg2 !== null) {
            throw (new Exception('Second argument to set() can be only passed if the first one is a string'))
                ->addMoreInfo('arg1', $arg1)
                ->addMoreInfo('arg2', $arg2);
        }

        if (is_scalar($arg1)) {
            $this->content = $arg1;

            return $this;
        }

        if (is_array($arg1)) {
            if (isset($arg1[0])) {
                $this->content = $arg1[0];
                unset($arg1[0]);
            }
            $this->setDefaults($arg1);

            return $this;
        }

        throw (new Exception('Not sure what to do with argument'))
            ->addMoreInfo('this', $this)
            ->addMoreInfo('arg1', $arg1)
            ->addMoreInfo('arg2', $arg2);
    }

    /**
     * Add CSS class to element. Previously added classes are not affected.
     * Multiple CSS classes can also be added if passed as space separated
     * string or array of class names.
     *
     * @param string|array $class CSS class name or array of class names
     *
     * @return $this
     */
    public function addClass($class)
    {
        if (is_array($class)) {
            $class = implode(' ', $class);
        }

        if (!$this->class) {
            $this->class = [];
        }

        if (is_string($this->class)) {
            throw (new Exception('Property $class should always be array'))
                ->addMoreInfo('object', $this)
                ->addMoreInfo('class', $this->class);
        }

        $this->class = array_merge($this->class, explode(' ', $class));

        return $this;
    }

    /**
     * Remove one or several CSS classes from the element.
     *
     * @param array|string $class CSS class name or array of class names
     *
     * @return $this
     */
    public function removeClass($class)
    {
        if (is_array($class)) {
            $class = implode(' ', $class);
        }

        $class = explode(' ', $class);
        $this->class = array_diff($this->class, $class);

        return $this;
    }

    /**
     * Add inline CSS style to element.
     * Multiple CSS styles can also be set if passed as array.
     *
     * @param string|array $property CSS Property or hash
     * @param string       $style    CSS Style definition
     *
     * @return $this
     *
     * @todo Think about difference between setStyle and addStyle
     */
    public function setStyle($property, string $style = null)
    {
        $this->style = array_merge(
            $this->style,
            is_array($property) ? $property : [$property => $style]
        );

        return $this;
    }

    /**
     * @param string|array $property CSS Property or hash
     * @param string       $style    CSS Style definition
     *
     * @return $this
     *
     * @see setStyle()
     */
    public function addStyle($property, string $style = null)
    {
        return $this->setStyle($property, $style);
    }

    /**
     * Remove inline CSS style from element, if it was added with setStyle
     * or addStyle.
     *
     * @param string $property CSS Property to remove
     *
     * @return $this
     */
    public function removeStyle($property)
    {
        unset($this->style[$property]);

        return $this;
    }

    /**
     * Set attribute.
     *
     * @param string|array $attr  Attribute name or hash
     * @param string       $value Attribute value
     *
     * @return $this
     */
    public function setAttr($attr, $value = null)
    {
        if (is_array($attr)) {
            $this->attr = array_merge($this->attr, $attr);

            return $this;
        }

        $this->attr[$attr] = $value;

        return $this;
    }

    /**
     * Remove attribute.
     *
     * @param string|array $property Attribute name or hash
     *
     * @return $this
     */
    public function removeAttr($property)
    {
        if (is_array($property)) {
            foreach ($property as $v) {
                unset($this->attr[$v]);
            }

            return $this;
        }

        unset($this->attr[$property]);

        return $this;
    }

    // }}}

    // {{{ Sticky URLs

    /** @var string[] stickyGet arguments */
    public $stickyArgs = [];

    /**
     * Build an URL which this view can use for js call-backs. It should
     * be guaranteed that requesting returned URL would at some point call
     * $this->invokeInit().
     *
     * @param array $page
     *
     * @return string
     */
    public function jsUrl($page = [])
    {
        return $this->getApp()->jsUrl($page, false, $this->_getStickyArgs());
    }

    /**
     * Build an URL which this view can use for call-backs. It should
     * be guaranteed that requesting returned URL would at some point call
     * $this->invokeInit().
     *
     * @param string|array $page URL as string or array with page name as first element and other GET arguments
     *
     * @return string
     */
    public function url($page = [])
    {
        return $this->getApp()->url($page, false, $this->_getStickyArgs());
    }

    /**
     * Get sticky arguments defined by the view and parents (including API).
     */
    protected function _getStickyArgs(): array
    {
        if ($this->issetOwner() && $this->getOwner() instanceof self) {
            $stickyArgs = array_merge($this->getOwner()->_getStickyArgs(), $this->stickyArgs);
        } else {
            $stickyArgs = $this->stickyArgs;
        }

        return $stickyArgs;
    }

    /**
     * Mark GET argument as sticky. Calling url() on this view or any
     * sub-views will embedd the value of this GET argument.
     *
     * If GET argument is empty or false, it won't make into URL.
     *
     * If GET argument is not presently set you can specify a 2nd argument
     * to forge-set the GET argument for current view and it's sub-views.
     */
    public function stickyGet(string $name, string $newValue = null): ?string
    {
        $this->stickyArgs[$name] = $newValue ?? $_GET[$name] ?? null;

        return $this->stickyArgs[$name];
    }

    // }}}

    // {{{ Rendering

    /**
     * View-specific rendering stuff. Feel free to replace this method with
     * your own. View::renderView contains some logic that integrates with
     * semanticUI.
     *
     * NOTE: maybe in the future, SemanticUI-related stuff needs to go into
     * a separate class.
     */
    protected function renderView(): void
    {
        if ($this->class) {
            $this->template->append('class', implode(' ', $this->class));
        }

        if ($this->style) {
            $style = $this->style;
            array_walk(
                $style,
                function (&$item, $key) {
                    $item = $key . ':' . $item;
                }
            );
            $this->template->append('style', implode(';', $style));
        }

        if ($this->ui) {
            if (is_string($this->ui)) {
                $this->template->set('_class', $this->ui);
            }
        } else {
            $this->template->tryDel('_ui');
        }

        if ($this->id) {
            $this->template->trySet('_id', $this->id);
        }

        if ($this->element) {
            $this->template->set('_element', $this->element);
        }

        if ($this->attr) {
            $tmp = [];
            foreach ($this->attr as $attr => $val) {
                $tmp[] = $attr . '="' . $this->getApp()->encodeAttribute($val) . '"';
            }
            $this->template->dangerouslySetHtml('attributes', implode(' ', $tmp));
        }
    }

    /**
     * Recursively render all children, placing their
     * output in our template.
     */
    protected function recursiveRender(): void
    {
        foreach ($this->elements as $view) {
            if (!$view instanceof self) {
                continue;
            }

            $this->template->dangerouslyAppendHtml($view->region, $view->getHtml());

            if ($view->_js_actions) {
                $this->_js_actions = array_merge_recursive($this->_js_actions, $view->_js_actions);
            }
        }

        if (isset($this->content) && $this->content !== false) {
            $this->template->append('Content', $this->content);
        }
    }

    /**
     * Render everything recursively, render ourselves but don't return
     * anything just yet.
     */
    public function renderAll(): void
    {
        if (!$this->_initialized) {
            $this->invokeInit();
        }

        if (!$this->_rendered) {
            $this->renderView();

            $this->recursiveRender();
            $this->_rendered = true;
        }
    }

    /**
     * For Form::renderTemplateToHtml() only.
     */
    protected function renderTemplateToHtml(string $region = null): string
    {
        return $this->template->renderToHtml($region);
    }

    /**
     * This method is for those cases when developer want to simply render his
     * view and grab HTML himself.
     */
    public function render(bool $forceReturn = true): string
    {
        $this->renderAll();

        return $this->getJs($forceReturn)
               . $this->renderTemplateToHtml();
    }

    /**
     * This method is to render view to place inside a Fomantic-UI Tab.
     */
    public function renderToTab(): array
    {
        $this->renderAll();

        return [
            'atkjs' => $this->getJsRenderActions(),
            'html' => $this->renderTemplateToHtml(),
        ];
    }

    /**
     * Render View using json format.
     *
     * @param string $region a specific template region to render
     */
    public function renderToJsonArr(bool $forceReturn = true, $region = null): array
    {
        $this->renderAll();

        return [
            'success' => true,
            'message' => 'Success',
            'atkjs' => $this->getJs($forceReturn),
            'html' => $this->renderTemplateToHtml($region),
            'id' => $this->name,
        ];
    }

    /**
     * Created for recursive rendering or when you want to only get HTML of
     * this object (not javascript).
     *
     * @return string
     */
    public function getHtml()
    {
        if (isset($_GET['__atk_reload']) && $_GET['__atk_reload'] === $this->name) {
            $this->getApp()->terminateJson($this);
        }

        $this->renderAll();

        return $this->renderTemplateToHtml();
    }

    // }}}

    // {{{ JavaScript integration

    /**
     * Views in Agile UI can assign javascript actions to themselves. This
     * is done by calling $view->js() method which returns instance of JsChain
     * object that is initialized to the object itself. Normally this chain
     * will map into $('#object_id') and calling additional methods will map
     * into additional calls.
     *
     * Action can represent javascript event, such as "click" or "mouseenter".
     * If you specify action = true, then the event will ALWAYS be executed on
     * documentReady. It will also be executed if respective view is being reloaded
     * by js()->reload()
     *
     * (Do not make mistake by specifying "true" instead of true)
     *
     * action = false will still return JsChain but will not bind it.
     * You can bind it by passing object into on() method.
     *
     * 1. Calling with arguments:
     *
     * $view->js();                   // technically does nothing
     * $a = $view->js()->hide();      // creates chain for hiding $view but does not
     *                                // bind to event yet.
     *
     * 2. Binding existing chains
     * $img->on('mouseenter', $a);    // binds previously defined chain to event on
     *                                // event of $img.
     *
     * Produced code: $('#img_id').on('mouseenter', function(ev){ ev.preventDefault();
     *    $('#view1').hide(); });
     *
     * 3. $button->on('click',$form->js()->submit());
     *                                // clicking button will result in form submit
     *
     * 4. $view->js(true)->find('.current')->text($text);
     *
     * Will convert calls to jQuery chain into JavaScript string:
     *  $('#view').find('.current').text('abc');    // The $text will be json-encoded
     *                                              // to avoid JS injection.
     *
     * Documentation:
     *
     * @see http://agile-ui.readthedocs.io/en/latest/js.html
     *
     * @param string|bool|null $when     Event when chain will be executed
     * @param JsExpression     $action   JavaScript action
     * @param string|View|null $selector If you wish to override jQuery($selector)
     *
     * @return Jquery
     */
    public function js($when = null, $action = null, $selector = null)
    {
        $chain = new Jquery($selector ?: $this);

        // Substitute $when to make it better work as a array key
        if ($when === true) {
            $this->_js_actions[$when][] = $chain;

            if ($action) {
                $this->_js_actions[$when][] = $action;
            }

            return $chain;
        }

        if ($when === false || $when === null) {
            return $chain;
        }

        // next - binding on a specific event
        $action = (new Jquery($this))
            ->bind($when, new JsFunction([$chain, $action]));

        $this->_js_actions[$when][] = $action;

        return $chain;
    }

    /**
     * Create Vue.js instance.
     * Vue.js instance can be create from Atk4\Ui\View.
     *
     * Component managed and defined by atk does not need componentDefinition variable name
     * because these are already loaded within the atk js namespace.
     * When creating your own component externally, you must supply the variable name holding
     * your Vue component definition. This definition must be also accessible within the window javascript
     * object. This way, you do not need to load Vue js file since it has already being include within
     * atkjs-ui.js build.
     *
     * If the external component use other components, it is possible to register them using
     * vueService getVue() method. This method return the current Vue object.
     * ex: atk.vueService.getVue().component('external_component', externalComponent). This is the same
     * as Vue.component() method.
     *
     * @param string           $component           The component name;
     * @param array            $initData            The component properties passed as the initData prop.
     *                                              This is the initial data pass to your main component via the initData bind property
     *                                              of the vue component instance created via the vueService.
     * @param string|null      $componentDefinition The name of the js var holding a component definition object.
     *                                              This var must be defined and accessible in window object. window['var_name']
     * @param string|View|null $selector            the selector for creating the base root object in Vue
     *
     * @return $this
     */
    public function vue($component, $initData = [], $componentDefinition = null, $selector = null)
    {
        if (!$selector) {
            $selector = '#' . $this->name;
        }

        if ($componentDefinition) {
            $chain = (new JsVueService())->createVue($selector, $component, $componentDefinition, $initData);
        } else {
            $chain = (new JsVueService())->createAtkVue($selector, $component, $initData);
        }

        $this->_js_actions[true][] = $chain;

        return $this;
    }

    /**
     * Emit an event on atkEvent bus.
     *
     * example of adding a listener on for an emit event.
     *
     *      atk.eventBus.on('eventName', (data) => {
     *          console.log(data)
     *      });
     *
     * Note: In order to make sure your event is unique within atk, you can
     * use the view name in it.
     *    $this->jsEmitEvent($this->name . '-my-event', $data)
     */
    public function jsEmitEvent(string $eventName, array $eventData = []): JsChain
    {
        return (new JsChain('atk.eventBus'))->emit($eventName, $eventData);
    }

    /**
     * Get Local and Session web storage associated with this view.
     * Web storage can be retrieve using a $view->jsReload() request.
     *
     * @return mixed
     */
    public function jsGetStoreData()
    {
        $data['local'] = json_decode($_GET[$this->name . '_local_store'] ?? $_POST[$this->name . '_local_store'] ?? 'null', true, 512, \JSON_THROW_ON_ERROR);
        $data['session'] = json_decode($_GET[$this->name . '_session_store'] ?? $_POST[$this->name . '_session_store'] ?? 'null', true, 512, \JSON_THROW_ON_ERROR);

        return $data;
    }

    /**
     * Clear Web storage data associated with this view.
     *
     * @return mixed
     */
    public function jsClearStoreData(bool $useSession = false)
    {
        $type = $useSession ? 'session' : 'local';

        if (!$name = $this->name) {
            throw new Exception('View property name needs to be set.');
        }

        return (new JsChain('atk.dataService'))->clearData($name, $type);
    }

    /**
     * Add Web storage for this specific view.
     * Data will be store as json value where key name
     * will be the name of this view.
     *
     * Data added to web storage is merge against previous value.
     *  $v->jsAddStoreData(['args' => ['path' => '.']]);
     *  $v->jsAddStoreData(['args' => ['path' => '/'], 'fields' => ['name' => 'test']]]);
     *
     *  Final store value will be: ['args' => ['path' => '/'], 'fields' => ['name' => 'test']];
     *
     * @return mixed
     */
    public function jsAddStoreData(array $data, bool $useSession = false)
    {
        $type = $useSession ? 'session' : 'local';

        if (!$name = $this->name) {
            throw new Exception('View property name needs to be set.');
        }

        return (new JsChain('atk.dataService'))->addJsonData($name, json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR), $type);
    }

    /**
     * Returns JS for reloading View.
     *
     * @param array             $args
     * @param JsExpression|null $afterSuccess
     * @param array             $apiConfig
     *
     * @return JsReload
     */
    public function jsReload($args = [], $afterSuccess = null, $apiConfig = [])
    {
        return new JsReload($this, $args, $afterSuccess, $apiConfig);
    }

    /**
     * Views in Agile Toolkit can assign javascript actions to themselves. This
     * is done by calling $view->js() or $view->on().
     *
     * on() method is similar to jQuery on() method.
     *
     * on(event, [selector,] action)
     *
     * Method on() also returns a chain, that will correspond affected element.
     * Here are some ways to use on();
     *
     * $button->on('click', $view->js()->hide());
     *
     *   // clicking on button will make the $view dissapear
     *
     * $view->on('click', 'a[data=clickable]')->parent()->hide();
     *
     *   // clicking on <a class="clickable"> will make it's parent dissapear
     *
     * Finally, it's also possible to use PHP closure as an action:
     *
     * $view->on('click', 'a', function($js, $data){
     *   if (!$data['clickable']) {
     *      return new JsExpression('alert([])', ['This record is not clickable'])
     *   }
     *   return $js->parent()->hide();
     * });
     *
     * For more information on how this works, see documentation:
     *
     * @see http://agile-ui.readthedocs.io/en/latest/js.html
     *
     * @param string                            $event    JavaScript event
     * @param string|View|JsExpressionable|null $selector Optional jQuery-style selector
     * @param mixed                             $action   code to execute
     * @param array                             $defaults Options
     *
     * @return Jquery
     */
    public function on($event, $selector = null, $action = null, $defaults = null)
    {
        $event_stmts = [];

        $cb = null;
        $actions = [];
        $chain = new Jquery();
        $actions[] = $chain;

        // second argument may be omitted
        if (!is_string($selector) && ($action === null || is_array($action))) {
            $defaults = $action;
            $action = $selector;
            $selector = null;
        }

        // check for arguments.
        $arguments = $defaults['args'] ?? [];
        if ($defaults === null) {
            $defaults = [];
        }

        // all non-key items of defaults are actually arguments
        foreach ($defaults as $key => $value) {
            if (is_numeric($key)) {
                $arguments[] = $value;
                unset($defaults[$key]);
            }
        }

        // set event stmts to use preventDefault and/or stopPropagation
        $event_stmts['preventDefault'] = $defaults['preventDefault'] ?? true;
        $event_stmts['stopPropagation'] = $defaults['stopPropagation'] ?? true;

        // Dealing with callback action.
        if ($action instanceof \Closure || (is_array($action) && ($action[0] ?? null) instanceof \Closure)) {
            if (is_array($action)) {
                $urlData = $action;
                unset($urlData[0]);
                foreach ($urlData as $a) {
                    $actions[] = $a;
                }
                $action = $action[0];
            }

            // create callback, that will include event as part of the full name
            $this->add($cb = new JsCallback(), ['desired_name' => $event]);
            if ($defaults['apiConfig'] ?? null) {
                $cb->apiConfig = $defaults['apiConfig'];
            }

            $cb->set(function () use ($action) {
                $args = func_get_args();
                $args[0] = new Jquery(new JsExpression('this'));

                return $action(...$args);
            }, $arguments);

            $actions[] = $cb;
        } elseif ($action instanceof UserAction\ExecutorInterface || $action instanceof Model\UserAction) {
            // Setup UserAction executor.
            $ex = $action instanceof Model\UserAction ? $this->getExecutorFactory()->create($action, $this) : $action;
            if ($ex instanceof self && $ex instanceof UserAction\JsExecutorInterface) {
                if (isset($arguments['id'])) {
                    $arguments[$ex->name] = $arguments['id'];
                    unset($arguments['id']);
                } elseif (isset($arguments[0])) {
                    // if id is not specify we assume arguments[0] is the model id.
                    $arguments[$ex->name] = $arguments[0];
                    unset($arguments[0]);
                }
                $ex_actions = $ex->jsExecute($arguments);
                if (is_array($ex_actions)) {
                    $actions = $ex_actions;
                } else {
                    $actions[] = $ex_actions;
                }
                $ex->executeModelAction();
            } elseif ($ex instanceof UserAction\JsCallbackExecutor) {
                if ($conf = $ex->getAction()->getConfirmation()) {
                    $defaults['confirm'] = $conf;
                }
                if ($defaults['apiConfig'] ?? null) {
                    $ex->apiConfig = $defaults['apiConfig'];
                }
                $actions[] = $ex;
                $ex->executeModelAction($arguments);
            } else {
                throw new Exception('Executor must be of type UserAction\JsCallbackExecutor or extend View and implement UserAction\JsExecutorInterface');
            }
        } elseif (is_array($action)) {
            $actions = array_merge($actions, $action);
        } elseif ($action) {
            $actions[] = $action;
        }

        // Do we need confirm action.
        if ($defaults['confirm'] ?? null) {
            array_unshift($event_stmts, new JsExpression('$.atkConfirm({message:[confirm], onApprove: [action], options: {button:{ok:[ok], cancel:[cancel]}}, context:this})', [
                'confirm' => $defaults['confirm'],
                'action' => new JsFunction($actions),
                'ok' => $defaults['ok'] ?? 'Ok',
                'cancel' => $defaults['cancel'] ?? 'Cancel',
            ]));
        } else {
            $event_stmts = array_merge($event_stmts, $actions);
        }

        $event_function = new JsFunction($event_stmts);

        if ($selector) {
            $this->js(true)->on($event, $selector, $event_function);
        } else {
            $this->js(true)->on($event, $event_function);
        }

        return $chain;
    }

    /**
     * Convert View into a value in case it happens to be inside our json_encode (as argument to JsChain).
     */
    public function jsRender(): string
    {
        if (!$this->_initialized) {
            throw new Exception('Render tree must be initialized before materializing JsChains.');
        }

        return json_encode('#' . $this->id, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    /**
     * Return rendered js actions as a string.
     */
    public function getJsRenderActions(): string
    {
        $actions = [];

        foreach ($this->_js_actions as $eventActions) {
            foreach ($eventActions as $action) {
                $actions[] = $action->jsRender();
            }
        }

        return implode(';', $actions);
    }

    /**
     * Get JavaScript objects from this render tree.
     *
     * @return string
     */
    public function getJs(bool $forceReturn = false)
    {
        $actions = [];

        foreach ($this->_js_actions as $eventActions) {
            foreach ($eventActions as $action) {
                $actions[] = $action;
            }
        }

        if (!$actions) {
            return '';
        }

        $actions['indent'] = '';

        if (!$forceReturn && $this->issetApp() && $this->getApp()->hasMethod('jsReady')) {
            $this->getApp()->jsReady($actions);

            return '';
        }

        // delegate $action rendering in hosting app if exist.
        if ($this->issetApp() && $this->getApp()->hasMethod('getViewJS')) {
            return $this->getApp()->getViewJS($actions);
        }

        $ready = new JsFunction($actions);

        return "<script>\n" .
               (new Jquery($ready))->jsRender() .
               '</script>';
    }

    // }}}
}
