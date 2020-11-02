<?php
declare(strict_types = 1);
namespace ModelHistory\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;
use ModelHistory\Model\Entity\ModelHistory;
use ModelHistory\Model\Transform\Transform;
use Cake\Database\Schema\TableSchema;
use Cake\Database\Schema\TableSchemaInterface;

/**
 * ModelHistory Model
 */
class ModelHistoryTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        $this->setTable('model_history');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Users', [
            'foreignKey' => 'user_id'
        ]);
    }

    protected function _initializeSchema(\Cake\Database\Schema\TableSchemaInterface $schema): \Cake\Database\Schema\TableSchemaInterface
    {
        $schema->setColumnType('data', 'json');
        $schema->setColumnType('context', 'json');
        return $schema;
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): \Cake\Validation\Validator
    {
        $validator->add('data', 'custom', [
            'rule' => function ($value, $context) {
                if ($context['data']['action'] != ModelHistory::ACTION_COMMENT) {
                    return true;
                }

                return !empty($value['comment']);
            },
            'message' => __d('model_history', 'comment_empty')
        ]);

        return $validator;
    }

    /**
     * Add a record to the ModelHistory
     *
     * @param EntityInterface $entity Entity
     * @param string $action One of ModelHistory::ACTION_*
     * @param string $userId User ID to assign this history entry to
     * @param array $options Additional options
     * @return ModelHistory|false
     */
    public function add(EntityInterface $entity, string $action, string $userId = null, array $options = [])
    {
        $options = Hash::merge([
            'dirtyFields' => null,
            'data' => null
        ], $options);

        if (!$options['data']) {
            $options['data'] = $entity->toArray();
        }

        $hiddenProperties = $entity->hiddenProperties();
        if (!empty($hiddenProperties)) {
            $options['data'] = Hash::merge($options['data'], $entity->extract($hiddenProperties));
        }

        $saveFields = [];

        $model = $entity->source();

        $tableConfig = [];
        if (defined('PHPUNIT_TESTSUITE')) {
            if (!in_array($model, ['ArticlesUsersTable', 'ArticlesItemsTable'])) {
                $tableConfig = ['className' => 'ModelHistoryTestApp\Model\Table\\' . $model];
            }
        }

        $saveableFields = TableRegistry::get($model, $tableConfig)->getSaveableFields();

        $entries = [];
        if ($action === ModelHistory::ACTION_COMMENT) {
            $saveFields = [
                'comment' => $options['data']['comment']
            ];
        } else {
            foreach ($saveableFields as $fieldName => $data) {
                if (isset($options['data'][$fieldName])) {
                    if (isset($data['saveParser']) && is_callable($data['saveParser'])) {
                        $callback = $data['saveParser'];
                        $options['data'][$fieldName] = $callback($fieldName, $options['data'][$fieldName], $entity);
                    } else {
                        if (isset($data['type'])) {
                            $filterClass = Transform::get($data['type']);

                            if ($data['type'] == 'association' && isset($data['associationKey'])) {
                                $tableName = Inflector::camelize(Inflector::pluralize(str_replace('_id', '', $fieldName)));

                                $foreignEntity = TableRegistry::get($tableName)->get($entity->$fieldName);

                                $entries[] = $this->newEntity([
                                    'model' => $this->getEntityModel($foreignEntity),
                                    'foreign_key' => $foreignEntity->id,
                                    'action' => $action,
                                    'data' => [$data['associationKey'] => $filterClass->save($fieldName, $data, $entity)],
                                    'revision' => $this->getNextRevisionNumberForEntity($foreignEntity)
                                ]);
                            } else {
                                $options['data'][$fieldName] = $filterClass->save($fieldName, $data, $entity);
                            }
                        }
                    }
                    if ($data['obfuscated'] === true) {
                        $options['data'][$fieldName] = '****************';
                    }
                    $saveFields[$fieldName] = $options['data'][$fieldName];
                }
            }
        }
        if ($action !== ModelHistory::ACTION_DELETE && empty($saveFields)) {
            return false;
        }

        $options['data'] = $saveFields;

        if ($action === ModelHistory::ACTION_DELETE) {
            $options['data'] = [];
        }

        if ($action === ModelHistory::ACTION_UPDATE && $options['dirtyFields']) {
            $newData = [];
            foreach ($options['dirtyFields'] as $field) {
                if (is_array($field)) {
                    continue;
                }
                if (isset($options['data'][$field])) {
                    $newData[$field] = $options['data'][$field];
                }
            }
            $options['data'] = $newData;
        }

        $context = null;
        if (method_exists($entity, 'getHistoryContext')) {
            $context = $entity->getHistoryContext();
        }
        $contextSlug = null;
        if (method_exists($entity, 'getHistoryContextSlug')) {
            $contextSlug = $entity->getHistoryContextSlug();
        }
        $contextType = null;
        if (method_exists($entity, 'getHistoryContextType')) {
            $contextType = $entity->getHistoryContextType();
        }

        $entry = false;
        if (!empty($entries)) {
            foreach ($entries as $entry) {
                $entry = $this->patchEntity($entry, [
                    'context_type' => $contextType,
                    'context' => $context,
                    'context_slug' => $contextSlug,
                    'user_id' => $userId,
                ]);
                $this->save($entry);
            }
        }

        if (!empty($entity->id)) {
            $entry = $this->newEntity([
                'model' => $this->getEntityModel($entity),
                'foreign_key' => $entity->id,
                'action' => $action,
                'data' => $options['data'],
                'context_type' => $contextType,
                'context' => $context,
                'context_slug' => $contextSlug,
                'save_hash' => $entity->save_hash,
                'user_id' => $userId,
                'revision' => $this->getNextRevisionNumberForEntity($entity)
            ]);
            $this->save($entry);
        }

        return $entry;
    }

    /**
     * Transforms data fields to human readable form
     *
     * @param array $history Data
     * @param string $model Model name
     * @return array
     */
    protected function _transformDataFields(array $history, string $model): array
    {
        $tableConfig = [];
        if (defined('PHPUNIT_TESTSUITE')) {
            $tableConfig = ['className' => 'ModelHistoryTestApp\Model\Table\\' . ucfirst($model) . 'Table'];
            $model = ucfirst($model) . 'Table';
        }
        $fieldConfig = TableRegistry::get($model, $tableConfig)->getFields();
        foreach ($history as $index => $entity) {
            $entityData = $entity->data;
            foreach ($entityData as $field => $value) {
                if (!isset($fieldConfig[$field]) || $fieldConfig[$field]['searchable'] !== true) {
                    continue;
                }
                if (isset($fieldConfig[$field]['displayParser']) && is_callable($fieldConfig[$field]['displayParser'])) {
                    $callback = $fieldConfig[$field]['displayParser'];
                    $entityData[$field] = $callback($field, $value, $entity);
                    continue;
                }
                $filterClass = Transform::get($fieldConfig[$field]['type']);
                $entityData[$field] = $filterClass->display($field, $value, $model);
            }
            $history[$index]->data = $entityData;
        }

        return $history;
    }

    /**
     * Add comment
     *
     * @param EntityInterface $entity Entity to add the comment to
     * @param string $comment Comment
     * @param string $userId User which wrote the note
     * @return ModelHistory
     */
    public function addComment(EntityInterface $entity, string $comment, string $userId = null): ModelHistory
    {
        $add = $this->add($entity, ModelHistory::ACTION_COMMENT, $userId, [
            'data' => [
                'comment' => $comment
            ]
        ]);

        return $add;
    }

    /**
     * Handles the revision sequence
     *
     * @param EntityInterface $entity Entity to get the revision number for
     * @return int
     */
    public function getNextRevisionNumberForEntity(EntityInterface $entity): int
    {
        $revision = 1;
        $last = $this->find()
            ->select('revision')
            ->where([
                'model' => $this->getEntityModel($entity),
                'foreign_key' => $entity->id
            ])
            ->order(['revision DESC'])
            ->hydrate(false)
            ->first();

        if (isset($last['revision'])) {
            $revision = $last['revision'] + 1;
        }

        return $revision;
    }

    /**
     * Extracts the string to be saved to the model field from an entity
     *
     * @param EntityInterface $entity Entity
     * @return string
     */
    public function getEntityModel(EntityInterface $entity): string
    {
        $source = $entity->source();
        if (substr($source, -5) == 'Table') {
            $source = substr($source, 0, -5);
        }

        return $source;
    }

    /**
     * GetEntityWithHistory function
     *
     * @param string $model Model
     * @param string $foreignKey ForeignKey
     * @param array $options Options
     * @return \Cake\Datasource\EntityInterface
     */
    public function getEntityWithHistory(string $model, string $foreignKey, array $options = []): EntityInterface
    {
        $tableConfig = [];
        if (defined('PHPUNIT_TESTSUITE')) {
            $tableConfig = ['className' => 'ModelHistoryTestApp\Model\Table\ArticlesTable'];
        }
        $Table = TableRegistry::get($model, $tableConfig);
        $userFields = $Table->getUserNameFields();
        $options = Hash::merge([
            'contain' => [
                'ModelHistory' => [
                    'fields' => [
                        'id',
                        'user_id',
                        'action',
                        'revision',
                        'created',
                        'model',
                        'foreign_key',
                        'data',
                        'context',
                        'context_slug'
                    ],
                    'sort' => ['ModelHistory.revision DESC'],
                    'Users' => [
                        'fields' => $userFields
                    ]
                ]
            ]
        ], $options);
        $entity = $Table->get($foreignKey, $options);

        return $entity;
    }

    /**
     * Get Model History
     *
     * @param string $model Model name
     * @param mixed $foreignKey Foreign key
     * @param int $itemsToShow Amount of items to be shown
     * @param int $page Current position
     * @param array $conditions Additional conditions for find
     * @param array $options Additional options
     * @return array
     */
    public function getModelHistory(string $model, $foreignKey, int $itemsToShow, int $page, array $conditions = [], array $options = []): array
    {
        $conditions = Hash::merge([
            'model' => $model,
            'foreign_key' => $foreignKey
        ], $conditions);
        $options = Hash::merge([
            'includeAssociated' => false
        ], $options);

        if ($options['includeAssociated']) {
            $hashes = $this->find()
                ->select(['save_hash'])
                ->where($conditions);

            $history = $this->find()
                ->where([
                    'save_hash IN' => $hashes
                ]);
        } else {
            $history = $this->find()
                ->where($conditions);
        }

        $history = $history->order([
                'revision' => 'DESC',
                'ModelHistory.created' => 'DESC'
            ])
            ->contain(['Users'])
            ->limit($itemsToShow)
            ->page($page)
            ->toArray();

        return $this->_transformDataFields($history, $model);
    }

    /**
     * Get Model History entries count
     *
     * @param string $model model name
     * @param mixed $foreignKey foreign key
     * @param array $conditions additional conditions for find
     * @return int
     */
    public function getModelHistoryCount(string $model, $foreignKey, array $conditions = [], array $options = []): int
    {
        $conditions = Hash::merge([
            'model' => $model,
            'foreign_key' => $foreignKey
        ], $conditions);
        $options = Hash::merge([
            'includeAssociated' => false
        ], $options);

        if ($options['includeAssociated']) {
            $hashes = $this->find()
                ->select(['save_hash'])
                ->where($conditions);

            $history = $this->find()
                ->where([
                    'save_hash IN' => $hashes
                ]);
        } else {
            $history = $this->find()
                ->where($conditions);
        }

        return $history->count();
    }

    /**
     * Builds a diff for a given history entry
     *
     * @param  ModelHistory  $historyEntry  ModelHistory Entry to build diff for
     * @return array
     */
    public function buildDiff(ModelHistory $historyEntry): array
    {
        if ($historyEntry->revision == 1) {
            return [];
        }

        $previousRevisions = $this->find()
            ->where([
                'model' => $historyEntry->model,
                'foreign_key' => $historyEntry->foreign_key,
                'revision <' => $historyEntry->revision
            ])
            ->order(['revision' => 'DESC'])
            ->toArray();

        $entity = $this->getEntityWithHistory($historyEntry->model, $historyEntry->foreign_key);

        $diffOutput = [
            'changed' => [],
            'changedBefore' => [],
            'unchanged' => []
        ];

        $fieldConfig = TableRegistry::get($historyEntry->model)->getFields();
        $transformers = [];

        // 1. Get old values for changed fields in passed entry, ignore arrays
        foreach ($historyEntry->data as $fieldName => $newValue) {
            foreach ($previousRevisions as $revision) {
                if (isset($revision->data[$fieldName])) {
                    if (!isset($fieldConfig[$fieldName])) {
                        continue 2;
                    }
                    if (isset($fieldConfig[$fieldName]['displayParser']) && is_callable($fieldConfig[$fieldName]['displayParser'])) {
                        $callback = $fieldConfig[$fieldName]['displayParser'];
                        $diffOutput['changed'][$fieldName] = [
                            'old' => $callback($fieldName, $revision->data[$fieldName], $entity),
                            'new' => $callback($fieldName, $newValue, $entity)
                        ];
                        continue 2;
                    }
                    $type = $fieldConfig[$fieldName]['type'];

                    if (!isset($transformers[$type])) {
                        $transformers[$type] = Transform::get($type);
                    }

                    $diffOutput['changed'][$fieldName] = [
                        'old' => $transformers[$type]->display($fieldName, $revision->data[$fieldName], $historyEntry->model),
                        'new' => $transformers[$type]->display($fieldName, $newValue, $historyEntry->model)
                    ];
                    continue 2;
                }
            }
        }

        $currentEntity = TableRegistry::get($historyEntry->model)->get($historyEntry->foreign_key);

        // 2. Try to get old values for any other fields defined in searchableFields and
        foreach ($fieldConfig as $fieldName => $data) {
            foreach ($previousRevisions as $revisionIndex => $revision) {
                if (!isset($revision->data[$fieldName])) {
                    continue;
                }
                if (isset($diffOutput['changed'][$fieldName])) {
                    continue 2;
                }
                if ($revision->data[$fieldName] != $currentEntity->{$fieldName}) {
                    if (isset($data['displayParser']) && is_callable($data['displayParser'])) {
                        $callback = $data['displayParser'];
                        $diffOutput['changedBefore'][$fieldName] = [
                            'old' => $callback($fieldName, $revision->data[$fieldName], $entity),
                            'new' => $callback($fieldName, $currentEntity->{$fieldName}, $entity)
                        ];
                        continue 2;
                    }

                    $type = $data['type'];

                    if (!isset($transformers[$type])) {
                        $transformers[$type] = Transform::get($type);
                    }

                    $diffOutput['changedBefore'][$fieldName] = [
                        'old' => $transformers[$type]->display($fieldName, $revision->data[$fieldName], $historyEntry->model),
                        'new' => $transformers[$type]->display($fieldName, $currentEntity->{$fieldName}, $historyEntry->model)
                    ];
                    continue 2;
                }
            }
        }

        // 3. Get all unchanged fields
        foreach ($fieldConfig as $fieldName => $data) {
            foreach ($previousRevisions as $revision) {
                if (!isset($revision->data[$fieldName])) {
                    continue;
                }
                if (isset($diffOutput['changed'][$fieldName]) || isset($diffOutput['changedBefore'][$fieldName])) {
                    continue 2;
                }

                if (isset($data['displayParser']) && is_callable($data['displayParser'])) {
                    $callback = $data['displayParser'];
                    $diffOutput['unchanged'][$fieldName] = $callback($fieldName, $currentEntity->{$fieldName}, $entity);
                    continue 2;
                }

                $type = $data['type'];

                if (!isset($transformers[$type])) {
                    $transformers[$type] = Transform::get($type);
                }

                $diffOutput['unchanged'][$fieldName] = $transformers[$type]->display($fieldName, $currentEntity->{$fieldName}, $historyEntry->model);
                continue 2;
            }
        }

        // Translate all the fieldnames
        foreach ($diffOutput as $type => $dataArr) {
            if (!empty($dataArr)) {
                foreach ($dataArr as $fieldName => $values) {
                    $localizedField = $this->_translateFieldname($fieldName, $historyEntry->model);
                    if ($localizedField != $fieldName) {
                        $dataArr[$localizedField] = $values;
                        unset($dataArr[$fieldName]);
                    }
                }
                $diffOutput[$type] = $dataArr;
            }
        }

        return $diffOutput;
    }

    /**
     * Try to translate fieldname
     *
     * @param  string  $fieldname  Fieldname
     * @param  string  $model      Model
     * @return string
     */
    protected function _translateFieldname(string $fieldname, string $model): string
    {
        // Try to get the generic model.field translation string
        $localeSlug = strtolower(Inflector::singularize(Inflector::delimit($model))) . '.' . strtolower($fieldname);
        $translatedString = __($localeSlug);

        // Return original value when no translation was made
        if ($localeSlug == $translatedString) {
            return $fieldname;
        }

        return $translatedString;
    }
}
