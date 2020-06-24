<?php

declare(strict_types=1);

namespace atk4\ui;

/**
 * Card class displays a single record data.
 *
 * IMPORTANT: Although the purpose of the "Card" component will remain the same, we do plan to
 * improve implementation of a card to to use https://semantic-ui.com/views/card.html.
 */
class CardTable extends Table
{
    protected $_bypass = false;

    public function setModel(\atk4\data\Model $m, $columndef = null)
    {
        if ($this->_bypass) {
            return parent::setModel($m);
        }

        if (!$m->loaded()) {
            throw (new Exception('Model must be loaded'))
                ->addMoreInfo('model', $m);
        }

        $data = [];

        $ui_values = $this->app ? $this->app->ui_persistence->typecastSaveRow($m, $m->get()) : $m->get();

        foreach ($m->get() as $key => $value) {
            if (!$columndef || ($columndef && in_array($key, $columndef, true))) {
                $data[] = [
                    'id' => $key,
                    'field' => $m->getField($key)->getCaption(),
                    'value' => $ui_values[$key],
                ];
            }
        }

        $this->_bypass = true;
        $mm = parent::setSource($data);
        $this->addDecorator('value', [Table\Column\Multiformat::class, function ($row, $field) use ($m) {
            $field = $m->getField($row->data['id']);
            $ret = $this->decoratorFactory($field);
            if ($ret instanceof Table\Column\Money) {
                $ret->attr['all']['class'] = ['single line'];
            }

            return $ret;
        }]);
        $this->_bypass = false;

        return $mm;
    }
}
