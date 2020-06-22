<?php

declare(strict_types=1);

namespace atk4\ui;

use atk4\core\AppScopeTrait;
use atk4\core\ContainerTrait;
use atk4\core\DIContainerTrait;
use atk4\core\Exception;
use atk4\core\FactoryTrait;
use atk4\core\InitializerTrait;
use atk4\core\StaticAddToTrait;
use atk4\core\TrackableTrait;
use atk4\data\Model;
use atk4\data\Persistence\Static_;

/**
 * Implements a most core view, which all of the other components descend
 * form.
 *
 * @property View $owner
 */
class View implements jsExpressionable
{
    use ContainerTrait {
        add as _add;
    }
    use InitializerTrait {
        init as _init;
    }
    use TrackableTrait;
    use AppScopeTrait;
    use FactoryTrait;
    use DIContainerTrait {
        setMissingProperty as _setMissingProperty;
    }
    use StaticAddToTrait;

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
     * Default name of the element.
     *
     * @var string
     */
    public $defaultName = 'atk';

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
     * @var Template
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

    /**
     * If add() method is called, but current view is not part of render tree yet,
     * then arguments to add() are simply stored in this array. When the view is
     * initialized by calling init() or adding into App or another initialized View,
     * then add() will be re-invoked with the contents of this array.
     *
     * @var array
     */
    protected $_add_later = [];

