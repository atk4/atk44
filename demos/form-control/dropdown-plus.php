<?php

declare(strict_types=1);

namespace atk4\ui\demo;

use atk4\ui\Form;

/** @var \atk4\ui\App $app */
require_once __DIR__ . '/../init-app.php';

$demo = Demo::addTo($app);

\atk4\ui\Header::addTo($demo->left, ['Dropdown sample:']);
\atk4\ui\Header::addTo($demo->right, ['Cascading Dropdown']);
$txt = \atk4\ui\Text::addTo($demo->right);
$txt->addParagraph('Dropdown may also be used in a cascade manner.');
$txt->addParagraph('You may find more information in DropdownCascade class.');
$v = \atk4\ui\View::addTo($demo->right, ['ui' => 'column padded centered grid']);
$btn = \atk4\ui\Button::addTo($v, ['DropdownCascade Class'])
    ->link('https://github.com/atk4/ui/blob/develop/src/Form/Field/DropdownCascade.php', '_blank')
    ->addClass('centered aligned');

$form = Form::addTo($demo->left);

//standard with model: use id_field as Value, title_field as Title for each Dropdown option
$form->addControl(
    'withModel',
    [Form\Control\Dropdown::class,
        'caption' => 'Dropdown with data from Model',
        'model' => (new Country($app->db))->setLimit(25),
    ]
);

//custom callback: alter title
$form->addControl(
    'withModel2',
    [Form\Control\Dropdown::class,
        'caption' => 'Dropdown with data from Model',
        'model' => (new Country($app->db))->setLimit(25),
        'renderRowFunction' => function ($row) {
            return [
                'value' => $row->id,
                'title' => $row->getTitle() . ' (' . $row->get('iso3') . ')',
            ];
        },
    ]
);

//custom callback: add icon
$form->addControl(
    'withModel3',
    [Form\Control\Dropdown::class,
        'caption' => 'Dropdown with data from Model',
        'model' => (new File($app->db))->setLimit(25),
        'renderRowFunction' => function ($row) {
            return [
                'value' => $row->id,
                'title' => $row->getTitle(),
                'icon' => $row->get('is_folder') ? 'folder' : 'file',
            ];
        },
    ]
);

$form->addControl(
    'enum',
    [Form\Control\Dropdown::class,
        'caption' => 'Using Single Values',
        'values' => ['default', 'option1', 'option2', 'option3'],
    ]
);

$form->addControl(
    'values',
    [Form\Control\Dropdown::class,
        'caption' => 'Using values with default text',
        'empty' => 'Choose an option',
        'values' => ['default' => 'Default', 'option1' => 'Option 1', 'option2' => 'Option 2', 'option3' => 'Option 3'],
    ]
);

$form->addControl(
    'icon',
    [Form\Control\Dropdown::class,
        'caption' => 'Using icon',
        'empty' => 'Choose an icon',
        'values' => ['tag' => ['Tag', 'icon' => 'tag icon'], 'globe' => ['Globe', 'icon' => 'globe icon'], 'registered' => ['Registered', 'icon' => 'registered icon'], 'file' => ['File', 'icon' => 'file icon']],
    ]
);

$form->addControl(
    'multi',
    [Form\Control\Dropdown::class,
        'caption' => 'Multiple selection',
        'empty' => 'Choose has many options needed',
        'isMultiple' => true,
        'values' => ['default' => 'Default', 'option1' => 'Option 1', 'option2' => 'Option 2'],
    ]
);

$form->onSubmit(function (Form $form) use ($app) {
    $message = $app->encodeJson($form->model->get());

    $view = new \atk4\ui\Message('Values: ');
    $view->init();
    $view->text->addParagraph($message);

    return $view;
});
