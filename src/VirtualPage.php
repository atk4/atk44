<?php

declare(strict_types=1);

namespace Atk4\Ui;

/**
 * Virtual page normally does not render, yet it has it's own trigger and will respond
 * to the trigger in a number of useful way depending on trigger's argument:.
 *
 *  - cut = will only output HTML of this VirtualPage and it's sub-elements
 *  - popup = will add VirtualPage directly into body, ideal for pop-up windows
 *  - normal = will get rid of all the normal components inside Layout's content replacing them
 *      the render of this page. Will preserve menus and top bar but that's it.
 */
class VirtualPage extends View
{
    /** @var Callback */
    public $cb;

    /** @var string specify custom callback trigger for the URL (see Callback::$urlTrigger) */
    public $urlTrigger;

    /** @var string UI container class */
    public $ui = 'container';

    /**
     * Initialization.
     */
    protected function init(): void
    {
        parent::init();

        $this->cb = $this->add([Callback::class, 'urlTrigger' => $this->urlTrigger ?: $this->name]);
    }

    /**
     * Set callback function of virtual page.
     *
     * @param \Closure $fx
     * @param array    $args arguments for \Closure
     *
     * @return $this
     */
    public function set($fx = null, $args = [])
    {
        if (!$fx || !$fx instanceof \Closure) {
            throw new Exception('Virtual page requires a Closure.');
        }

        $this->cb->set($fx, array_merge([$this], $args));

        return $this;
    }

    /**
     * Is virtual page active?
     */
    public function isTriggered(): bool
    {
        return $this->cb->isTriggered();
    }

    /**
     * Returns URL which you can load directly in the browser location, open in a new tab,
     * new window or inside iframe. This URL will contain HTML for a new page.
     *
     * @param string $mode
     *
     * @return string
     */
    public function getUrl($mode = 'callback')
    {
        return $this->cb->getUrl($mode);
    }

    /**
     * Return URL that is designed to be loaded from inside JavaScript and contain JSON code.
     * This is useful for dynamically loaded Modal, Tab or Loader.
     *
     * @param string $mode
     *
     * @return string
     */
    public function getJsUrl($mode = 'callback')
    {
        return $this->cb->getJsUrl($mode);
    }

    /**
     * VirtualPage is not rendered normally. It's invisible. Only when
     * it is triggered, it will exclusively output it's content.
     */
    public function getHtml()
    {
        if ($this->cb->isTriggered() && !$this->cb->canTerminate()) {
            return parent::getHtml();
        }

        if ($this->cb->canTerminate()) {
            if ($mode = $this->cb->getTriggeredValue()) {
                // special treatment for popup
                if ($mode === 'popup') {
                    $this->getApp()->html->template->set('title', $this->getApp()->title);
                    $this->getApp()->html->template->dangerouslySetHtml('Content', parent::getHtml());
                    $this->getApp()->html->template->dangerouslyAppendHtml('HEAD', $this->getJs());

                    $this->getApp()->terminateHtml($this->getApp()->html->template);
                }

                // render and terminate
                if (isset($_GET['__atk_json'])) {
                    $this->getApp()->terminateJson($this);
                }

                if (isset($_GET['__atk_tab'])) {
                    $this->getApp()->terminateHtml($this->renderToTab());
                }

                // do not terminate if callback supplied (no cutting)
                if ($mode !== 'callback') {
                    $this->getApp()->terminateHtml($this);
                }
            }

            // Remove all elements from inside the Content
            foreach ($this->getApp()->layout->elements as $key => $view) {
                if ($view instanceof View && $view->region === 'Content') {
                    unset($this->getApp()->layout->elements[$key]);
                }
            }

            // Prepare modals in order to include them in VirtualPage.
            $modalHtml = '';
            foreach ($this->getApp()->html !== null ? $this->getApp()->html->elements : [] as $view) {
                if ($view instanceof Modal) {
                    $modalHtml .= $view->getHtml();
                    $this->getApp()->layout->_js_actions = array_merge($this->getApp()->layout->_js_actions, $view->_js_actions);
                }
            }

            $this->getApp()->layout->template->dangerouslySetHtml('Content', parent::getHtml());
            $this->getApp()->layout->_js_actions = array_merge($this->getApp()->layout->_js_actions, $this->_js_actions);

            $this->getApp()->html->template->dangerouslySetHtml('Content', $this->getApp()->layout->template->renderToHtml());
            $this->getApp()->html->template->dangerouslySetHtml('Modals', $modalHtml);

            $this->getApp()->html->template->dangerouslyAppendHtml('HEAD', $this->getApp()->layout->getJs());

            $this->getApp()->terminateHtml($this->getApp()->html->template);
        }
    }
}
