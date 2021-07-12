<?php

declare(strict_types=1);

namespace Atk4\Ui\Demos;

/**
 * Demonstrates how to use tabs.
 */
/** @var \Atk4\Ui\App $app */
require_once __DIR__ . '/../init-app.php';

$tabs = \Atk4\Ui\Tabs::addTo($app);

// static tab
\Atk4\Ui\HelloWorld::addTo($tabs->addTab('Hello'));
$tab = $tabs->addTab('Static Tab');
\Atk4\Ui\Message::addTo($tab, ['Content of this tab will refresh only if you reload entire page']);
\Atk4\Ui\LoremIpsum::addTo($tab);

// set the default active tab
$tabs->addTab('Default Active Tab', function ($tab) {
    \Atk4\Ui\Message::addTo($tab, ['This is the active tab by default']);
})->setActive();

// dynamic tab
$tabs->addTab('Dynamic Lorem Ipsum', function ($tab) {
    \Atk4\Ui\Message::addTo($tab, ['Every time you come to this tab, you will see a different text']);
    \Atk4\Ui\LoremIpsum::addTo($tab, ['size' => (int) ($_GET['size'] ?? 1)]);
}, ['apiSettings' => ['data' => ['size' => random_int(1, 4)]]]);

// modal tab
$tabs->addTab('Modal popup', function ($tab) {
    \Atk4\Ui\Button::addTo($tab, ['Load Lorem'])->on('click', \Atk4\Ui\Modal::addTo($tab)->set(function ($p) {
        \Atk4\Ui\LoremIpsum::addTo($p, ['size' => 2]);
    })->show());
});

// dynamic tab
$tabs->addTab('Dynamic Form', function ($tab) {
    \Atk4\Ui\Message::addTo($tab, ['It takes 2 seconds for this tab to load', 'warning']);
    sleep(2);
    $modelRegister = new \Atk4\Data\Model(new \Atk4\Data\Persistence\Array_());
    $modelRegister->addField('name', ['caption' => 'Please enter your name (John)']);

    $form = \Atk4\Ui\Form::addTo($tab, ['segment' => true]);
    $form->setModel($modelRegister->createEntity());
    $form->onSubmit(function (\Atk4\Ui\Form $form) {
        if ($form->model->get('name') !== 'John') {
            return $form->error('name', 'Your name is not John! It is "' . $form->model->get('name') . '". It should be John. Pleeease!');
        }
    });
});

$tabs->addTabUrl('Any other page', 'https://example.com/');
