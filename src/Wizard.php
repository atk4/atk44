<?php

declare(strict_types=1);

namespace Atk4\Ui;

use Atk4\Core\Factory;

/**
 * Wizard widget.
 */
class Wizard extends View
{
    use \Atk4\Core\SessionTrait;

    public $defaultTemplate = 'wizard.html';
    public $ui = 'steps';

    /** @var string Get argument for this wizard. */
    public $urlTrigger;

    /** @var array List of steps. */
    public $steps = [];

    /** @var int Current step. */
    public $currentStep;

    /** @var Button Button for going to previous step. */
    public $buttonPrev;

    /** @var Button Buttor for going to next step. */
    public $buttonNext;

    /**
     * Icon that will be used on all steps by default.
     *  - 'empty' , since no such icon exists, no visible icon will be used unless step is completed
     *  - 'square outline', use this (or any other) Semantic UI icon by default
     *  - false,  disables icons alltogether (or using checkboxes for completed steps).
     *
     * @var string|false
     */
    public $defaultIcon = 'empty'; // 'square outline'

    protected function init(): void
    {
        parent::init();

        if (!$this->urlTrigger) {
            $this->urlTrigger = $this->name;
        }

        $this->currentStep = (int) ($this->stickyGet($this->urlTrigger) ?? 0);

        $this->stepTemplate = $this->template->cloneRegion('Step');
        $this->template->del('Step');

        // add buttons
        if ($this->currentStep) {
            $this->buttonPrev = Button::addTo($this, ['Back', 'basic'], ['Left']);
            $this->buttonPrev->link($this->getUrl($this->currentStep - 1));
        }

        $this->buttonNext = Button::addTo($this, ['Next', 'primary'], ['Right']);
        $this->buttonFinish = Button::addTo($this, ['Finish', 'primary'], ['Right']);

        $this->buttonNext->link($this->getUrl($this->currentStep + 1));
    }

    protected function getUrl(int $step): string
    {
        return $this->url([$this->urlTrigger => $step]);
    }

    /**
     * Adds step to the wizard.
     *
     * @param mixed $name Name of tab or Tab object
     *
     * @return View
     */
    public function addStep($name, \Closure $fx)
    {
        $step = Factory::factory([
            Step::class,
            'wizard' => $this,
            'template' => clone $this->stepTemplate,
            'sequence' => count($this->steps),
        ], is_string($name) ? [$name] : $name);

        // add tabs menu item
        $this->steps[] = $this->add($step, 'Step');

        if ($step->sequence === $this->currentStep) {
            $step->addClass('active');
            $fx($this);
        } elseif ($step->sequence < $this->currentStep) {
            $step->addClass('completed');
        }

        if ($step->icon === null) {
            $step->icon = $this->defaultIcon;
        }

        return $step;
    }

    /**
     * Adds an extra screen to show user when he goes beyond last step.
     * There won't be "back" button on this step anymore.
     */
    public function addFinish(\Closure $fx)
    {
        if (count($this->steps) === $this->currentStep + 1) {
            $this->buttonFinish->link($this->getUrl(count($this->steps)));
        } elseif ($this->currentStep === count($this->steps)) {
            $this->buttonPrev->destroy();
            $this->buttonNext->addClass('disabled')->set('Completed');
            $this->buttonFinish->destroy();

            $fx($this);
        } else {
            $this->buttonFinish->destroy();
        }
    }

    public function add($seed, $region = null): AbstractView
    {
        $result = parent::add($seed, $region);

        if ($result instanceof Form) {
            // mingle with the button icon
            if ($result->buttonSave !== null) {
                $result->buttonSave->destroy();
                $result->buttonSave = null;
            }

            $this->buttonNext->on('click', $result->js()->submit());
        }

        return $result;
    }

    /**
     * Get URL to next step. Will respect stickyGET.
     */
    public function urlNext(): string
    {
        return $this->getUrl($this->currentStep + 1);
    }

    /**
     * Get URL to previous step. Will respect stickyGET.
     *
     * @return string URL to previous step
     */
    public function jsNext()
    {
        return new JsExpression('document.location = []', [$this->urlNext()]);
    }

    protected function recursiveRender(): void
    {
        if (!$this->steps) {
            $this->addStep(['No Steps Defined', 'icon' => 'configure', 'description' => 'use $wizard->addStep() now'], function ($p) {
                Message::addTo($p, ['Step content will appear here', 'type' => 'error', 'text' => 'Specify callback to addStep() which would populate this area.']);
            });
        }

        if (count($this->steps) === $this->currentStep + 1) {
            $this->buttonNext->destroy();
        }

        parent::recursiveRender();
    }

    protected function renderView(): void
    {
        // Set proper width to the wizard
        $c = count($this->steps);
        $enumeration = ['one', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];
        $this->ui = $enumeration[$c] . ' ' . $this->ui;

        if ($c > 6) {
            $this->addClass('mini');
        } elseif ($c > 5) {
            $this->addClass('tiny');
        } elseif ($c > 4) {
            $this->addClass('small');
        }

        parent::renderView();
    }
}
