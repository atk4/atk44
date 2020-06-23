<?php

declare(strict_types=1);

namespace atk4\ui\demo;

/** @var \atk4\ui\App $app */
require_once __DIR__ . '/../init-app.php';

use atk4\ui\Form;

\atk4\ui\View::addTo($app, [
    'Forms below focus on Data integration and automated layouts',
    'ui' => 'ignored warning message',
]);

$cc = \atk4\ui\Columns::addTo($app);
$form = Form::addTo($cc->addColumn());

// adding field without model creates a regular line
$form->addField('one');

// Second argument string is used as a caption
$form->addField('two', 'Caption');

// Array second is a default seed for default line field
$form->addField('three', ['caption' => 'Caption2']);

// Use zeroth argument of the seed to specify standard class
$form->addField('four', [Form\Field\Checkbox::class, 'caption' => 'Caption2']);

// Use explicit object for user-defined or 3rd party field
$form->addField('five', new Form\Field\Checkbox())->set(true);

// Objects still accept seed
$form->addField('six', new Form\Field\Checkbox(['caption' => 'Caption3']));

$model = new \atk4\data\Model(new \atk4\data\Persistence\Array_());

// model field uses regular line form field by default
$model->addField('one');

// caption is a top-level property of a field
$model->addField('two', ['caption' => 'Caption']);

// ui can also specify caption which is a form-specific
$model->addField('three', ['ui' => ['form' => ['caption' => 'Caption']]]);

// type is converted into CheckBox form field with caption as a seed
$model->addField('four', ['type' => 'boolean', 'ui' => ['form' => ['caption' => 'Caption2']]]);

// Can specify class for a checkbox explicitly
$model->addField('five', ['ui' => ['form' => [Form\Field\Checkbox::class, 'caption' => 'Caption3']]]);

// Form-specific caption overrides general caption of a field. Also you can specify object instead of seed
$model->addField('six', ['caption' => 'badcaption', 'ui' => ['form' => new Form\Field\Checkbox(['caption' => 'Caption4'])]]);

$form = Form::addTo($cc->addColumn());
$form->setModel($model);

// Next form won't initalize default fields, but we'll add them individually
$form = Form::addTo($cc->addColumn());
$form->setModel($model, false);

// adding that same field but with custom form field seed
$form->addField('one', ['caption' => 'Caption0']);

// another way to override caption
$form->addField('two', 'Caption2');

// We can override type, but seed from model will still be respected
$form->addField('three', [Form\Field\Checkbox::class]);

// We override type and caption here
$form->addField('four', [Form\Field\Line::class, 'caption' => 'CaptionX']);

// We can specify form field object. It's still seeded with caption from model.
$form->addField('five', new Form\Field\Checkbox());

// can add field that does not exist in a model
$form->addField('nine', new Form\Field\Checkbox(['caption' => 'Caption3']));
