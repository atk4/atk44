<?php

declare(strict_types=1);

namespace atk4\ui\Table\Column;

use atk4\ui\Table;

/**
 * Implement drag handler column for sorting table.
 */
class DragHandler extends Table\Column
{
    public $class;
    public $tag = 'i';
    /** @var \atk4\ui\JsCallback */
    public $cb;

    protected function init(): void
    {
        parent::init();

        if (!$this->class) {
            $this->class = 'content icon';
        }
        $this->cb = \atk4\ui\JsSortable::addTo($this->table, ['handleClass' => 'atk-handle']);
    }

    /**
     * Callback when table has been reorder using handle.
     */
    public function onReorder(\Closure $fx)
    {
        $this->cb->onReorder($fx);
    }

    public function getDataCellTemplate(\Atk4\Data\Field $field = null)
    {
        return $this->getApp()->getTag($this->tag, ['class' => $this->class . ' atk-handle', 'style' => 'cursor:pointer; color: #bcbdbd']);
    }
}
