<?php
/**
 * Dropdown form field that will based it's list value
 * according to another input value.
 * Also possible to cascade value from another cascade field.
 * For example:
 *  - you need to narrow product base on Category and sub category
 *       $f = Form::addTo($app);
 *       $f->addField('category_id', [DropDown::class, 'model' => new Category($db)])->set(3);
 *       $f->addField('sub_category_id', [DropDownCascade::class, 'cascadeFrom' => 'category_id', 'reference' => 'SubCategories']);
 *       $f->addField('product_id', [DropDownCascade::class, 'cascadeFrom' => 'sub_category_id', 'reference' => 'Products']);
 */

namespace atk4\ui\FormField;

use atk4\data\Model;
use atk4\ui\Exception;

class DropDownCascade extends DropDown
{
    /** @var null|string|Generic the form input to use for setting this dropdown list values from. */
    public $cascadeFrom = null;

    /** @var null|string|Model the hasMany reference model that will generated value for this dropdown list.*/
    public $reference = null;

    /** @var null The form input create by cascadeFrom field*/
    protected $cascadeInput = null;

    /** @var null The casacade input value. */
    protected $cascadeInputValue = null;

    public function init()
    {
        parent::init();

        if (!$this->cascadeFrom) {
            throw new Exception('cascadeFrom property is not set.');
        }

        $this->cascadeInput = is_string($this->cascadeFrom) ? $this->form->getField($this->cascadeFrom) : $this->cascadeFrom;

        if (!$this->cascadeInput instanceof Generic) {
            throw new Exception('cascadeFrom property should be an instance of atk4/ui/FormField/Generic');
        }

        $this->cascadeInputValue = $_POST[$this->cascadeInput->name] ?? $this->cascadeInput->field->get('value');

        $this->model = $this->cascadeInput->model ? $this->cascadeInput->model->ref($this->reference) : null;

        $expr = [
            function ($t) {
                return [
                    $this->js()->dropdown('change values', $this->getNewValues($this->cascadeInputValue)),
                    $this->js()->removeClass('loading')
                ];
            },
            $this->js()->dropdown('clear'),
            $this->js()->addClass('loading'),
        ];

        $this->cascadeInput->onChange($expr, ['args' => [$this->cascadeInput->name => $this->cascadeInput->jsInput()->val()]]);
    }

    /**
     * Generate new dropdown values based on cascadeInput model selected id.
     * Return an empty value set if id is null.
     *
     * @return array
     */
    public function getNewValues($id)
    {
        if (!$id) {
            return [['value' => '', 'text' => $this->empty, 'name' => $this->empty]];
        }

        $values = [];
        foreach ($this->cascadeInput->model->load($id)->ref($this->reference) as $k => $row) {
            if ($this->renderRowFunction && is_callable($this->renderRowFunction)) {
                $res = call_user_func($this->renderRowFunction, $row, $k);
                $values[] = ['value' => $res['value'], 'text' => $row['name'], 'name' => $res['title']];
            } else {
                $values[] = ['value' => $row['id'], 'text' => $row['name'], 'name' => $row['name']];
            }
        }

        return $values;
    }

    public function renderView()
    {
        // can't be multiple selection.
        $this->isMultiple = false;

        parent::renderView();
        // set value on initial load if cascadeInput model is not loaded.
        if (!$this->cascadeInput->model->loaded()) {
            $this->js(true)->dropdown('change values', $this->getNewValues($this->cascadeInputValue));
        }
    }
}
