<?php

declare(strict_types=1);

namespace atk4\ui\FormField;

use atk4\data\Field;
use atk4\data\Model;
use atk4\data\Model\Scope\AbstractScope;
use atk4\data\Model\Scope\Condition;
use atk4\data\Model\Scope\Scope;
use atk4\ui\Exception;
use atk4\ui\Template;

class ScopeBuilder extends Generic
{
    /** @var bool Do not render label for this input. */
    public $renderLabel = false;

    /**
     * Field type specific options.
     *
     * @var array
     */
    public $options = [
        'enum' => [
            'limit' => 250,
        ],
    ];
    /**
     * Max depth of nested conditions allowed.
     * Corresponds to VueQueryBulder maxDepth.
     * Maximum support by js component is 5.
     *
     * @var int
     */
    public $maxDepth = 3;

    /**
     * Fields to use for creating the rules.
     *
     * @var array
     */
    public $fields = [];

    /**
     * The template needed for the scopebuilder view.
     *
     * @var Template
     */
    public $scopeBuilderTemplate;

    /**
     * List of delimiters for auto-detection in order of priority.
     *
     * @var array
     */
    public static $listDelimiters = [';', ',', '|'];

    /**
     * The scopebuilder View. Assigned in init().
     *
     * @var \atk4\ui\View
     */
    protected $scopeBuilderView;

    /**
     * Definition of VueQueryBuilder rules.
     *
     * @var array
     *            todo setback to protected after testing
     */
    public $rules = [];

    /**
     * Set Labels for Vue-Query-Builder
     * see https://dabernathy89.github.io/vue-query-builder/configuration.html#labels.
     *
     * @var array
     */
    public $labels = [];

    /**
     * Default VueQueryBuilder query.
     *
     * @var array
     *            todo reset to protected after testing
     *            todo reset to empty after testing
     */
    public $query = ['logicalOperator' => 'all', 'children' => []];

    protected const OPERATOR_EQUALS = 'equals';
    protected const OPERATOR_DOESNOT_EQUAL = 'does not equal';
    protected const OPERATOR_GREATER = 'is greater than';
    protected const OPERATOR_GREATER_EQUAL = 'is greater or equal to';
    protected const OPERATOR_LESS = 'is less than';
    protected const OPERATOR_LESS_EQUAL = 'is less or equal to';
    protected const OPERATOR_CONTAINS = 'contains';
    protected const OPERATOR_DOESNOT_CONTAIN = 'does not contain';
    protected const OPERATOR_BEGINS_WITH = 'begins with';
    protected const OPERATOR_DOESNOT_BEGIN_WITH = 'does not begin with';
    protected const OPERATOR_ENDS_WITH = 'begins with';
    protected const OPERATOR_DOESNOT_END_WITH = 'does not begin with';
    protected const OPERATOR_IN = 'is in';
    protected const OPERATOR_NOT_IN = 'is not in';
    protected const OPERATOR_MATCHES_REGEX = 'matches regular expression';
    protected const OPERATOR_DOESNOT_MATCH_REGEX = 'does not match regular expression';
    protected const OPERATOR_EMPTY = 'is empty';
    protected const OPERATOR_NOT_EMPTY = 'is not empty';

    /**
     * VueQueryBulder => Condition map of operators.
     *
     * @var array
     */
    protected static $operators = [
        self::OPERATOR_EQUALS => '=',
        self::OPERATOR_DOESNOT_EQUAL => '!=',
        self::OPERATOR_GREATER => '>',
        self::OPERATOR_GREATER_EQUAL => '>=',
        self::OPERATOR_LESS => '<',
        self::OPERATOR_LESS_EQUAL => '<=',
        self::OPERATOR_CONTAINS => 'LIKE',
        self::OPERATOR_DOESNOT_CONTAIN => 'NOT LIKE',
        self::OPERATOR_BEGINS_WITH => 'LIKE',
        self::OPERATOR_DOESNOT_BEGIN_WITH => 'NOT LIKE',
        self::OPERATOR_ENDS_WITH => 'LIKE',
        self::OPERATOR_DOESNOT_END_WITH => 'NOT LIKE',
        self::OPERATOR_IN => 'IN',
        self::OPERATOR_NOT_IN => 'NOT IN',
        self::OPERATOR_MATCHES_REGEX => 'REGEXP',
        self::OPERATOR_DOESNOT_MATCH_REGEX => 'NOT REGEXP',
        self::OPERATOR_EMPTY => '=',
        self::OPERATOR_NOT_EMPTY => '!=',
    ];

