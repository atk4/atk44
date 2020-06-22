<?php

declare(strict_types=1);

namespace atk4\ui\Form\Field;

use atk4\ui\Form;

/**
 * Input element for a form field.
 */
class Textarea extends Input
{
    /** @var int Text area vertical size */
    public $rows = 2;

    /**
     * returns <input .../> tag.
     *
     * @return string
     */
    public function getInput()
    {
        return $this->app->getTag(
            'textarea',
            array_merge([
                'name' => $this->short_name,
                'type' => $this->inputType,
                'rows' => $this->rows,
                'placeholder' => $this->placeholder,
                'id' => $this->id . '_input',
                'readonly' => $this->readonly ? 'readonly' : false,
                'disabled' => $this->disabled ? 'disabled' : false,
            ], $this->inputAttr),
            (string) $this->getValue() // need to cast to string to avoid null values which break html markup
        );
    }
}
