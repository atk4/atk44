<?php

declare(strict_types=1);

namespace Atk4\Ui\UserAction;

use Atk4\Core\Factory;
use Atk4\Data\Model\UserAction;
use Atk4\Ui\AbstractView;
use Atk4\Ui\Button;
use Atk4\Ui\Exception;
use Atk4\Ui\Item;
use Atk4\Ui\Modal;
use Atk4\Ui\View;

/**
 * Class ExecutorFactory
 * Executor utility.
 */
class ExecutorFactory
{
    public const JS_EXECUTOR = self::class . '@jsExecutorSeed';
    public const MODAL_EXECUTOR = self::class . '@modalExecutorSeed';
    public const CONFIRMATION_EXECUTOR = self::class . '@confirmationExecutorClass';
    public const BASIC_BUTTON = self::class . '@basicButton';
    public const MODAL_BUTTON = self::class . '@modalExecutorButton';
    public const TABLE_BUTTON = self::class . '@tableButton';
    public const CARD_BUTTON = self::class . '@cardButton';
    public const MENU_ITEM = self::class . '@menuItem';
    public const TABLE_MENU_ITEM = self::class . '@tableMenuItem';

    public const BUTTON_PRIMARY_COLOR = 'primary';

    /**
     * Store basic type of executor to use for create method.
     * Basic type can be changed or added globally via the registerTypeExecutor method.
     * A specific model/action executor may be set via the registerExecutor method.
     */
    protected $executorSeed = [
        self::JS_EXECUTOR => [JsCallbackExecutor::class],
        self::MODAL_EXECUTOR => [ModalExecutor::class],
        self::CONFIRMATION_EXECUTOR => [ConfirmationExecutor::class],
    ];

    /**
     * Store caption to use for action.
     * Can be apply globally per action name
     * or specifically per model/action name.
     *
     * Can be set to a callable method in order
     * to customize the return caption further more.
     *
     * @var array[callable]|string
     */
    protected $triggerCaption = [
        self::MODAL_BUTTON => [
            'add' => 'Save',
            'edit' => 'Save',
        ],
    ];

    /**
     * Store seed|View|callable method
     * to use for creating UI View Object
     * They can be store per either view type or
     * model/action name.
     *
     * @var array[]|View|callable
     */
    protected $triggerSeed = [
        self::TABLE_BUTTON => [
            'edit' => [Button::class, null, 'icon' => 'edit'],
            'delete' => [Button::class, null, 'icon' => 'red trash'],
        ],
        self::MENU_ITEM => [
            'add' => [__CLASS__, 'getAddMenuItem'],
        ],
    ];

    /**
     * Register an executor for basic type.
     */
    public function registerTypeExecutor(string $type, $seed)
    {
        $this->executorSeed[$type] = $seed;
    }

    /**
     * Register an executor instance for a specific model User action.
     *
     * @param array|ExecutorInterface $seed
     */
    public function registerExecutor(UserAction $action, $seed)
    {
        $this->executorSeed[$this->getModelId($action)][$action->short_name] = $seed;
    }

    /**
     * Register a trigger for a specific View type.
     * Trigger can be specify per action or per model/action.
     *
     * @param array|View $seed
     */
    public function registerTrigger(string $type, $seed, UserAction $action, bool $isSpecific = false)
    {
        if ($isSpecific) {
            $this->triggerSeed[$type][$this->getModelId($action)][$action->short_name] = $seed;
        } else {
            $this->triggerSeed[$type][$action->short_name] = $seed;
        }
    }

    /**
     * Set an action trigger type to use it's default seed.
     */
    public function useTriggerDefault(string $type)
    {
        $this->triggerSeed[$type] = [];
    }

    /**
     * Register a trigger caption.
     */
    public function registerCaption(UserAction $action, string $caption, bool $isSpecific = false, string $type = null)
    {
        if ($isSpecific) {
            $this->triggerCaption[$this->getModelId($action)][$action->short_name] = $caption;
        } elseif ($type) {
            $this->triggerCaption[$type][$action->short_name] = $caption;
        } else {
            $this->triggerCaption[$action->short_name] = $caption;
        }
    }

    public function create(UserAction $action, View $owner, string $requiredType = null): object
    {
        return $this->createExecutor($action, $owner, $requiredType);
    }

    public function createTrigger(UserAction $action, string $type = null): object
    {
        return $this->createActionTrigger($action, $type);
    }

