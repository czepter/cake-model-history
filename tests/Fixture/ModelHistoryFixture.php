<?php
declare(strict_types = 1);
namespace ModelHistory\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * ModelHistoryFixture
 *
 */
class ModelHistoryFixture extends TestFixture
{

    /**
     * Table name
     *
     * @var string
     */
    public $table = 'model_history';

    /**
     * Fields
     *
     * @var array
     */
    // @codingStandardsIgnoreStart
    public $fields = [
        'id' => ['type' => 'uuid', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'model' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null, 'comment' => 'e.g. "Installation"', 'precision' => null, 'fixed' => null],
        'foreign_key' => ['type' => 'uuid', 'length' => null, 'null' => true, 'default' => null, 'comment' => 'uuid', 'precision' => null],
        'user_id' => ['type' => 'uuid', 'length' => null, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'action' => ['type' => 'string', 'length' => 45, 'null' => true, 'default' => null, 'comment' => 'e.g. "create", "update", "delete"', 'precision' => null, 'fixed' => null],
        'data' => ['type' => 'binary', 'length' => null, 'null' => true, 'default' => null, 'comment' => 'JSON blob, schema per action', 'precision' => null],
        'context' => ['type' => 'binary', 'length' => null, 'null' => true, 'default' => null, 'comment' => 'JSON blob, schema per action', 'precision' => null],
        'context_slug' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null, 'comment' => 'e.g. "create", "update", "delete"', 'precision' => null, 'fixed' => null],
        'context_type' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null, 'comment' => 'e.g. "create", "update", "delete"', 'precision' => null, 'fixed' => null],
        'save_hash' => ['type' => 'string', 'length' => 40, 'null' => true, 'default' => null, 'comment' => 'e.g. "create", "update", "delete"', 'precision' => null, 'fixed' => null],
        'revision' => ['type' => 'integer', 'length' => 8, 'unsigned' => false, 'null' => false, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        'created' => ['type' => 'datetime', 'length' => null, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB', 'collation' => 'utf8_general_ci'
        ],
    ];
    // @codingStandardsIgnoreEnd

    /**
     * Records
     *
     * @var array
     */
    public $records = [
    ];
}
