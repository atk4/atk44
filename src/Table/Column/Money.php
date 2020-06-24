<?php

declare(strict_types=1);

namespace atk4\ui\Table\Column;

use atk4\data\Model;
use atk4\ui\Table;

/**
 * Column for formatting money.
 */
class Money extends Table\Column
{
    /** @var bool Should we show zero values in cells? */
    public $show_zero_values = true;

    // overrides
    public $attr = ['all' => ['class' => ['right aligned single line']]];

    public function getTagAttributes($position, $attr = [])
    {
        $attr = array_merge_recursive($attr, ['class' => ['{$_' . $this->short_name . '_class}']]);

        return parent::getTagAttributes($position, $attr);
    }

    public function getDataCellHTML(\atk4\data\Field $f = null, $extra_tags = [])
    {
        if (!isset($f)) {
            throw new \atk4\ui\Exception('Money column requires a field');
        }

        return $this->getTag(
            'body',
            '{$' . $f->short_name . '}',
            $extra_tags
        );
    }

    public function getHTMLTags(Model $row, $field)
    {
        if ($field->get() < 0) {
            return ['_' . $this->short_name . '_class' => 'negative'];
        } elseif (!$this->show_zero_values && (float) $field->get() === 0.0) {
            return ['_' . $this->short_name . '_class' => '', $field->short_name => '-'];
        }

        return ['_' . $this->short_name . '_class' => ''];
    }
}
