<?php

declare(strict_types=1);

namespace atk4\ui\Persistence;

use atk4\data\Model;

class POST extends \atk4\data\Persistence
{
    public function load(Model $model, $id = 0)
    {
        // carefully copy stuff from $_POST into the model
        $data = [];

        foreach ($model->getFields() as $field => $def) {
            if ($def->type === 'boolean') {
                $data[$field] = isset($_POST[$field]);

                continue;
            }

            if (isset($_POST[$field])) {
                $data[$field] = $_POST[$field];
            }
        }

        return array_merge($model->data, $data);
    }
}
