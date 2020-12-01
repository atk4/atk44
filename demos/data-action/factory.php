<?php

declare(strict_types=1);

namespace atk4\ui\demo;

use atk4\data\Model\UserAction;
use atk4\ui\Button;
use atk4\ui\UserAction\ExecutorFactory;

/** @var \atk4\ui\App $app */
require_once __DIR__ . '/../init-app.php';

$msg = \atk4\ui\Message::addTo($app, [
    'Overriding Executor Factory',
]);
$msg->text->addParagraph('You may easily 
change the look of model user action trigger element by overriding the
Executor factory class.
');
$msg->text->addParagraph('Override Executor class may be applied globally to the application or per View instance.');

// Overriding basic ExecutorFactory in order to change Table and Modal button.
// and also changing default add action label.
$myFactory = get_class(new class() extends ExecutorFactory {
    protected static $actionTriggerSeed = [
        self::MODAL_BUTTON => [
            'edit' => [Button::class, 'Save', 'green'],
            'add' => [Button::class, 'Save', 'green'],
        ],
        self::TABLE_BUTTON => [
            'edit' => [Button::class, null, 'icon' => 'pencil'],
            'delete' => [Button::class, null, 'icon' => 'times red'],
        ],
    ];

    protected static $actionCaption = [
        'add' => [__CLASS__, 'self::getAddActionCaption'],
    ];

    /**
     * Return add action caption.
     */
    protected static function getAddActionCaption(UserAction $action): string
    {
        return 'Add New Record';
    }
});

// Set new executor globally.
$app->defaultExecutorFactory = $myFactory;

$model = new CountryLock($app->db);

$crud = \atk4\ui\Crud::addTo($app, ['ipp' => 5]);
$crud->setModel($model);