    /**
     * will be set to true after rendered. This is so that we don't render view twice.
     *
     * @var bool
     */
    protected $_rendered = false;

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
    public function setModel(Model $m)
    {
        $this->model = $m;

        return $m;
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
        return $this->setModel(new Model(new Static_($data)), $fields);
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

        $this->_setMissingProperty($propertyName, $value);
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
    public function init(): void
    {
        if ($this->name === null) {
            $this->name = $this->defaultName;
        }

        if ($this->id === null) {
            $this->id = $this->name;
        }

        // initialize
        $this->_init();

        if (!$this->app) {
            $this->initDefaultApp();
        }

        if ($this->region && !$this->template && !$this->defaultTemplate && $this->owner && $this->owner->template) {
            $this->template = $this->owner->template->cloneRegion($this->region);

            $this->owner->template->del($this->region);
        } else {
            // set up template
            if (is_string($this->defaultTemplate) && $this->template === null) {
                $this->template = $this->app->loadTemplate($this->defaultTemplate);
            }

            if (!$this->region) {
                $this->region = 'Content';
            }
        }

        if ($this->template && !isset($this->template->app) && isset($this->app)) {
            $this->template->app = $this->app;
        }

        // add default objects
        foreach ($this->_add_later as list($object, $region)) {
            $this->add($object, $region);
        }

        $this->_add_later = [];

        // allow for injecting the model with a seed
        if ($this->model) {
            $this->setModel($this->model);
        }
    }

    /**
     * For the absence of the application, we would add a very
     * simple one.
     */
    protected function initDefaultApp()
    {
        $this->app = new App([
            'skin' => $this->skin,
            'catch_exceptions' => false,
            'always_run' => false,
            'catch_runaway_callbacks' => false,
        ]);
        $this->app->init();
    }

    /**
     * In addition to adding a child object, sets up it's template
     * and associate it's output with the region in our template.
     *
     * @param View              $object
     * @param string|array|null $region
     *
     * @return View
     */
    public function add($object, $region = null)
    {
        if (func_num_args() > 2) { // prevent bad usage
            throw new \Error('Too many method arguments');
        }

        if ($this->_rendered) {
            throw new Exception('You cannot add anything into the view after it was rendered');
        }

        if (!is_object($object)) {
            // for BC do not throw
            // later consider to accept strictly objects only
            $object = self::addToWithClUnsafe($this, $object, [], true);
        }

        if (!$this->app) {
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
        $this->_add($object, $args);

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
        if (!isset($object->owner)) {
            return;
        }

        if ($object->owner instanceof $class) {
            return $object->owner;
        }

        return $this->getClosestOwner($object->owner, $class);
    }

    // }}}

    // {{{ Manipulating classes and view properties

    /**
     * Override this method without compatibility with parent, if you wish
     * to set your own things your own way for your view.
     *
     * @param string|array $arg1
     * @param string|null  $arg2
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

    // {{{ Rendering

    /**
     * View-specific rendering stuff. Feel free to replace this method with
     * your own. View::renderView contains some logic that integrates with
     * semanticUI.
     *
     * NOTE: maybe in the future, SemanticUI-related stuff needs to go into
     * a separate class.
     */
    public function renderView()
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
                $tmp[] = $attr . '="' . $this->app->encodeAttribute($val) . '"';
            }
            $this->template->setHTML('attributes', implode(' ', $tmp));
        }
    }

    /**
     * Recursively render all children, placing their
     * output in our template.
     */
    public function recursiveRender()
    {
        foreach ($this->elements as $view) {
            if (!$view instanceof self) {
                continue;
            }

            $this->template->appendHTML($view->region, $view->getHTML());

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
    public function renderAll()
    {
        if (!$this->_initialized) {
            $this->init();
        }

        if (!$this->_rendered) {
            $this->renderView();

            $this->recursiveRender();
            $this->_rendered = true;
        }
    }

    /**
     * This method is for those cases when developer want to simply render his
     * view and grab HTML himself.
     *
     * @param bool $force_echo
     *
     * @return string
     */
    public function render($force_echo = true)
    {
        $this->renderAll();

        return
            $this->getJS($force_echo) .
            $this->template->render();
    }

    /**
     * This method is to render view to place inside a Fomantic-UI Tab.
     */
    public function renderTab()
    {
        $this->renderAll();

        return [
            'atkjs' => $this->getJsRenderActions(),
            'html' => $this->template->render(),
        ];
    }

    /**
     * Render View using json format.
     *
     * @param bool   $force_echo
     * @param string $region     a specific template region to render
     *
     * @return string
     */
    public function renderJSON($force_echo = true, $region = null)
    {
        $this->renderAll();

        return json_encode([
            'success' => true,
            'message' => 'Success',
            'atkjs' => $this->getJS($force_echo),
            'html' => $this->template->render($region),
            'id' => $this->name,
        ]);
    }

    /**
     * Created for recursive rendering or when you want to only get HTML of
     * this object (not javascript).
     *
     * @return string
     */
    public function getHTML()
    {
        if (isset($_GET['__atk_reload']) && $_GET['__atk_reload'] === $this->name) {
            $this->app->terminateJSON($this);
        }

        $this->renderAll();

        return $this->template->render();
    }

    // }}}

    // {{{ JavaScript integration

    /**
     * Views in Agile UI can assign javascript actions to themselves. This
     * is done by calling $view->js() method which returns instance of jsChain
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
     * action = false will still return jsChain but will not bind it.
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
     * @param jsExpression     $action   JavaScript action
     * @param string           $selector If you wish to override jQuery($selector)
     *
     * @return jQuery
     */
    public function js($when = null, $action = null, $selector = null)
    {
        $chain = new jQuery($selector ?: $this);

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
        $action = (new jQuery($this))
            ->bind($when, new jsFunction([$chain, $action]));

        $this->_js_actions[$when][] = $action;

        return $chain;
    }

    /**
     * Create Vue.js instance.
     * Vue.js instance can be create from atk4\ui\View.
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
     * @param string      $component           The component name;
     * @param array       $initData            The component properties passed as the initData prop.
     *                                         This is the initial data pass to your main component via the initData bind property
     *                                         of the vue component instance created via the vueService.
     * @param string|null $componentDefinition The name of the js var holding a component definition object.
     *                                         This var must be defined and accessible in window object. window['var_name']
     * @param string      $selector            the selector for creating the base root object in Vue
     *
     * @return $this
     */
    public function vue($component, $initData = [], $componentDefinition = null, $selector = null)
    {
        if (!$selector) {
            $selector = '#' . $this->name;
        }

        if ($componentDefinition) {
            $chain = (new jsVueService())->createVue($selector, $component, $componentDefinition, $initData);
        } else {
            $chain = (new jsVueService())->createAtkVue($selector, $component, $initData);
        }

        $this->_js_actions[true][] = $chain;

        return $this;
    }

    /**
     * Emit an event on the Vue event bus.
     * vueService has a dedicated Vue instance for registering
     * event that allow communication between external view like button,
     * or even separate vue component, in order to communicate to each other.
     *
     * Once a component is set for listening to a particular event,
     * you can emit the event using this function.
     *
     * Adding a listener is generally done via the created component method.
     *
     * example of adding a listener inside the created method.
     *
     *      atk.vueService.eventBus.$on('eventName', (data) => {
     *          // make sure we are talking to the right component.
     *          if (this.$parent.$el.id === data.id) {
     *              this.doSomething();
     *          }
     *      });
     *
     * @param string $eventName the event name the will be emit
     * @param array  $eventData $eventData   The data passed with the event
     *
     * @return mixed
     */
    public function jsVueEmit($eventName, $eventData = [])
    {
        // adding this view id to data.
        // Usually, you would check if the event is emit for the right component.
        if (!isset($eventData['id'])) {
            $eventData['id'] = $this->name;
        }

        return (new jsVueService())->emitEvent($eventName, $eventData);
    }

    /**
     * Get Local and Session web storage associated with this view.
     * Web storage can be retrieve using a $view->jsReload() request.
     *
     * @return mixed
     */
    public function jsGetStoreData()
    {
        $data['local'] = json_decode($_GET[$this->name . '_local_store'] ?? $_POST[$this->name . '_local_store'] ?? 'null', true);
        $data['session'] = json_decode($_GET[$this->name . '_session_store'] ?? $_POST[$this->name . '_session_store'] ?? 'null', true);

        return $data;
    }

    /**
     * Clear Web storage data associated with this view.
     *
     * @param bool $useSession
     *
     * @return mixed
     */
    public function jsClearStoreData($useSession = false)
    {
        $type = $useSession ? 'session' : 'local';

        if (!$name = $this->name) {
            throw new \atk4\ui\Exception('View property name needs to be set.');
        }

        return (new jsChain('atk.dataService'))->clearData($name, $type);
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
     * @param bool $useSession
     *
     * @return mixed
     */
    public function jsAddStoreData(array $data, $useSession = false)
    {
        $type = $useSession ? 'session' : 'local';

        if (!$name = $this->name) {
            throw new \atk4\ui\Exception('View property name needs to be set.');
        }

        return (new jsChain('atk.dataService'))->addJsonData($name, json_encode($data), $type);
    }

    /**
     * Returns JS for reloading View.
     *
     * @param array             $args
     * @param jsExpression|null $afterSuccess
     * @param array             $apiConfig
     *
     * @return jsReload
     */
    public function jsReload($args = [], $afterSuccess = null, $apiConfig = [])
    {
        return new jsReload($this, $args, $afterSuccess, $apiConfig);
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
     *      return new jsExpression('alert([])', ['This record is not clickable'])
     *   }
     *   return $js->parent()->hide();
     * });
     *
     * For more information on how this works, see documentation:
     *
     * @see http://agile-ui.readthedocs.io/en/latest/js.html
     *
     * @param string                            $event    JavaScript event
     * @param string                            $selector Optional jQuery-style selector
     * @param jsChain|callable|Model\UserAction $action   code to execute or \atk4\Data\UserAction
     * @param array                             $defaults Options
     *
     * @return jQuery
     */
    public function on($event, $selector = null, $action = null, $defaults = null)
    {
        $event_stmts = [];

        $cb = null;
        $actions = [];
        $chain = new jQuery();
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
        if (is_callable($action) || (is_array($action) && isset($action[0]) && is_callable($action[0]))) {
            if (is_array($action)) {
                $urlData = $action;
                unset($urlData[0]);
                foreach ($urlData as $a) {
                    $actions[] = $a;
                }
                $action = $action[0];
            }

            // create callback, that will include event as part of the full name
            $this->_add($cb = new jsCallback(), ['desired_name' => $event]);
            if ($defaults['apiConfig'] ?? null) {
                $cb->apiConfig = $defaults['apiConfig'];
            }

            $cb->set(function () use ($action) {
                $args = func_get_args();
                $args[0] = new jQuery(new jsExpression('this'));

                return call_user_func_array($action, $args);
            }, $arguments);

            $actions[] = $cb;
        } elseif ($action instanceof Model\UserAction) {
            // Setup UserAction executor.
            if (isset($action->ui['executor'])) {
                $class = $action->ui['executor'];
            } elseif (isset($defaults['executor'])) {
                $class = $defaults['executor'];
            } elseif (!$action->args && !$action->fields && !$action->preview) {
                $class = [UserAction\JsCallbackExecutor::class];
            } else {
                $class = [UserAction\ModalExecutor::class];
            }
            $ex = $this->factory($class);
            if ($ex instanceof self && $ex instanceof UserAction\JsExecutorInterface) {
                //Executor may already had been add to layout. Like in CardDeck.
                if (!isset($this->app->html->elements[$ex->short_name])) {
                    $ex = $this->app->html->add($ex, 'Modals')->setAction($action);
                }
                if (isset($arguments[0])) {
                    $arguments[$ex->name] = $arguments[0];
                }
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
            } elseif ($ex instanceof UserAction\JsCallbackExecutor) {
                $ex = $this->add($ex)->setAction($action, $arguments);
                if ($conf = $action->getConfirmation()) {
                    $defaults['confirm'] = $conf;
                }
                if ($defaults['apiConfig'] ?? null) {
                    $ex->apiConfig = $defaults['apiConfig'];
                }
                $actions[] = $ex;
            } else {
                throw new \atk4\ui\Exception('Executor must be of type UserAction\JsCallbackExecutor or extend View and implement UserAction\JsExecutorInterface');
            }
        } elseif (is_array($action)) {
            $actions = array_merge($actions, $action);
        } elseif ($action) {
            $actions[] = $action;
        }

        // Do we need confirm action.
        if ($defaults['confirm'] ?? null) {
            array_unshift($event_stmts, new jsExpression('$.atkConfirm({message:[confirm], onApprove: [action], options: {button:{ok:[ok], cancel:[cancel]}}, context:this})', [
                'confirm' => $defaults['confirm'],
                'action' => new jsFunction($actions),
                'ok' => $defaults['ok'] ?? 'Ok',
                'cancel' => $defaults['cancel'] ?? 'Cancel',
            ]));
        } else {
            $event_stmts = array_merge($event_stmts, $actions);
        }

        $event_function = new jsFunction($event_stmts);

        if ($selector) {
            $this->js(true)->on($event, $selector, $event_function);
        } else {
            $this->js(true)->on($event, $event_function);
        }

        return $chain;
    }

    /**
     * Convert View into a value in case it happens to be inside our json_encode (as argument to jsChain).
     *
     * @return string
     */
    public function jsRender()
    {
        if (!$this->_initialized) {
            throw new Exception('Render tree must be initialized before materializing jsChains.');
        }

        return json_encode('#' . $this->id);
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
     * @param bool $force_echo
     *
     * @return string
     */
    public function getJS($force_echo = false)
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

        if (!$force_echo && $this->app && $this->app->hasMethod('jsReady')) {
            $this->app->jsReady($actions);

            return '';
        }

        // delegate $action rendering in hosting app if exist.
        if ($this->app && $this->app->hasMethod('getViewJS')) {
            return $this->app->getViewJS($actions);
        }

        $ready = new jsFunction($actions);

        return "<script>\n" .
            (new jQuery($ready))->jsRender() .
            '</script>';
    }

    // }}}

    // {{{ Sticky URLs

    /** @var string[] stickyGet arguments */
    public $stickyArgs = [];

    /** @var string[] Cached stickyGet arguments */
    public $_stickyArgsCached;

    /**
     * Build an URL which this view can use for js call-backs. It should
     * be guaranteed that requesting returned URL would at some point call
     * $this->init().
     *
     * @param array $page
     *
     * @return string
     */
    public function jsURL($page = [])
    {
        return $this->app->jsURL($page, false, array_merge($this->_getStickyArgs($this->name), $this->stickyArgs));
    }

    /**
     * Build an URL which this view can use for call-backs. It should
     * be guaranteed that requesting returned URL would at some point call
     * $this->init().
     *
     * @param string|array $page URL as string or array with page name as first element and other GET arguments
     *
     * @return string
     */
    public function url($page = [])
    {
        return $this->app->url($page, false, array_merge($this->_getStickyArgs($this->name), $this->stickyArgs));
    }

    /**
     * Needed for tracking which view in a render tree called url().
     *
     * @internal
     */
    public $_triggerBy;

    /**
     * Get sticky arguments defined by the view and parents (including API).
     *
     * @param string $triggerBy If exception occurs, will know which view called url()
     *
     * @return array
     */
    public function _getStickyArgs($triggerBy)
    {
        $this->_triggerBy = $triggerBy;
        if ($this->owner && $this->owner instanceof self) {
            $this->_stickyArgsCached = array_merge($this->owner->_getStickyArgs($triggerBy), $this->stickyArgs);
        } else {
            $this->_stickyArgsCached = [];
        }

        return $this->_stickyArgsCached;
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
}
