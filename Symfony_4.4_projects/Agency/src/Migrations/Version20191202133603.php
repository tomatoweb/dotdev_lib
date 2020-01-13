<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191202133603 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE property ADD created DATETIME , DROP created_at, CHANGE rooms rooms SMALLINT UNSIGNED DEFAULT 1, CHANGE bedrooms bedrooms SMALLINT UNSIGNED DEFAULT 1, CHANGE price price VARCHAR(255) DEFAULT \'100000\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE property ADD created_at DATETIME DEFAULT NULL, DROP created, CHANGE rooms rooms SMALLINT UNSIGNED DEFAULT 1, CHANGE bedrooms bedrooms SMALLINT UNSIGNED DEFAULT 1, CHANGE price price VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'100000\' COLLATE `utf8mb4_unicode_ci`');
    }
}
