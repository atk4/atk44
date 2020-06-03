<?php

namespace atk4\ui\demo;

use atk4\ui\Form;

class Flyers extends \atk4\data\Model
{
    public function init(): void
    {
        parent::init();

        $this->addField('first_name');
        $this->addField('last_name');
        $this->addField('age', ['values' => ['1' => 'From months to 2 years old', '2' => 'From 3 to 17 years old', '3' => '18 years or more']]);
    }
}

/**
 * Flyers form form.
 */
class FlyersForm extends Form
{
    public $db;
    public $flyers = [];

    public $cards = [
        ['name' => 'Frequent Flyer Program', 'id' => 1, 'nodes' => []],
        ['name' => 'World Class', 'id' => 2, 'nodes' => []],
        ['name' => 'Around the world', 'id' => 3, 'nodes' => []],
    ];

    public function init(): void
    {
        parent::init();

        $this->addField('first_name', [\atk4\ui\FormField\Line::class, 'caption' => 'Main passenger', 'placeholder' => 'First name'], ['required' => true]);
        $this->addField('last_name', [\atk4\ui\FormField\Line::class, 'renderLabel' => false, 'placeholder' => 'Last name'], ['required' => true]);
        $this->addField('email', [\atk4\ui\FormField\Line::class], ['required' => true]);

        $this->addField('from', [\atk4\ui\FormField\Calendar::class, 'caption' => 'Date:', 'placeholder' => 'From'], ['type' => 'date', 'required' => true]);
        $this->addField('to', [\atk4\ui\FormField\Calendar::class, 'renderLabel' => false, 'placeholder' => 'To'], ['type' => 'date', 'required' => true]);

        $this->addField('contains', [
            \atk4\ui\FormField\Line::class,
            'placeholder' => 'Search for country containing ...',
            'renderLabel' => false,
        ]);

        $this->addField('country', [
            \atk4\ui\FormField\Lookup::class,
            'model' => new \atk4\ui\demo\Country($this->db),
            'dependency' => function ($model, $data) {
                isset($data['contains']) ? $model->addCondition('name', 'like', '%' . $data['contains'] . '%') : null;
            },
            'search' => ['name', 'iso', 'iso3'],
            'caption' => 'Destination',
            'placeholder' => 'Select your destination',
        ], ['required' => true]);

        $ml = $this->addField('multi', [\atk4\ui\FormField\MultiLine::class, 'rowLimit' => 4, 'addOnTab' => true, 'caption' => 'Additional passengers:', 'renderLabel' => false]);
        $ml->setModel(new Flyers(new \atk4\data\Persistence\Array_($this->flyers)));

        $cards = $this->addField('cards', [new \atk4\ui\FormField\TreeItemSelector(['treeItems' => $this->cards]), 'caption' => 'Flyers program:'], ['type' => 'array', 'serialize' => 'json']);
        $cards->set(json_encode([]));

        $this->onSubmit(function ($f) {
            return new \atk4\ui\jsToast('Thank you!');
        });
    }
}