    public function getCaption(UserAction $action, string $type = null): string
    {
        return $this->getActionCaption($action, $type);
    }

    /**
     * Create proper executor based on action properties.
     */
    protected function createExecutor(UserAction $action, View $owner, string $requiredType = null): AbstractView
    {
        // required a specific executor type.
        if ($requiredType) {
            if (!($this->executorSeed[$requiredType] ?? null)) {
                throw (new Exception('Required executor type is not set. Register it via the registerTypeExecutor method.'))
                    ->addMoreInfo('type', $requiredType);
            }
            $seed = $this->executorSeed[$requiredType];
        // check if executor is register for this model/action.
        } elseif ($seed = $this->executorSeed[$this->getModelId($action)][$action->short_name] ?? null) {
        } else {
            // if no type is register, determine executor to use base on action properties.
            if (is_callable($action->confirmation)) {
                $seed = $this->executorSeed[self::CONFIRMATION_EXECUTOR];
            } else {
                $seed = (!$action->args && !$action->fields && !$action->preview)
                        ? $this->executorSeed[self::JS_EXECUTOR]
                        : $this->executorSeed[self::MODAL_EXECUTOR];
            }
        }

        $executor = Factory::factory($seed);
        if ($executor instanceof Modal) {
            // add modal to app->html for proper rendering on callback.
            if (!isset($owner->getApp()->html->elements[$executor->short_name])) {
                // very dirty hack, @TODO, attach modals in the standard render tree
                // but only render the result to a different place/html DOM
                $executor->viewForUrl = $owner;
                $executor = $owner->getApp()->html->add($executor, 'Modals'); //->setAction($action);
            }
        } else {
            $executor = $owner->add($executor);
        }

        $executor->setAction($action);

        return $executor;
    }

    /**
     * Create executor View for firing model user action.
     */
    protected function createActionTrigger(UserAction $action, string $type = null): View
    {
        $viewType = array_merge(['default' => [$this, 'getDefaultTrigger']], $this->triggerSeed[$type] ?? []);
        if ($seed = $viewType[$this->getModelId($action)][$action->short_name] ?? null) {
        } elseif ($seed = $viewType[$action->short_name] ?? null) {
        } else {
            $seed = $viewType['default'];
        }

        $seed = is_array($seed) && is_callable($seed) ? $seed($action, $type) : $seed;

        return Factory::factory($seed);
    }

    /**
     * Return executor default trigger seed based on type.
     */
    protected function getDefaultTrigger(UserAction $action, string $type = null): array
    {
        switch ($type) {
            case self::CARD_BUTTON:
            case self::TABLE_BUTTON:
            case self::MODAL_BUTTON:
                $seed = [Button::class, $this->getActionCaption($action, $type)];
                if ($type === self::MODAL_BUTTON || $type === self::CARD_BUTTON) {
                    $seed[] = static::BUTTON_PRIMARY_COLOR;
                }

                break;
            case self::MENU_ITEM:
                $seed = [Item::class, $this->getActionCaption($action, $type), ['class' => 'item']];

                break;
            case self::TABLE_MENU_ITEM:
                $seed = [Item::class, $this->getActionCaption($action, $type), 'id' => false, ['class' => 'item']];

                break;
            default:
                $seed = [Button::class, $this->getActionCaption($action, $type)];
        }

        return $seed;
    }

    /**
     * Return action caption set in actionLabel or default.
     */
    protected function getActionCaption(UserAction $action, string $type = null): string
    {
        if ($caption = $this->triggerCaption[$type][$action->short_name] ?? null) {
        } elseif ($caption = $this->triggerCaption[$this->getModelId($action)][$action->short_name] ?? null) {
        } elseif ($caption = $this->triggerCaption[$action->short_name] ?? null) {
        } else {
            $caption = $action->getCaption();
        }

        return is_array($caption) && is_callable($caption) ? $caption($action) : $caption;
    }

    /**
     * Return Add action seed for menu item.
     */
    protected function getAddMenuItem(UserAction $action): array
    {
        return [Item::class, $this->getAddActionCaption($action), 'icon' => 'plus'];
    }

    /**
     * Return label for add model UserAction.
     */
    protected function getAddActionCaption(UserAction $action): string
    {
        return 'Add ' . $action->getModel()->caption ?? '';
    }

    // Generate id for a model user action.
    protected function getModelId(UserAction $action): string
    {
        return strtolower(str_replace(' ', '_', $action->getModel()->getModelCaption()));
    }
}
