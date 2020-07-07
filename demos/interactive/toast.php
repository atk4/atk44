<?php

declare(strict_types=1);

namespace atk4\ui\demo;

/** @var \atk4\ui\App $app */
require_once __DIR__ . '/../init-app.php';

\atk4\ui\Header::addTo($app, ['Toast']);

$btn = \atk4\ui\Button::addTo($app)->set('Minimal');

$btn->on('click', new \atk4\ui\JsToast('Hi there!'));

$btn = \atk4\ui\Button::addTo($app)->set('Using a title');

$btn->on('click', new \atk4\ui\JsToast([
    'title' => 'Title',
    'message' => 'See I have a title',
]));

\atk4\ui\Header::addTo($app, ['Using class name']);

$btn = \atk4\ui\Button::addTo($app)->set('Success');
$btn->on('click', new \atk4\ui\JsToast([
    'title' => 'Success',
    'message' => 'Well done',
    'class' => 'success',
]));

$btn = \atk4\ui\Button::addTo($app)->set('Error');
$btn->on('click', new \atk4\ui\JsToast([
    'title' => 'Error',
    'message' => 'An error occured',
    'class' => 'error',
]));

$btn = \atk4\ui\Button::addTo($app)->set('Warning');
$btn->on('click', new \atk4\ui\JsToast([
    'title' => 'Warning',
    'message' => 'Behind you!',
    'class' => 'warning',
]));

\atk4\ui\Header::addTo($app, ['Using different position']);

$btn = \atk4\ui\Button::addTo($app)->set('Bottom Right');
$btn->on('click', new \atk4\ui\JsToast([
    'title' => 'Bottom Right',
    'message' => 'Should appear at the bottom on your right',
    'position' => 'bottom right',
]));

$btn = \atk4\ui\Button::addTo($app)->set('Top Center');
$btn->on('click', new \atk4\ui\JsToast([
    'title' => 'Top Center',
    'message' => 'Should appear at the top center',
    'position' => 'top center',
]));

\atk4\ui\Header::addTo($app, ['Other Options']);

$btn = \atk4\ui\Button::addTo($app)->set('5 seconds');
$btn->on('click', new \atk4\ui\JsToast([
    'title' => 'Timeout',
    'message' => 'I will stay here for 5 sec.',
    'displayTime' => 5000,
]));

$btn = \atk4\ui\Button::addTo($app)->set('For ever');
$btn->on('click', new \atk4\ui\JsToast([
    'title' => 'No Timeout',
    'message' => 'I will stay until you click me',
    'displayTime' => 0,
]));

$btn = \atk4\ui\Button::addTo($app)->set('Using Message style');
$btn->on('click', new \atk4\ui\JsToast([
    'title' => 'Awesome',
    'message' => 'I got my style from the message class',
    'class' => 'purple',
    'className' => ['toast' => 'ui message', 'title' => 'ui header'],
]));

$btn = \atk4\ui\Button::addTo($app)->set('With progress bar');
$btn->on('click', new \atk4\ui\JsToast([
    'title' => 'Awesome',
    'message' => 'See how long I will last',
    'showProgress' => 'bottom',
]));
