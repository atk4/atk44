<?php

declare(strict_types=1);

namespace Atk4\Ui;

/**
 * This class add modal dialog to a page.
 *
 * Modal are added to the layout but their content is hidden by default.
 * $modal->show() is the triggered needed to actually display the modal.
 *
 * Modal can be use as a regular view, simply by adding other view to it.
 *  Message::addTo($modal, ['title'=>'Welcome to Agile Toolkit'])->text('Your text here');
 *
 * Modal can add content dynamically via CallbackLater.
 *  $modal->set(function ($modal) {
 *     Form::addTo($modal);
 *  });
 *
 * Modal can use semantic-ui predefine method onApprove or onDeny by passing
 * a jsAction to Modal::addDenyAction or Modal::addApproveAction method. It will not close until the jsAction return true.
 *  $modal->addDenyAction('No', new \Atk4\Ui\JsExpression('function(){window.alert("Can\'t do that."); return false;}'));
 *  $modal->addApproveAction('Yes', new \Atk4\Ui\JsExpression('function(){window.alert("You\'re good to go!");}'));
 *
 * You may also prevent modal from closing via the esc or dimmed area click using $modal->notClosable().
 *
 * Some helper methods are also available to set: transition time, transition type or modal settings from semantic-ui.
 */
class Modal extends View
{
    public $defaultTemplate = 'modal.html';

    /** @var string|null Set null for no title */
    public $title;
    public $loading_label = 'Loading...';
    public $headerCss = 'header';
    public $ui = 'modal';
    public $fx = [];
    public $cb;
    public $cb_view;
    public $args = [];

    /** @var string Currently only "json" response type is supported. */
    public $type = 'json';

    /**
     * Add ability to add css classes to "content" div.
     *
     * @var array
     */
    public $contentCss = ['img', 'content', 'atk-dialog-content'];

    /**
     * if true, the <div class="actions"> at the bottom of the modal is
     * shown. Automatically set to true if any actions are added.
     *
     * @var bool
     */
    public $showActions = false;

    protected function init(): void
    {
        parent::init();
        $this->getApp()->registerModal($this);
    }

    /**
     * Set callback function for this modal.
     * $fx is set as an array in order to comply with View::set().
     * TODO Rename this function and break BC?
     *
     * @param \Closure $fx
     *
     * @return $this
     */
    public function set($fx = null, $ignore = null)
    {
        if (!($fx instanceof \Closure)) {
            throw new Exception('Need to pass a function to Modal::set()');
        } elseif (func_num_args() > 1) {
            throw new Exception('Only one argument is needed by Modal::set()');
        }

        $this->fx = [$fx];
        $this->enableCallback();

        return $this;
    }

    /**
     * Add View to be loaded in this modal and
     * attach CallbackLater to it.
     * The cb_view only will be loaded dynamically within modal
     * div.atk-content.
     */
    public function enableCallback()
    {
        $this->cb_view = View::addTo($this);
        $this->cb_view->stickyGet('__atk_m', $this->name);
        if (!$this->cb) {
            $this->cb = CallbackLater::addTo($this->cb_view);
        }

        $this->cb->set(function () {
            $this->fx[0]($this->cb_view);
            $this->cb->terminateJson($this->cb_view);
        });
    }

    /**
     * Add CSS classes to "content" div.
     */
    public function addContentCss($class)
    {
        $this->contentCss = array_merge($this->contentCss, is_string($class) ? [$class] : $class);
    }

    /**
     * Set modal to show on page.
     * Will trigger modal to be show on page.
     * ex: $button->on('click', $modal->show());.
     *
     * @param array $args
     *
     * @return mixed
     */
    public function show($args = [])
    {
        $js_chain = $this->js();
        if (!empty($args)) {
            $js_chain->data(['args' => $args]);
        }

        return $js_chain->modal('show');
    }

    /**
     * Hide modal from page.
     *
     * @return mixed
     */
    public function hide()
    {
        return $this->js()->modal('hide');
    }

