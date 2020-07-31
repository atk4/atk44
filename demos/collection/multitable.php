<?php

declare(strict_types=1);

namespace atk4\ui\demo;

/** @var \atk4\ui\App $app */
require_once __DIR__ . '/../init-app.php';

// Re-usable component implementing counter

/** @var \atk4\ui\Columns $finderClass */
$finderClass = get_class(new class() extends \atk4\ui\Columns {
    public $route = [];

    public function setModel(\atk4\data\Model $model, $route = [])
    {
        parent::setModel($model);

        $this->addClass('internally celled');

        // lets add our first table here
        $table = \atk4\ui\Table::addTo($this->addColumn(), ['header' => false, 'very basic selectable'])->addStyle('cursor', 'pointer');
        $table->setModel($model, [$model->title_field]);

        $selections = explode(',', $_GET[$this->name] ?? '');

        if (!empty($selections[0])) {
            $table->js(true)->find('tr[data-id=' . $selections[0] . ']')->addClass('active');
        }

        $path = [];
        $jsReload = new \atk4\ui\JsReload($this, [$this->name => new \atk4\ui\JsExpression('[]+[]', [
            $path ? (implode(',', $path) . ',') : '',
            new \atk4\ui\JsExpression('$(this).data("id")'),
        ])]);
        $table->on('click', 'tr', $jsReload);

        while ($selections && $id = array_shift($selections)) {
            $path[] = $id;
            $model->tryLoad($id);
            if (!$model->loaded()) {
                break;
            }
            $ref = array_shift($route);
            if (!$route) {
                $route[] = $ref; // repeat last route
            }

            if (!$model->hasRef($ref)) {
                break; // no such route
            }

            $model = $model->ref($ref);

            $table = \atk4\ui\Table::addTo($this->addColumn(), ['header' => false, 'very basic selectable'])->addStyle('cursor', 'pointer');
            $table->setModel($model->setLimit(25), [$model->title_field]);

            if ($selections) {
                $table->js(true)->find('tr[data-id=' . $selections[0] . ']')->addClass('active');
            }

            $jsReload = new \atk4\ui\JsReload($this, [$this->name => new \atk4\ui\JsExpression('[]+[]', [
                $path ? (implode(',', $path) . ',') : '',
                new \atk4\ui\JsExpression('$(this).data("id")'),
            ])]);
            $table->on('click', 'tr', $jsReload);
        }

        return $this->model;
    }
});

$model = new File($app->db);
$model->addCondition('parent_folder_id', null);
$model->setOrder('is_folder desc, name');

\atk4\ui\Header::addTo($app, ['MacOS File Finder', 'subHeader' => 'Component built around Table, Columns and JsReload']);

$vp = \atk4\ui\VirtualPage::addTo($app)->set(function ($vp) use ($model) {
    $model->toQuery('delete')->execute();
    $model->importFromFilesystem(dirname(__DIR__));
    \atk4\ui\Button::addTo($vp, ['Import Complete', 'big green fluid'])->link('multitable.php');
    $vp->js(true)->closest('.modal')->find('.header')->remove();
});

\atk4\ui\Button::addTo($app, ['Re-Import From Filesystem', 'top attached'])->on('click', new \atk4\ui\JsModal('Now importing ... ', $vp));

$finderClass::addTo($app, 'bottom attached')
    ->addClass('top attached segment')
    ->setModel($model->setLimit(5), ['SubFolder']);
