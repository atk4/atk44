<?php

declare(strict_types=1);

namespace Atk4\Ui;

/**
 * Class implements Loader, which is a View that will dynamically render it's content.
 * To provide content for a loader, use set() callback.
 */
class Loader extends View
{
    /**
     * Shim is a filler object that is displayed inside loader while the actual content is fetched
     * from the server. You may supply an object here or a seed. This view will be replaced
     * by an actual content when loading stops. Additionally there will be loading indicator
     * on top of this content.
     *
     * @var View
     */
    public $shim;

    /**
     * Specify which event will cause Loader to begen fetching it's actual data. In some cases
     * you would want to wait. You can set a custom JavaScript event name then trigger() it.
     *
     * Default value is `true` which means loading will take place as soon as possible. Setting this
     * to `false` will disable event entirely.
     *
     * @var bool|string
     */
    public $loadEvent = true;

    /** @var string defautl css class */
    public $ui = 'ui segment';

    /** @var Callback for triggering */
    protected $cb;

    protected function init(): void
    {
        parent::init();

        if (!$this->shim) {
            $this->shim = [View::class, 'class' => ['padded segment'], 'style' => ['min-height' => '7em']];
        }

        if (!$this->cb) {
            $this->cb = Callback::addTo($this);
        }
    }

    /**
     * Set callback function for this loader.
     *
     * The loader view is pass as an argument to the loader callback function.
     * This allow to easily update the loader view content within the callback.
     *  $l1 = Loader::addTo($layout);
     *  $l1->set(function ($loader_view) {
     *    do_long_processing_action();
     *    $loader_view->set('new content');
     *  });
     *
     * Or
     *  $l1->set([$my_object, 'run_long_process']);
     *
     * @param \Closure $fx
     *
     * @return $this
     */
    public function set($fx = null, $ignore = null)
    {
        if (!($fx instanceof \Closure)) {
            throw new Exception('Need to pass a function to Loader::set()');
        } elseif (func_num_args() > 1) {
            throw new Exception('Only one argument is needed by Loader::set()');
        }

        $this->cb->set(function () use ($fx) {
            $fx($this);
            $this->cb->terminateJson($this);
        });

        return $this;
    }

    /**
     * Automatically call the jsLoad on a supplied event unless it was already triggered
     * or if user have invoked jsLoad manually.
     */
    protected function renderView(): void
    {
        if (!$this->cb->isTriggered()) {
            if ($this->loadEvent) {
                $this->js($this->loadEvent, $this->jsLoad());
            }
            $this->add($this->shim);
        }

        parent::renderView();
    }

    /**
     * Return a js action that will trigger the loader to start.
     *
     * @param array $args
     *
     * @return mixed
     */
    public function jsLoad($args = [], $apiConfig = [], $storeName = null)
    {
        return $this->js()->atkReloadView([
            'uri' => $this->cb->getUrl(),
            'uri_options' => $args,
            'apiConfig' => !empty($apiConfig) ? $apiConfig : null,
            'storeName' => $storeName ? $storeName : null,
        ]);
    }
}