    /**
     * Set modal option.
     *
     * @return $this
     */
    public function setOption($option, $value)
    {
        $this->options['modal_option'][$option] = $value;

        return $this;
    }

    /**
     * Set modal options passing an array.
     *
     * @return $this
     */
    public function setOptions($options)
    {
        if (isset($this->options['modal_option'])) {
            $this->options['modal_option'] = array_merge($this->options['modal_option'], $options);
        } else {
            $this->options['modal_option'] = $options;
        }

        return $this;
    }

    /**
     * Whether any change in modal DOM should automatically refresh cached positions.
     * Allow modal window to add scrolling when adding content dynamically after modal creation.
     *
     * @return $this
     */
    public function observeChanges()
    {
        $this->setOptions(['observeChanges' => true]);

        return $this;
    }

    /**
     * Add scrolling capability to modal.
     *
     * @return $this
     */
    public function addScrolling()
    {
        $this->addContentCss('scrolling');

        return $this;
    }

    /**
     * Set modal transition.
     *
     * @return $this
     */
    public function transition($transition_type)
    {
        $this->settings('transition', $transition_type);

        return $this;
    }

    /**
     * Set modal transition duration.
     *
     * @return $this
     */
    public function duration($time)
    {
        $this->settings('duration', $time);

        return $this;
    }

    /**
     * Add modal settings.
     */
    public function settings($setting_option, $value)
    {
        $this->options['setting'][$setting_option] = $value;
    }

    /**
     * Add a deny action to modal.
     *
     * @param JsExpressionable $jsAction javascript action that will run when deny is click
     *
     * @return $this
     */
    public function addDenyAction($label, $jsAction)
    {
        $button = new Button();
        $button->set($label)->addClass('red cancel');
        $this->addButtonAction($button);
        $this->options['modal_option']['onDeny'] = $jsAction;

        return $this;
    }

    /**
     * Add an approve action button to modal.
     *
     * @param JsExpressionable $jsAction javascript action that will run when deny is click
     *
     * @return $this
     */
    public function addApproveAction($label, $jsAction)
    {
        $b = new Button();
        $b->set($label)->addClass('green ok');
        $this->addButtonAction($b);
        $this->options['modal_option']['onApprove'] = $jsAction;

        return $this;
    }

    /**
     * Add an action button to modal.
     *
     * @return $this
     */
    public function addButtonAction($button)
    {
        $this->add($button, 'actions');
        $this->showActions = true;

        return $this;
    }

    /**
     * Make this modal not closable via close icon, esc key or via the dimmer area.
     *
     * @return $this
     */
    public function notClosable()
    {
        $this->options['modal_option']['closable'] = false;

        return $this;
    }

    protected function renderView(): void
    {
        $data['type'] = $this->type;
        $data['label'] = $this->loading_label;

        if (!empty($this->title)) {
            $this->template->trySet('title', $this->title);
            $this->template->trySet('headerCss', $this->headerCss);
        }

        if ($this->contentCss) {
            $this->template->trySet('contentCss', implode(' ', $this->contentCss));
        }

        if (!empty($this->fx)) {
            $data['uri'] = $this->cb->getJsUrl();
        }

        if (!$this->showActions) {
            $this->template->del('ActionContainer');
        }

        // call modal creation first
        if (isset($this->options['modal_option'])) {
            $this->js(true)->modal($this->options['modal_option']);
        } else {
            $this->js(true)->modal();
        }

        // add setting if available.
        if (isset($this->options['setting'])) {
            foreach ($this->options['setting'] as $key => $value) {
                $this->js(true)->modal('setting', $key, $value);
            }
        }

        if (!isset($this->options['modal_option']['closable']) || $this->options['modal_option']['closable']) {
            $this->template->trySet('close', 'icon close');
        }

        if (!empty($this->args)) {
            $data['args'] = $this->args;
        }
        $this->js(true)->data($data);

        parent::renderView();
    }
}
