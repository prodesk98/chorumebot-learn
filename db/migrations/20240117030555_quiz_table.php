<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class QuizTable extends AbstractMigration
{
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