    /**
     * Definition of rule types.
     *
     * @var array
     */
    protected static $ruleTypes = [
        'default' => 'text',
        'text' => [
            'type' => 'text',
            'operators' => [
                self::OPERATOR_EQUALS,
                self::OPERATOR_DOESNOT_EQUAL,
                self::OPERATOR_GREATER,
                self::OPERATOR_GREATER_EQUAL,
                self::OPERATOR_LESS,
                self::OPERATOR_LESS_EQUAL,
                self::OPERATOR_CONTAINS,
                self::OPERATOR_DOESNOT_CONTAIN,
                self::OPERATOR_BEGINS_WITH,
                self::OPERATOR_DOESNOT_BEGIN_WITH,
                self::OPERATOR_ENDS_WITH,
                self::OPERATOR_DOESNOT_END_WITH,
                self::OPERATOR_IN,
                self::OPERATOR_NOT_IN,
                self::OPERATOR_MATCHES_REGEX,
                self::OPERATOR_DOESNOT_MATCH_REGEX,
            ],
        ],
        'enum' => [
            'type' => 'select',
            'operators' => [
                self::OPERATOR_EQUALS,
                self::OPERATOR_DOESNOT_EQUAL,
            ],
            'choices' => [__CLASS__, 'getChoices'],
        ],
        'numeric' => [
            'type' => 'text',
            'inputType' => 'number',
            'operators' => [
                self::OPERATOR_EQUALS,
                self::OPERATOR_DOESNOT_EQUAL,
                self::OPERATOR_GREATER,
                self::OPERATOR_GREATER_EQUAL,
                self::OPERATOR_LESS,
                self::OPERATOR_LESS_EQUAL,
            ],
        ],
        'boolean' => [
            'type' => 'radio',
            'operators' => [],
            'choices' => [
                ['label' => 'Yes', 'value' => 1],
                ['label' => 'No', 'value' => 0],
            ],
        ],
        'date' => [
            'type' => 'custom-component',
            'component' => 'DatePicker',
            'inputType' => 'date',
            'operators' => [
                self::OPERATOR_EQUALS,
                self::OPERATOR_DOESNOT_EQUAL,
                self::OPERATOR_GREATER,
                self::OPERATOR_GREATER_EQUAL,
                self::OPERATOR_LESS,
                self::OPERATOR_LESS_EQUAL,
                self::OPERATOR_EMPTY,
                self::OPERATOR_NOT_EMPTY,
            ],
        ],
        'datetime' => 'date',
        'integer' => 'numeric',
        'float' => 'numeric',
        'checkbox' => 'boolean',
    ];

    public function init(): void
    {
        parent::init();

        if (!$this->scopeBuilderTemplate) {
            $this->scopeBuilderTemplate = new Template('<div id="{$_id}" class="ui"><atk-query-builder v-bind="initData"></atk-query-builder></div>');
        }

        $this->scopeBuilderView = \atk4\ui\View::addTo($this, ['template' => $this->scopeBuilderTemplate]);

        if ($this->form) {
            $this->form->onHook(\atk4\ui\Form::HOOK_LOAD_POST, function ($form, &$post) {
                $key = $this->field->short_name;
                // todo only for testing
                $post[$key] = json_decode($post[$key], true);
//                $post[$key] = $this->queryToScope(json_decode($post[$key], true));
            });
        }
    }

    /**
     * Set the model to build scope for.
     *
     * @return Model
     */
    public function setModel(Model $model)
    {
        $model = parent::setModel($model);

        $this->fields = $this->fields ?: array_keys($model->getFields());

        foreach ($this->fields as $fieldName) {
            $field = $model->getField($fieldName);

            $this->addFieldRule($field);

            $this->addReferenceRules($field);
        }

        $this->query = $this->scopeToQuery($model->scope())['query'] ?? [];

        return $model;
    }

