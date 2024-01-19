<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class QuizTable extends AbstractMigration
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
        $table = $this->table('quiz');
        $table->addColumn('status', 'integer', ['default' => 0])
            ->addColumn('amount', 'integer', ['default' => 0])
            ->addColumn('theme', 'string', ['limit' => 100])
            ->addColumn('question', 'string', ['default' => null, 'null' => true, 'limit' => 255])
            ->addColumn('alternatives', 'json')
            ->addColumn('truth', 'integer', ['null' => true])
            ->addColumn('voice_url', 'string', ['null' => true, 'default' => null])
            ->addIndex(['id'], ['unique' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();
    }
}
