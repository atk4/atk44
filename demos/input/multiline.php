<?php

namespace atk4\ui\demo;

require_once __DIR__ . '/../atk-init.php';

use atk4\ui\jsExpression;
use atk4\ui\jsFunction;

/**
 * Class Inventory Item.
 */
class multiline extends \atk4\data\Model
{
    public function init(): void
    {
        parent::init();

        $this->addField('item', ['required' => true, 'default' => 'item']);
        $this->addField('qty', ['type' => 'integer', 'caption' => 'Qty / Box', 'required' => true, 'ui' => ['multiline' => ['width' => 2]]]);
        $this->addField('box', ['type' => 'integer', 'caption' => '# of Boxes', 'required' => true, 'ui' => ['multiline' => ['width' => 2]]]);
        $this->addExpression('total', ['expr' => function (\atk4\data\Model $row) {
            return $row->get('qty') * $row->get('box');
        }, 'type' => 'integer']);
    }
}

\atk4\ui\Header::addTo($app, ['MultiLine form field', 'icon' => 'database', 'subHeader' => 'Collect/Edit multiple rows of table record.']);

$data = [];

$inventory = new InventoryItem(new \atk4\data\Persistence\Array_($data));

// Populate some data.
$total = 0;
for ($i = 1; $i < 3; ++$i) {
    $inventory['id'] = $i;
    $inventory['item'] = 'item_' . $i;
    $inventory['qty'] = random_int(10, 100);
    $inventory['box'] = random_int(1, 10);
    $total = $total + ($inventory['qty'] * $inventory['box']);
    $inventory->saveAndUnload();
}

$f = \atk4\ui\Form::addTo($app);
$f->addField('test');
// Add multiline field and set model.
$ml = $f->addField('ml', ['MultiLine', 'options' => ['color' => 'blue'], 'rowLimit' => 10, 'addOnTab' => true]);
$ml->setModel($inventory);

// Add total field.
$sub_layout = $f->layout->addSublayout('Columns');
$sub_layout->addColumn(12);
$c = $sub_layout->addColumn(4);
$f_total = $c->addField('total', ['readonly' => true])->set($total);

// Update total when qty and box value in any row has changed.
$ml->onLineChange(function ($rows, $form) use ($f_total) {
    $total = 0;
    foreach ($rows as $row => $cols) {
        $qty = array_column($cols, 'qty')[0];
        $box = array_column($cols, 'box')[0];
        $total = $total + ($qty * $box);
    }

    return $f_total->jsInput()->val($total);
}, ['qty', 'box']);

$ml->jsAfterAdd = new jsFunction(['value'], [new jsExpression('console.log(value)')]);
$ml->jsAfterDelete = new jsFunction(['value'], [new jsExpression('console.log(value)')]);

$f->onSubmit(function (\atk4\ui\Form $form) use ($ml) {
    $rows = $ml->saveRows()->getModel()->export();

    return new \atk4\ui\jsToast(json_encode(array_values($rows)));
});
