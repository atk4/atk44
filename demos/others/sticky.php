<?php

namespace atk4\ui\demo;

use atk4\ui\Button;

require_once __DIR__ . '/../atk-init.php';

\atk4\ui\View::addTo($app, [
    'Sticky GET allows us to preserve some GET arguments',
    'ui' => 'ignored info message',
]);

$myButtonClass = get_class(new class() extends \atk4\ui\Button {
    public function renderView()
    {
        $this->link($this->content);
        $this->addClass('green');

        return parent::renderView();
    }
});

// Buttons
$myButtonClass::addTo($app, $app->url());
$myButtonClass::addTo($app, $app->url(['xx' => 'YEY']));
$myButtonClass::addTo($app, $app->url(['c' => 'OHO']));
$myButtonClass::addTo($app, $app->url(['xx' => 'YEY', 'c' => 'OHO']));

// URLs presented by a blank app
\atk4\ui\Header::addTo($app, ['URLs presented by a blank app']);
Button::addTo($app, [$app->url()]);
Button::addTo($app, [$app->url(['b' => 2])]);
Button::addTo($app, [$app->url(['b' => 2, 'c' => false])]);
Button::addTo($app, [$app->url(['b' => 2, 'c' => null])]);
Button::addTo($app, [$app->url(['b' => 2, 'c' => 'abc'])]);

// Sticky for xx=
\atk4\ui\Header::addTo($app, ['Now add sticky for xx=' . $app->stickyGET('xx')]);
Button::addTo($app, [$app->url()]);
Button::addTo($app, [$app->url(['b' => 2])]);
Button::addTo($app, [$app->url(['b' => 2, 'c' => false])]);
Button::addTo($app, [$app->url(['b' => 2, 'c' => null])]);
Button::addTo($app, [$app->url(['b' => 2, 'c' => 'abc'])]);

// Sticky for c=
\atk4\ui\Header::addTo($app, ['Now also add sticky for c=' . $app->stickyGET('c')]);
Button::addTo($app, [$app->url()]);
Button::addTo($app, [$app->url(['b' => 2])]);
Button::addTo($app, [$app->url(['b' => 2, 'c' => false])]);
Button::addTo($app, [$app->url(['b' => 2, 'c' => null])]);
Button::addTo($app, [$app->url(['b' => 2, 'c' => 'abc'])]);

// Various ways to build links
\atk4\ui\Header::addTo($app, ['Various ways to build links']);
Button::addTo($app, [$app->url()]);
Button::addTo($app, [$app->url('other.php')]);
Button::addTo($app, [$app->url('other')]);
Button::addTo($app, [$app->url(['other', 'b' => 2])]);
Button::addTo($app, [$app->url('http://yahoo.com/')]);
Button::addTo($app, [$app->url('http://yahoo.com/?q=abc')]);
