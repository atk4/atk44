<?php

declare(strict_types=1);

namespace atk4\ui\demo;

/**
 * Demonstrates how to use tabs.
 */
/** @var \atk4\ui\App $app */
require_once __DIR__ . '/../init-app.php';

$tabs = \atk4\ui\Tabs::addTo($app);

// static tab
\atk4\ui\Helloworld::addTo($tabs->addTab('Hello'));
$tab = $tabs->addTab('Static Tab');
\atk4\ui\Message::addTo($tab, ['Content of this tab will refresh only if you reload entire page']);
\atk4\ui\Loremipsum::addTo($tab);

// set the default active tab
$tabs->addTab('Default Active Tab', function ($tab) {
    \atk4\ui\Message::addTo($tab, ['This is the active tab by default']);
})->setActive();

// dynamic tab
$tabs->addTab('Dynamic Lorem Ipsum', function ($tab) {
    \atk4\ui\Message::addTo($tab, ['Every time you come to this tab, you will see a different text']);
    \atk4\ui\Loremipsum::addTo($tab, ['size' => (int) $_GET['size'] ?? 1]);
}, ['apiSettings' => ['data' => ['size' => random_int(1, 4)]]]);

// modal tab
$tabs->addTab('Modal popup', function ($tab) {
    \atk4\ui\Button::addTo($tab, ['Load Lorem'])->on('click', \atk4\ui\Modal::addTo($tab)->set(function ($p) {
        \atk4\ui\Loremipsum::addTo($p, ['size' => 2]);
    })->show());
});

// dynamic tab
$tabs->addTab('Dynamic Form', function ($tab) {
    \atk4\ui\Message::addTo($tab, ['It takes 2 seconds for this tab to load', 'warning']);
    sleep(2);
    $modelRegister = new \atk4\data\Model(new \atk4\data\Persistence\Array_());
    $modelRegister->addField('name', ['caption' => 'Please enter your name (John)']);

    $form = \atk4\ui\Form::addTo($tab, ['segment' => true]);
    $form->setModel($modelRegister);
    $form->onSubmit(function (\atk4\ui\Form $form) {
        if ($form->model->get('name') !== 'John') {
            return $form->error('name', 'Your name is not John! It is "' . $form->model->get('name') . '". It should be John. Pleeease!');
        }
    });
});

$tabs->addTabUrl('Any other page', 'https://example.com/');
