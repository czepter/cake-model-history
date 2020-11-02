<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateModelHistory extends AbstractMigration
{
    private $tableName = 'model_history';
    
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function up()
    {
        $table = $this->table($this->tableName, [
            'id' => false, 'primary_key' => ['id']
        ]);

        $table
            ->addColumn('id', 'uuid', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('model', 'string', [
                'comment' => 'e.g. \"Installation\"',
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('foreign_key', 'uuid', [
                'comment' => 'uuid',
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('user_id', 'uuid', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('action', 'string', [
                'comment' => 'e.g. \"create\", \"update\", \"delete\"',
                'default' => null,
                'limit' => 45,
                'null' => true,
            ])
            ->addColumn('data', 'text', [
                'comment' => 'JSON text, schema per action',
                'default' => null,
                'limit' => 16777215,
                'null' => true,
            ])
            ->addColumn('context', 'text', [
                'comment' => 'JSON text, schema per action',
                'default' => null,
                'limit' => 16777215,
                'null' => true,
            ])
            ->addColumn('context_type', 'string', [
                'comment' => 'e.g. \"controller\", \"shell\"',
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('context_slug', 'string', [
                'comment' => 'e.g. \"Admin/Users/reset_password\"',
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('save_hash', 'string', [
                'default' => null,
                'length' => 40,
                'null' => true,
            ])
            ->addColumn('revision', 'integer', [
                'default' => null,
                'limit' => 8,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->create();
        
    }

    public function down(): void
    {
        $this->table($this->$tableName)
            ->drop;
    }
}
