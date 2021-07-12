<?php

declare(strict_types=1);

namespace Atk4\Ui;

use Atk4\Core\AppScopeTrait;
use Atk4\Core\ContainerTrait;
use Atk4\Core\InitializerTrait;
use Atk4\Core\StaticAddToTrait;
use Atk4\Core\TrackableTrait;

/**
 * Abstract view tree item (used only for View and Callback, you want probably to extend one of these).
 *
 * @property View[] $elements
 *
 * @method View getOwner()
 */
abstract class AbstractView
{
    use AppScopeTrait;
    use ContainerTrait {
        add as private _add;
    }
    use InitializerTrait {
        init as private _init;
    }
    use StaticAddToTrait;
    use TrackableTrait;

    /**
     * Default name of the element.
     *
     * @var string
     */
    public $defaultName = 'atk';

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

    // {{{ Default init() method and add() logic

    /**
     * For the absence of the application, we would add a very
     * simple one.
     */
    protected function initDefaultApp()
    {
        $this->setApp(new App([
            'skin' => $this->skin,
            'catch_exceptions' => false,
            'always_run' => false,
            'catch_runaway_callbacks' => false,
        ]));
        $this->getApp()->invokeInit();
    }

    /**
     * Called when view becomes part of render tree. You can override it but avoid
     * placing any "heavy processing" here.
     */
    protected function init(): void
    {
        if (!$this->issetApp()) {
            $this->initDefaultApp();
        }

        if ($this->name === null) {
            $this->name = $this->defaultName;
        }

        $this->_init();

        // add default objects
        foreach ($this->_add_later as [$object, $args]) {
            $this->add($object, $args);
        }
        $this->_add_later = [];
    }

    /**
     * @param AbstractView $object
     */
    public function add($object, $args = null): self
    {
        (self::class)::assertInstanceOf($object);

        if (func_num_args() > 2) { // prevent bad usage
            throw new \Error('Too many method arguments');
        } elseif ($this->_rendered) {
            throw new Exception('You cannot add anything into the view after it was rendered');
        }

        if (!$this->issetApp()) {
            $this->_add_later[] = [$object, $args];

            return $object;
        }

        // will call init() of the object
        $this->_add($object, $args);

        return $object;
    }
}
