<?php

require 'init.php';
require 'database.php';

$g = $app->add(['Grid']);
$g->setModel(new Country($db));
$g->addQuickSearch();

// Example how to filter quickSearch on each keypress
//$g->quickSearch->on('keyup', 'input', $g->quickSearch->js()->find('.atk-search-button')->click());

$g->menu->addItem(['Add Country', 'icon' => 'add square'], new \atk4\ui\jsExpression('alert(123)'));
$g->menu->addItem(['Re-Import', 'icon' => 'power'], new \atk4\ui\jsReload($g));
$g->menu->addItem(['Delete All', 'icon' => 'trash', 'red active']);

$g->addColumn(null, ['Template', 'hello<b>world</b>']);
//$g->addColumn('name', ['TableColumn/Link', 'page2']);
$g->addColumn(null, 'Delete');

$g->addAction('Say HI', function ($j, $id) use ($g) {
    return 'Loaded "'.$g->model->load($id)['name'].'" from ID='.$id;
});

$g->addModalAction(['icon'=>'external'], 'Modal Test', function ($p, $id) {
    $p->add(['Message', 'Clicked on ID='.$id]);
});

$sel = $g->addSelection();
$g->menu->addItem('show selection')->on('click', new \atk4\ui\jsExpression(
    'alert("Selected: "+[])', [$sel->jsChecked()]
));

//Setting ipp with an array will add an ItemPerPageSelector to paginator.
$g->setIpp([10, 25, 50, 100]);
