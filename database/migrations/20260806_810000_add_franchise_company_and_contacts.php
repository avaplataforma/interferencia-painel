<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260806_810000_add_franchise_company_and_contacts';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE organizations
            ADD cnpj CHAR(14) NULL AFTER display_name,
            ADD state_registration VARCHAR(40) NULL AFTER cnpj,
            ADD municipal_registration VARCHAR(40) NULL AFTER state_registration,
            ADD postal_code VARCHAR(9) NULL AFTER municipal_registration,
            ADD address VARCHAR(190) NULL AFTER postal_code,
            ADD address_number VARCHAR(30) NULL AFTER address,
            ADD address_complement VARCHAR(120) NULL AFTER address_number,
            ADD neighborhood VARCHAR(120) NULL AFTER address_complement,
            ADD city VARCHAR(120) NULL AFTER neighborhood,
            ADD state CHAR(2) NULL AFTER city,
            ADD manager_name VARCHAR(160) NULL AFTER state,
            ADD manager_document CHAR(11) NULL AFTER manager_name,
            ADD manager_email VARCHAR(190) NULL AFTER manager_document,
            ADD manager_phone VARCHAR(20) NULL AFTER manager_email,
            ADD general_manager_name VARCHAR(160) NULL AFTER manager_phone,
            ADD general_manager_document CHAR(11) NULL AFTER general_manager_name,
            ADD general_manager_email VARCHAR(190) NULL AFTER general_manager_document,
            ADD general_manager_phone VARCHAR(20) NULL AFTER general_manager_email,
            ADD UNIQUE KEY organizations_cnpj_unique(cnpj)");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE organizations
            DROP INDEX organizations_cnpj_unique,
            DROP COLUMN general_manager_phone,
            DROP COLUMN general_manager_email,
            DROP COLUMN general_manager_document,
            DROP COLUMN general_manager_name,
            DROP COLUMN manager_phone,
            DROP COLUMN manager_email,
            DROP COLUMN manager_document,
            DROP COLUMN manager_name,
            DROP COLUMN state,
            DROP COLUMN city,
            DROP COLUMN neighborhood,
            DROP COLUMN address_complement,
            DROP COLUMN address_number,
            DROP COLUMN address,
            DROP COLUMN postal_code,
            DROP COLUMN municipal_registration,
            DROP COLUMN state_registration,
            DROP COLUMN cnpj");
    }
};
