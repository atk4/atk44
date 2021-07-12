<?php

declare(strict_types=1);

namespace Atk4\Ui\Demos;

use Atk4\Ui\Callback;
use Atk4\Ui\Wizard;

/** @var \Atk4\Ui\App $app */
require_once __DIR__ . '/../init-app.php';

/**
 * Demonstrates how to use a wizard.
 */
$wizard = Wizard::addTo($app, ['urlTrigger' => 'demo_wizard']);
// First step will automatcally be active when you open page first. It
// will contain the 'Next' button with a link.
$wizard->addStep('Welcome', function (Wizard $wizard) {
    \Atk4\Ui\Message::addTo($wizard, ['Welcome to wizard demonstration'])->text
        ->addParagraph('Use button "Next" to advance')
        ->addParagraph('You can specify your existing database connection string which will be used
        to create a table for model of your choice');
});

// If you add a form on a step, then wizard will automatically submit that
// form on "Next" button click, performing validation and submission. You do not need
// to return any action from form's onSubmit callback. You may also use memorize()
// to store wizard-specific variables
$wizard->addStep(['Set DSN', 'icon' => 'configure', 'description' => 'Database Connection String'], function (Wizard $wizard) {
    $form = \Atk4\Ui\Form::addTo($wizard);
    // IMPORTANT - needed for php_unit Wizard test.
    $form->cb->setUrlTrigger('w_form_submit');

    $form->addControl('dsn', 'Connect DSN', ['required' => true])->placeholder = 'mysql://user:pass@db-host.example.com/mydb';
    $form->onSubmit(function (\Atk4\Ui\Form $form) use ($wizard) {
        $wizard->memorize('dsn', $form->model->get('dsn'));

        return $wizard->jsNext();
    });
});

// Alternatvely, you may access buttonNext , buttonPrev properties of a wizard
// and set a custom js action or even set a different link. You can use recall()
// to access some values that were recorded on another steps.
$wizard->addStep(['Select Model', 'description' => '"Country" or "Stat"', 'icon' => 'table'], function (Wizard $wizard) {
    if (isset($_GET['name'])) {
        $wizard->memorize('model', $_GET['name']);
        $wizard->getApp()->redirect($wizard->urlNext());
    }

    $columns = \Atk4\Ui\Columns::addTo($wizard);

    $grid = \Atk4\Ui\Grid::addTo($columns->addColumn(), ['paginator' => false, 'menu' => false]);
    \Atk4\Ui\Message::addTo($columns->addColumn(), ['Information', 'info'])->text
        ->addParagraph('Selecting which model you would like to import into your DSN. If corresponding table already exist, we might add extra fields into it. No tables, columns or rows will be deleted.');

    $grid->setSource(['Country', 'Stat']);

    // should work after url() fix
    $grid->addDecorator('name', [\Atk4\Ui\Table\Column\Link::class, [], ['name']]);

    //$t->addDecorator('name', [\Atk4\Ui\Table\Column\Link::class, [$wizard->stepCallback->name=>$wizard->currentStep], ['name']]);

    $wizard->buttonNext->addClass('disabled');
});

// Steps may contain interractive elements. You can disable navigational buttons
// and enable them as you see fit. Use handy js method to trigger advancement to
// the next step.
$wizard->addStep(['Migration', 'description' => 'Create or update table', 'icon' => 'database'], function (Wizard $wizard) {
    $console = \Atk4\Ui\Console::addTo($wizard);
    $wizard->buttonFinish->addClass('disabled');

    $console->set(function ($console) use ($wizard) {
        $dsn = $wizard->recall('dsn');
        $model = $wizard->recall('model');

        $console->output('please wait');
        sleep(1);
        $console->output('connecting to "' . $dsn . '" (well not really, this is only a demo)');
        sleep(2);
        $console->output('initializing table for model "' . $model . '" (again - tricking you)');
        sleep(1);
        $console->output('DONE');

        $console->send($wizard->buttonFinish->js()->removeClass('disabled'));
    });
});

// calling addFinish adds a step, which will not appear in the list of steps, but
// will be displayed when you click the "Finish". Finish will not add any buttons
// because you shouldn't be able to navigate wizard back without restarting it.
// Only one finish can be added.
$wizard->addFinish(function (Wizard $wizard) {
    \Atk4\Ui\Header::addTo($wizard, ['You are DONE', 'huge centered']);
});