    /**
     * Add the field rules to use in VueQueryBuilder.
     */
    protected function addFieldRule(Field $field): self
    {
        $type = ($field->enum || $field->values || $field->reference) ? 'enum' : $field->type;

        $this->rules[] = self::getRule($type, array_merge([
            'id' => $field->short_name,
            'label' => $field->getCaption(),
            'options' => $this->options[strtolower($type)] ?? [],
        ], $field->ui['scopebuilder'] ?? []), $field);

        return $this;
    }

    /**
     * Add rules on the referenced model fields.
     */
    protected function addReferenceRules(Field $field): self
    {
        if ($reference = $field->reference) {
            // add the number of records rule
            $this->rules[] = self::getRule('numeric', [
                'id' => $reference->link . '/#',
                'label' => $field->getCaption() . ' number of records ',
            ]);

            $refModel = $reference->getModel();

            // add rules on all fields of the referenced model
            foreach ($refModel->getFields() as $refField) {
                $refField->ui['scopebuilder'] = [
                    'id' => $reference->link . '/' . $refField->short_name,
                    'label' => $field->getCaption() . ' is set to record where ' . $refField->getCaption(),
                ];

                $this->addFieldRule($refField);
            }
        }

        return $this;
    }

    protected static function getRule($type, array $defaults = [], Field $field = null): array
    {
        $rule = self::$ruleTypes[strtolower($type)] ?? self::$ruleTypes['default'];

        // when $rule is an alias
        if (is_string($rule)) {
            return self::getRule($rule, $defaults, $field);
        }

        $options = $defaults['options'] ?? [];

        // 'options' is atk specific so not necessary to pass it to VueQueryBuilder
        unset($defaults['options']);

        // when $rule is callable
        if (is_callable($rule)) {
            $rule = call_user_func($rule, $field, $options);
        }

        // map all values for callables and merge with defaults
        return array_merge(array_map(function ($value) use ($field, $options) {
            return is_array($value) && is_callable($value) ? call_user_func($value, $field, $options) : $value;
        }, $rule), $defaults);
    }

    /**
     * Returns the choises array for the field rule.
     */
    protected static function getChoices(Field $field, $options = []): array
    {
        $choices = [];
        if ($field->enum) {
            $choices = array_combine($field->enum, $field->enum);
        }
        if ($field->values && is_array($field->values)) {
            $choices = $field->values;
        } elseif ($field->reference) {
            $model = $field->reference->refModel();

            if ($limit = $options['limit'] ?? false) {
                $model->setLimit($limit);
            }

            foreach ($model as $item) {
                $choices[$item[$model->id_field]] = $item[$model->title_field];
            }
        }

        $ret = [['label' => '[empty]', 'value' => null]];
        foreach ($choices as $value => $label) {
            $ret[] = compact('label', 'value');
        }

        return $ret;
    }

    public function renderView()
    {
//        $this->app->addStyle('
//            .vue-query-builder select,input {
//                width: auto !important;
//            }
//        ');

//        $this->scopeBuilderView->template->trySetHTML('Input', $this->getInput());

        parent::renderView();

        $this->scopeBuilderView->vue(
            'atk-query-builder',
            [
                'data' => [
                    'rules' => $this->rules,
                    'maxDepth' => $this->maxDepth,
                    'query' => $this->query,
                    'name' => $this->short_name,
                    'labels' => $this->labels ?? null,
                ],
            ]
        );
    }

    /**
     * Converts an VueQueryBuilder query array to Condition or Scope.
     */
    public static function queryToScope(array $query): AbstractScope
    {
        $type = $query['type'] ?? 'query-builder-group';
        $query = $query['query'] ?? $query;

        switch ($type) {
            case 'query-builder-group':
                $components = array_map([static::class, 'queryToScope'], $query['children']);
                $junction = $query['logicalOperator'] === 'all' ? Scope::AND : Scope::OR;

                $scope = Scope::create($components, $junction);

                break;
            case 'query-builder-rule':
                $scope = self::queryToCondition($query);

                break;
            default:
                $scope = Scope::create();

            break;
        }

        return $scope;
    }

