<?php

chdir('..');
require_once 'init.php';
require_once 'database.php';

$c = \atk4\ui\Columns::addTo($app);

$grid = \atk4\ui\Grid::addTo($c->addColumn(), ['ipp' => 10, 'menu' => false]);
$grid->setModel(new Country($db), ['name']);

$country_loader = \atk4\ui\Loader::addTo($c->addColumn(), ['loadEvent' => false, 'shim' => ['Text', 'Select country on your left']]);

$grid->table->onRowClick($country_loader->jsLoad(['id' => $grid->table->jsRow()->data('id')]));

$country_loader->set(function ($p) use ($db) {
    \atk4\ui\Form::addTo($p)->setModel(new Country($db))->load($_GET['id']);
});
