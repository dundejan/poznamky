<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019100319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the note table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE note (
              id INT AUTO_INCREMENT NOT NULL,
              title VARCHAR(200) NOT NULL,
              content TEXT NOT NULL,
              priority VARCHAR(16) NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              INDEX idx_note_priority_updated_at (priority, updated_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE note');
    }
}
