<?php

declare(strict_types=1);
/**
 * A Simple inline editable text Vue component.
 */

namespace atk4\ui\Component;

use atk4\data\ValidationException;
use atk4\ui\Exception;
use atk4\ui\JsToast;
use atk4\ui\View;

class InlineEdit extends View
{
    public $defaultTemplate = 'inline-edit.html';

    /**
     * JsCallback for saving data.
     *
     * @var \atk4\ui\JsCallback
     */
    public $cb;

    /**
     * Input initial value.
     *
     * @var mixed
     */
    public $initValue;

    /**
     * Whether callback should save value to db automatically or not.
     * Default to using onChange handler.
     * If set to true, then saving to db will be done when model get set
     * and if model is loaded already.
     *
     * @var bool
     */
    public $autoSave = false;

    /**
     * The actual db field name that need to be saved.
     * Default to title field when model is set.
     *
     * @var string|null the name of the field
     */
    public $field;

    /**
     * Whether component should save it's value when input get blur.
     * Using this option will trigger callback when user is moving out of the
     * inline edit field, like pressing tab for example.
     *
     *  Otherwise, callback is fire when pressing Enter key,
     *  while inside the inline input field, only.
     *
     * @var bool
     */
    public $saveOnBlur = true;

    /**
     * Default css for the input div.
     *
     * @var string
     */
    public $inputCss = 'ui right icon input';

    /**
     * The validation error msg function.
     * This function is call when a validation error occur and
     * give you a chance to format the error msg display inside
     * errorNotifier.
     *
     * A default one is supply if this is null.
     * It receive the error ($e) as parameter.
     *
     * @var \Closure|null
     */
    public $formatErrorMsg;

    /**
     * Initialization.
     */
    public function init(): void
    {
        parent::init();
        $this->cb = \atk4\ui\JsCallback::addTo($this);

        // Set default validation error handler.
        if (!$this->formatErrorMsg || !($this->formatErrorMsg instanceof \Closure)) {
            $this->formatErrorMsg = function ($e, $value) {
                $caption = $this->model->getField($this->field)->getCaption();

                return $caption . ' - ' . $e->getMessage() . '. <br>Trying to set this value: "' . $value . '"';
            };
        }
    }

    /**
     * Set Model of this View.
     *
     * @return \atk4\data\Model
     */
    public function setModel(\atk4\data\Model $model)
    {
        parent::setModel($model);
        $this->field = $this->field ? $this->field : $this->model->title_field;
        if ($this->autoSave && $this->model->loaded()) {
            $value = $_POST['value'] ?? null;
            $this->cb->set(function () use ($value) {
                try {
                    $this->model->set($this->field, $this->app->ui_persistence->typecastLoadField($this->model->getField($this->field), $value));
                    $this->model->save();

                    return $this->jsSuccess('Update successfully');
                } catch (ValidationException $e) {
                    $this->app->terminateJson([
                        'success' => true,
                        'hasValidationError' => true,
                        'atkjs' => $this->jsError(($this->formatErrorMsg)($e, $value))->jsRender(),
                    ]);
                }
            });
        }

        return $this->model;
    }

    /**
     * onChange handler.
     * You may supply your own function to handle update.
     * The function will receive one param:
     *  value: the new input value.
     */
    public function onChange(\Closure $fx)
    {
        if (!$this->autoSave) {
            $value = $_POST['value'] ?? null;
            $this->cb->set(function () use ($fx, $value) {
                return $fx($value);
            });
        }
    }

    /**
     * On success notifier.
     *
     * @param string $message
     *
     * @return \atk4\ui\JsToast
     */
    public function jsSuccess($message)
    {
        return new JsToast([
            'title' => 'Success',
            'message' => $message,
            'class' => 'success',
        ]);
    }

    /**
     * On validation error notifier.
     *
     * @param string $message
     *
     * @return \atk4\ui\JsToast
     */
    public function jsError($message)
    {
        return new JsToast([
            'title' => 'Validation error:',
            'displayTime' => 8000,
            'showIcon' => 'exclamation',
            'message' => $message,
            'class' => 'error',
        ]);
    }

    /**
     * Renders View.
     */
    protected function renderView(): void
    {
        parent::renderView();

        $type = ($this->model && $this->field) ? $this->model->getField($this->field)->type : 'text';
        $type = ($type === 'string') ? 'text' : $type;

        if ($type !== 'text' && $type !== 'number') {
            throw new Exception('Only string or number field can be edited inline. Field Type = ' . $type);
        }

        if ($this->model && $this->model->loaded()) {
            $initValue = $this->model->get($this->field);
        } else {
            $initValue = $this->initValue;
        }

        $fieldName = $this->field ? $this->field : 'name';

        $this->template->set('inputCss', $this->inputCss);
        $this->template->trySet('fieldName', $fieldName);
        $this->template->trySet('fieldType', $type);

        $this->vue('atk-inline-edit', [
            'initValue' => $initValue,
            'url' => $this->cb->getJsUrl(),
            'saveOnBlur' => $this->saveOnBlur,
        ]);
    }
}