    /**
     * Converts an VueQueryBuilder rule array to Condition or Scope.
     */
    public static function queryToCondition(array $query): Condition
    {
        $key = $query['rule'] ?? null;
        $operator = $query['operator'] ?? null;
        $value = $query['value'] ?? null;

        switch ($operator) {
            case self::OPERATOR_EMPTY:
            case self::OPERATOR_NOT_EMPTY:
                $value = null;

            break;
            case self::OPERATOR_BEGINS_WITH:
            case self::OPERATOR_DOESNOT_BEGIN_WITH:
                $value = $value . '%';

            break;
            case self::OPERATOR_ENDS_WITH:
            case self::OPERATOR_DOESNOT_END_WITH:
                $value = '%' . $value;

            break;
            case self::OPERATOR_CONTAINS:
            case self::OPERATOR_DOESNOT_CONTAIN:
                $value = '%' . $value . '%';

            break;
            case self::OPERATOR_IN:
            case self::OPERATOR_NOT_IN:
                $value = explode(self::detectDelimiter($value), $value);

                break;
            default:

            break;
        }

        $operator = $operator ? (self::$operators[strtolower($operator)] ?? '=') : null;

        return Condition::create($key, $operator, $value);
    }

    /**
     * Converts Scope or Condition to VueQueryBuilder query array.
     */
    public static function scopeToQuery(AbstractScope $scope): array
    {
        $query = [];
        switch (get_class($scope)) {
            case Condition::class:
                $query = [
                    'type' => 'query-builder-rule',
                    'query' => self::conditionToQuery($scope),
                ];

            break;
            case Scope::class:
                $children = [];
                foreach ($scope->getActiveComponents() as $component) {
                    $children[] = self::scopeToQuery($component);
                }

                $query = $children ? [
                    'type' => 'query-builder-group',
                    'query' => [
                        'logicalOperator' => $scope->isAnd() ? 'all' : 'any',
                        'children' => $children,
                    ],
                ] : [];

            break;
        }

        return $query;
    }

    /**
     * Converts a Condition to VueQueryBuilder query array.
     */
    public static function conditionToQuery(Condition $condition): array
    {
        if (is_string($condition->key)) {
            $rule = $condition->key;
        } elseif ($condition->key instanceof Field) {
            $rule = $condition->key->short_name;
        } else {
            throw new Exception('Unsupported scope key: ' . gettype($condition->key));
        }

        $operator = $condition->operator;
        $value = $condition->value;

        if (stripos($operator, 'like') !== false) {
            // no %
            $match = 0;
            // % at the beginning
            $match += substr($value, 0, 1) === '%' ? 1 : 0;
            // % at the end
            $match += substr($value, -1) === '%' ? 2 : 0;

            $map = [
                'LIKE' => [
                    self::OPERATOR_EQUALS,
                    self::OPERATOR_BEGINS_WITH,
                    self::OPERATOR_ENDS_WITH,
                    self::OPERATOR_CONTAINS,
                ],
                'NOT LIKE' => [
                    self::OPERATOR_DOESNOT_EQUAL,
                    self::OPERATOR_DOESNOT_BEGIN_WITH,
                    self::OPERATOR_DOESNOT_END_WITH,
                    self::OPERATOR_DOESNOT_CONTAIN,
                ],
            ];

            $operator = $map[strtoupper($operator)][$match];

            $value = trim($value, '%');
        } else {
            if (is_array($value)) {
                $map = [
                    '=' => 'IN',
                    '!=' => 'NOT IN',
                ];
                $value = implode(',', $value);
                $operator = $map[$operator] ?? 'IN';
            }
            $operator = array_search(strtoupper($operator), self::$operators, true) ?: self::OPERATOR_EQUALS;
        }

        return compact('rule', 'operator', 'value');
    }

    /**
     * Auto-detects a string delimiter based on list of predefined values in ScopeBuilder::$listDelimiters in order of priority.
     *
     * @param string $value
     *
     * @return string
     */
    public static function detectDelimiter($value)
    {
        $matches = [];
        foreach (self::$listDelimiters as $delimiter) {
            $matches[$delimiter] = substr_count($value, $delimiter);
        }

        $max = array_keys($matches, max($matches), true);

        return reset($max) ?: reset(self::$listDelimiters);
    }
}