<?php

declare(strict_types=1);

namespace atk4\ui\UserAction;

use atk4\core\HookTrait;
use atk4\data\Model;
use atk4\ui\JsCallback;
use atk4\ui\JsExpressionable;
use atk4\ui\JsToast;
use atk4\ui\View;

/**
 * Javascript Action executor.
 *
 * Will execute a model action using a js Event.
 *
 * Usage:
 * When use with View::on method, then JsCallbackExecutor executor is automatically create.
 *  $btn->on('click', $model->getUserAction('delete') , [4, 'confirm'=> 'This will delete record with id 4. Are you sure?']);
 *
 * Manual setup.
 * $action = $model->getUserAction('delete')
 * $ex = JsCallbackExecutor::addTo($app)->setAction($action, [4])
 * $btn->on('click', $ex, ['confirm'=> 'This will delete record with id 4. Are you sure?']);
 */
class JsCallbackExecutor extends JsCallback implements ExecutorInterface
{
    use HookTrait;

    /** @var Model\UserAction The model user action */
    public $action;

    /**
     * @var JsExpressionable array|\Closure JsExpression to return if action was successful, e.g "new JsToast('Thank you')"
     */
    public $jsSuccess;

    public function getAction(): Model\UserAction
    {
        return $this->action;
    }

    /**
     * Set action to be execute.
     */
    public function setAction(Model\UserAction $action)
    {
        $this->action = $action;
        if (!$this->action->enabled && $this->getOwner() instanceof View) {
            $this->getOwner()->addClass('disabled');
        }

        return $this;
    }

    /**
     * Execute model user action.
     */
    public function executeModelAction(array $args = [])
    {
        $this->set(function ($j) {
            // may be id is pass within $post args.
            $id = $_POST['c0'] ?? $_POST[$this->action->getOwner()->id_field] ?? null;
            if ($id && $this->action->appliesTo === Model\UserAction::APPLIES_TO_SINGLE_RECORD) {
                $this->action->getOwner()->tryLoad($id);
            }

            if ($errors = $this->_hasAllArguments()) {
                $js = new JsToast(['title' => 'Error', 'message' => 'Missing Arguments: ' . implode(', ', $errors), 'class' => 'error']);
            } else {
                $args = [];
                foreach ($this->action->args as $key => $val) {
                    $args[] = $_POST[$key] ?? $this->args[$key];
                }

                $return = $this->action->execute(...$args);
                $success = $this->jsSuccess instanceof \Closure
                    ? ($this->jsSuccess)($this, $this->action->getOwner(), $id, $return)
                    : $this->jsSuccess;

                $js = $this->hook(BasicExecutor::HOOK_AFTER_EXECUTE, [$return, $id]) ?: $success ?: new JsToast('Success' . (is_string($return) ? (': ' . $return) : ''));
            }

            return $js;
        }, $args);

        return $this;
    }

    /**
     * Set jsSuccess property.
     *
     * @param array|\Closure $fx
     *
     * @return $this
     */
    public function setJsSuccess($fx)
    {
        $this->jsSuccess = $fx;

        return $this;
    }

    /**
     * Check if all argument values have been provided.
     */
    private function _hasAllArguments()
    {
        $errors = [];
        foreach ($this->action->args as $key => $val) {
            if (!isset($this->args[$key])) {
                $errors[] = $key;
            }
        }

        return $errors;
    }
}
