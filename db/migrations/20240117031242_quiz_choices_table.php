<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class QuizChoicesTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('quiz_choices');
        $table->addColumn('user_id', 'integer', ['signed' => false])
              ->addColumn('quiz_id', 'integer', ['signed' => false])
              ->addColumn('bet_amount', 'integer')
              ->addColumn('choice', 'integer')
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['id'], ['unique' => true])
              ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
              ->addForeignKey('quiz_id', 'quiz', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
              ->create();
    }
}
