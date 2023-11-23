<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231122212945 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE address (id INT AUTO_INCREMENT NOT NULL, numero INT DEFAULT NULL, voie VARCHAR(255) DEFAULT NULL, cdp VARCHAR(5) DEFAULT NULL, ville VARCHAR(255) NOT NULL, gps_latitude DOUBLE PRECISION DEFAULT NULL, gps_longitude VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE companies ADD address_id INT NOT NULL, DROP adresse');
        $this->addSql('ALTER TABLE companies ADD CONSTRAINT FK_8244AA3AF5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8244AA3AF5B7AF75 ON companies (address_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE companies DROP FOREIGN KEY FK_8244AA3AF5B7AF75');
        $this->addSql('DROP TABLE address');
        $this->addSql('DROP INDEX UNIQ_8244AA3AF5B7AF75 ON companies');
        $this->addSql('ALTER TABLE companies ADD adresse LONGTEXT DEFAULT NULL, DROP address_id');
    }
}
