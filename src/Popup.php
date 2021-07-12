<?php

declare(strict_types=1);

namespace Atk4\Ui;

/**
 * Implement popup view.
 *
 * Popup are views that will be display when triggered by another view.
 *
 * Popup can add content statically or dynamically via a callback.
 *
 * When adding a popup to the page, you need to specify it's trigger element
 * and the event needed on the trigger element in order to display the popup.
 */
class Popup extends View
{
    public $ui = 'popup';

    /**
     * The view activating the popup.
     * Usually the view where popup is attached to,
     * unless target is supply.
     *
     * @var View|string|null object view or a string id
     */
    public $triggerBy;

    /**
     * Js event that trigger the popup.
     *
     * @var string
     */
    public $triggerOn;

    /**
     * Default position of the popup in relation to target element.
     *
     * @var string
     */
    public $position = 'top left';

    /**
     * When set to false, target is the triggerBy element.
     * Otherwise, you can supply a View object where popup will be shown.
     *
     * @var View|bool
     */
    public $target = false;

    /**
     * Popup options as defined in semantic-ui popup module.
     *
     * @var array
     */
    public $popOptions = [];

    /**
     * The callback use to generate dynamic content.
     *
     * @var Callback|null
     */
    public $cb;

    /**
     * The dynamic View to load inside the popup
     * when dynamic content is use.
     *
     * @var View|array
     */
    public $dynamicContent = [View::class];

    /**
     * Whether or not dynamic content is cache.
     * If cache is on, will retrieve content only the first time
     * popup is requrired.
     *
     * @var bool
     */
    public $useCache = false;

    /**
     * Min width for a dynamic popup.
     *
     * @var string
     */
    public $minWidth; //'120px';

    /**
     * Min height for a dynamic popup.
     *
     * @var string
     */
    public $minHeight; //'60px';

    /**
     * Whether or not the click event triggering popup
     * should stop event propagation.
     *
     * Ex: when Popup is located inside a sortable grid header.
     * Set this options to true in order to activate just the popup
     * and stop sort action.
     *
     * @var bool
     */
    public $stopClickEvent = false;

    public function __construct($triggerBy = null)
    {
        if (is_object($triggerBy)) {
            $this->triggerBy = $triggerBy;
        } else {
            parent::__construct($triggerBy);
        }
    }

    protected function init(): void
    {
        parent::init();

        if (
            $this->getOwner() instanceof Item
            || $this->getOwner() instanceof Menu
            || $this->getOwner() instanceof Dropdown
            || $this->getOwner() instanceof Button
        ) {
            throw (new Exception('Although it may be tempting to add pop-up into Button/Menu/Item, this may cause some random issues. Add elsewhere and use "triggerBy"'))
                ->addMoreInfo('owner', $this->getOwner());
        }

        if (
            ($this->triggerBy instanceof Item
            || $this->triggerBy instanceof Menu
            || $this->triggerBy instanceof Dropdown) && $this->triggerOn === null
        ) {
            $this->triggerOn = 'hover';
        }

        if (
            $this->triggerBy instanceof Button && $this->triggerOn === null
        ) {
            $this->triggerOn = 'click';
        }

        $this->popOptions = array_merge($this->popOptions, [
            'popup' => '#' . $this->name,
            'on' => $this->triggerOn,
            'position' => $this->position,
            'target' => ($this->target) ? '#' . $this->target->name : false,
        ]);
    }

    /**
     * Set callback for loading content dynamically.
     * Callback will receive a view attach to this popup
     * for adding content to it.
     *
     * @param \Closure $fx
     */
    public function set($fx = null, $ignore = null)
    {
        if (!($fx instanceof \Closure)) {
            throw new Exception('Need to pass a function to Popup::set()');
        } elseif (func_num_args() > 1) {
            throw new Exception('Only one argument is needed by Popup::set()');
        }

        $this->cb = Callback::addTo($this);

        if (!$this->minWidth) {
            $this->minWidth = '120px';
        }

        if (!$this->minHeight) {
            $this->minHeight = '60px';
        }

        // create content view to pass to callback.
        $content = $this->add($this->dynamicContent);
        $this->cb->set($fx, [$content]);
        // only render our content view.
        // PopupService will replace content with this one.
        $this->cb->terminateJson($content);

        return $this;
    }

    /**
     * Set triggerBy.
     *
     * @return $this
     */
    public function setTriggerBy($trigger)
    {
        $this->triggerBy = $trigger;

        return $this;
    }

    /**
     * Allow to pass a target selector by name, i.e. a css class name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setTargetByName($name)
    {
        $this->popOptions['target'] = $name;

        return $this;
    }

    /**
     * Whether popup stay open when user hover on it or not.
     *
     * @return $this
     */
    public function setHoverable(bool $isOverable = true)
    {
        $this->popOptions['hoverable'] = $isOverable;

        return $this;
    }

    /**
     * Set a popup options as defined in semantic-ui popup module.
     *
     * @return $this
     */
    public function setOption($name, $option)
    {
        $this->popOptions[$name] = $option;

        return $this;
    }

    /**
     * Setting options using using an array.
     *
     * @return $this
     */
    public function setOptions($options)
    {
        $this->popOptions = array_merge($this->popOptions, $options);

        return $this;
    }

    /**
     * Return js action need to display popup.
     * When a grid is reloading, this method can be call
     * in order to display the popup once again.
     *
     * @return Jquery
     */
    public function jsPopup()
    {
        $name = $this->triggerBy;
        if (!is_string($this->triggerBy)) {
            $name = '#' . $this->triggerBy->name;
            if ($this->triggerBy instanceof Form\Control) {
                $name = '#' . $this->triggerBy->name . '_input';
            }
        }
        $chain = new Jquery($name);
        $chain->popup($this->popOptions);
        if ($this->stopClickEvent) {
            $chain->on('click', new JsExpression('function(e){e.stopPropagation();}'));
        }

        return $chain;
    }

    protected function renderView(): void
    {
        if ($this->triggerBy) {
            $this->js(true, $this->jsPopup());
        }

        if ($this->cb) {
            $this->setAttr('data-uri', $this->cb->getJsUrl());
            $this->setAttr('data-cache', $this->useCache ? 'true' : 'false');
        }

        if ($this->minWidth) {
            $this->setStyle('min-width', $this->minWidth);
        }

        if ($this->minHeight) {
            $this->setStyle('min-height', $this->minHeight);
        }
        //$this->setStyle(['min-width' => $this->minWidth, 'min-height' => $this->minHeight]);

        parent::renderView();
    }
}
