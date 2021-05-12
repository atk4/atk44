<?php

declare(strict_types=1);

namespace Atk4\Ui\Demos;

/** @var \Atk4\Ui\App $app */
require_once __DIR__ . '/../init-app.php';

// Test 1 - Basic reloading
\Atk4\Ui\Header::addTo($app, ['Button reloading segment']);
$v = \Atk4\Ui\View::addTo($app, ['ui' => 'segment'])->set((string) random_int(1, 100));
\Atk4\Ui\Button::addTo($app, ['Reload random number'])->js('click', new \Atk4\Ui\JsReload($v, [], new \Atk4\Ui\JsExpression('console.log("Output with afterSuccess");')));

// Reload but keep custom changes
\Atk4\Ui\Header::addTo($app, ['Button reloading View without loosing original values']);
$v = \Atk4\Ui\View::addTo($app)->set((string) random_int(1, 1000));
$inputControl = \Atk4\Ui\Form\Control\Input::addTo($v);
\Atk4\Ui\View::addTo($app)->js(true, null, $inputControl)->find('input')->val('test ' . (string) random_int(1, 1000)); // simulate change by user

$app->requireJs('https://fiduswriter.github.io/diffDOM/browser/diffDOM.js'); // use probably versioned CDN link here
// demo view-source:http://fiduswriter.github.io/diffDOM/demo/index.html
// man https://github.com/fiduswriter/diffDOM

// alternatives:
// https://www.npmjs.com/package/preact
// https://www.npmjs.com/package/virtual-dom

// https://vuejs.org/v2/guide/render-function.html#The-Virtual-DOM
// https://vuejs.org/v2/api/#Vue-compile
// https://stackoverflow.com/questions/32106155/can-you-force-vue-js-to-reload-re-render

\Atk4\Ui\Button::addTo($app, ['Reload but keep custom changes'])->js('click', new \Atk4\Ui\JsExpression('{}', [
    'var jsRenderFunc = function () { ' . (new \Atk4\Ui\JsReload($v))->jsRender() . ' };'
    . 'var reloadUrl = ' . (new \Atk4\Ui\JsExpression('[]', [$v->jsUrl(['__atk_reload' => $v->name])]))->jsRender() . ';'
    . <<<'EOF'
        // jsRenderFunc(); // reload like with JsReload

        $.get(reloadUrl, null, function(data) {
            if (data.success !== true) {
                alert('Invalid reload response');
            }
            var newHtml = data.html;
            // var newAtkJs = data.atkjs; // ignore JS, compare html only
            var id = data.id;
            console.log('Reload triggered, ID: ' + id);

            var dd = new diffDOM.DiffDOM();
            var cloneDom = function (elem) {
                var virtualElem = document.createElement(elem.tagName);
                dd.apply(virtualElem, dd.diff(virtualElem, elem));
                return virtualElem;
            };
            if (window.manualChangesEndVdom === undefined) {
                window.previousVdom = new DOMParser().parseFromString(window.snapshotAfterLoad, 'text/html').getElementById(id); // always clone
                window.manualChangesStartVdom = cloneDom(window.previousVdom);
                window.manualChangesEndVdom = cloneDom(window.previousVdom);
            }
            var realElem = document.getElementById(id);

            // find new manual changes
            var newManualChanges = dd.diff(window.previousVdom, realElem);
            dd.apply(window.manualChangesEndVdom, newManualChanges);
            var allManualChanges = dd.diff(window.manualChangesStartVdom, window.manualChangesEndVdom);

            // find all new changes (from server)
            var newChanges = dd.diff(realElem, newHtml);

            // combine diffs and apply at once
            var changes = [];
            changes.push(...newChanges);
            changes.push(...allManualChanges); // manual changes are prioritized
            dd.apply(realElem, changes);
            window.previousVdom = cloneDom(realElem);

            console.log('all ok');
        }, 'json')
        EOF,
]));

// Test 2 - Reloading self
\Atk4\Ui\Header::addTo($app, ['JS-actions will be re-applied']);
$b2 = \Atk4\Ui\Button::addTo($app, ['Reload Myself']);
$b2->js('click', new \Atk4\Ui\JsReload($b2));

// Test 3 - avoid duplicate
\Atk4\Ui\Header::addTo($app, ['No duplicate JS bindings']);
$b3 = \Atk4\Ui\Button::addTo($app, ['Reload other button']);
$b4 = \Atk4\Ui\Button::addTo($app, ['Add one dot']);

$b4->js('click', $b4->js()->text(new \Atk4\Ui\JsExpression('[]+"."', [$b4->js()->text()])));
$b3->js('click', new \Atk4\Ui\JsReload($b4));

// Test 3 - avoid duplicate
\Atk4\Ui\Header::addTo($app, ['Make sure nested JS bindings are applied too']);
$seg = \Atk4\Ui\View::addTo($app, ['ui' => 'segment']);

// add 3 counters
Counter::addTo($seg);
Counter::addTo($seg, ['40']);
Counter::addTo($seg, ['-20']);

// Add button to reload all counters
$bar = \Atk4\Ui\View::addTo($app, ['ui' => 'buttons']);
$b = \Atk4\Ui\Button::addTo($bar, ['Reload counter'])->js('click', new \Atk4\Ui\JsReload($seg));

// Relading with argument
\Atk4\Ui\Header::addTo($app, ['We can pass argument to reloader']);

$v = \Atk4\Ui\View::addTo($app, ['ui' => 'segment'])->set($_GET['val'] ?? 'No value');

\Atk4\Ui\Button::addTo($app, ['Set value to "hello"'])->js('click', new \Atk4\Ui\JsReload($v, ['val' => 'hello']));
\Atk4\Ui\Button::addTo($app, ['Set value to "world"'])->js('click', new \Atk4\Ui\JsReload($v, ['val' => 'world']));

$val = \Atk4\Ui\Form\Control\Line::addTo($app, ['']);
$val->addAction('Set Custom Value')->js('click', new \Atk4\Ui\JsReload($v, ['val' => $val->jsInput()->val()], $val->jsInput()->focus()));
