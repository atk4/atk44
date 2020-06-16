<?php

declare(strict_types=1);

namespace atk4\ui\demo;

/** @var \atk4\ui\App $app */
require_once __DIR__ . '/../init-app.php';

$country = new CountryLock($app->db);
$country->tryLoadAny();
DemoActionsUtil::setupDemoActions($country);

\atk4\ui\Header::addTo($app, ['Right Panel', 'subHeader' => 'Content on the fly!']);

// PANEL

\atk4\ui\Header::addTo($app, ['Static', 'size' => 4, 'subHeader' => 'Panel may have static content only.']);
$panel = $app->layout->addRightPanel(new \atk4\ui\Panel\Right(['dynamic' => false]));
\atk4\ui\Message::addTo($panel, ['This panel contains only static content.']);
$btn = \atk4\ui\Button::addTo($app, ['Open Static']);
$btn->on('click', $panel->jsOpen());
\atk4\ui\View::addTo($app, ['ui' => 'divider']);

// PANEL_1

\atk4\ui\Header::addTo($app, ['Dynamic', 'size' => 4, 'subHeader' => 'Panel can load content dynamically']);
$panel_1 = $app->layout->addRightPanel(new \atk4\ui\Panel\Right());
\atk4\ui\Message::addTo($panel_1, ['This panel will load content dynamically below according to button select on the right.']);
$btn = \atk4\ui\Button::addTo($app, ['Button 1']);
$btn->js(true)->data('btn', '1');
$btn->on('click', $panel_1->jsOpen(['btn'], 'orange'));

$btn = \atk4\ui\Button::addTo($app, ['Button 2']);
$btn->js(true)->data('btn', '2');
$btn->on('click', $panel_1->jsOpen(['btn'], 'orange'));

$view = \atk4\ui\View::addTo($app, ['ui' => 'segment']);
$text = \atk4\ui\Text::addTo($view);
$text->set($_GET['txt'] ?? 'Not Complete');

$panel_1->onOpen(function ($p) use ($view) {
    $panel = \atk4\ui\View::addTo($p, ['ui' => 'basic segment']);
    $btn_number = $panel->stickyGet('btn');

    $p_text = 'You loaded panel content using button #' . $btn_number;
    \atk4\ui\Message::addTo($panel, ['Panel 1', 'text' => $p_text]);

    $p_reload = \atk4\ui\Button::addTo($panel, ['Reload Myself']);
    $p_reload->on('click', new \atk4\ui\jsReload($panel));

    \atk4\ui\View::addTo($panel, ['ui' => 'divider']);
    $p_btn = \atk4\ui\Button::addTo($panel, ['Complete']);
    $p_btn->on('click', [
        $p->owner->jsClose(),
        new \atk4\ui\jsReload($view, ['txt' => 'Complete using button #' . $btn_number]),
    ]);
});

\atk4\ui\View::addTo($app, ['ui' => 'divider']);

// PANEL_2

\atk4\ui\Header::addTo($app, ['Closing option', 'size' => 4, 'subHeader' => 'Panel can prevent from closing.']);

$panel_2 = $app->layout->addRightPanel(new \atk4\ui\Panel\Right(['hasClickAway' => false]));
$icon = \atk4\ui\Icon::addTo($app, ['big cog'])->addStyle('cursor', 'pointer');
$icon->on('click', $panel_2->jsOpen());
$panel_2->addConfirmation('Changes will be lost. Are you sure?');

$msg = \atk4\ui\Message::addTo($panel_2, ['Prevent close.']);

$txt = \atk4\ui\Text::addTo($msg);
$txt->addParagraph('This panel can only be closed via it\'s close icon at top right.');
$txt->addParagraph('Try to change dropdown value and close without saving!');

$panel_2->onOpen(function ($p) {
    $f = \atk4\ui\Form::addTo($p);
    $f->addHeader('Settings');
    $f->addField('name', [\atk4\ui\FormField\DropDown::class, 'values' => ['1' => 'Option 1', '2' => 'Option 2']])
        ->set('1')
        ->onChange($p->owner->jsDisplayWarning(true));

    $f->onSubmit(function (\atk4\ui\Form $form) use ($p) {
        return [
            new \atk4\ui\jsToast('Saved, closing panel.'),
            $p->owner->jsDisplayWarning(false),
            $p->owner->jsClose(),
        ];
    });
});
\atk4\ui\View::addTo($app, ['ui' => 'divider']);

// PANEL_3

$c_id = $app->stickyGet('id');
\atk4\ui\Header::addTo($app, ['UserAction Friendly', 'size' => 4, 'subHeader' => 'Panel can run model action.']);
$panel_3 = $app->layout->addRightPanel(new \atk4\ui\Panel\Right());
$msg = \atk4\ui\Message::addTo($panel_3, ['Run Country model action below.']);

$deck = \atk4\ui\View::addTo($app, ['ui' => 'cards']);
$country->setLimit(3);

foreach ($country as $ct) {
    $c = \atk4\ui\Card::addTo($deck, ['useLabel' => true])->addStyle('cursor', 'pointer');
    $c->setModel($ct);
    $c->on('click', $panel_3->jsOpen(['id'], 'orange'));
}

$panel_3->onOpen(function ($p) use ($country, $c_id) {
    $seg = \atk4\ui\View::addTo($p, ['ui' => 'basic segment center aligned']);
    \atk4\ui\Header::addTo($seg, [$country->load($c_id)->getTitle()]);
    $buttons = \atk4\ui\View::addTo($seg, ['ui' => 'vertical basic buttons']);
    foreach ($country->getActions() as $action) {
        $b = \atk4\ui\Button::addTo($buttons, [$action->getDescription()]);
        $b->on('click', $action, ['args' => ['id' => $c_id]]);
    }
});
