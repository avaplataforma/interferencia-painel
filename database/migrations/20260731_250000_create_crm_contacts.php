<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260731_250000_create_crm_contacts'; }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE crm_statuses (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, code VARCHAR(64) NOT NULL, name VARCHAR(100) NOT NULL, color VARCHAR(7) NOT NULL, sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (id), UNIQUE KEY crm_statuses_code_unique (code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $database->exec("INSERT INTO crm_statuses (code, name, color, sort_order) VALUES ('new', 'Novo', '#ed1c24', 10), ('negotiation', 'Negociação', '#0d6efd', 20), ('not_interested', 'Sem interesse', '#d9a62e', 30), ('enrolled', 'Matriculado', '#198754', 40)");
        $database->exec("CREATE TABLE crm_contacts (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, unit_id BIGINT UNSIGNED NOT NULL, status_id BIGINT UNSIGNED NOT NULL, responsible_user_id BIGINT UNSIGNED NULL, name VARCHAR(160) NOT NULL, phone VARCHAR(32) NULL, email VARCHAR(190) NULL, document VARCHAR(24) NULL, course VARCHAR(160) NULL, interest_score TINYINT UNSIGNED NULL, origin_city VARCHAR(120) NULL, registration_source VARCHAR(32) NOT NULL DEFAULT 'internal', registered_at DATETIME NOT NULL, notes TEXT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY crm_contacts_unit_name_index (unit_id, name), KEY crm_contacts_status_index (status_id), KEY crm_contacts_phone_index (phone), CONSTRAINT crm_contacts_unit_fk FOREIGN KEY (unit_id) REFERENCES units (id), CONSTRAINT crm_contacts_status_fk FOREIGN KEY (status_id) REFERENCES crm_statuses (id), CONSTRAINT crm_contacts_responsible_fk FOREIGN KEY (responsible_user_id) REFERENCES users (id) ON DELETE SET NULL, CONSTRAINT crm_contacts_creator_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL, CONSTRAINT crm_contacts_interest_check CHECK (interest_score IS NULL OR interest_score BETWEEN 0 AND 10), CONSTRAINT crm_contacts_source_check CHECK (registration_source IN ('internal', 'external_form'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $database->exec("INSERT IGNORE INTO permissions (code, name) VALUES ('crm.contacts.view', 'Visualizar contatos do CRM'), ('crm.contacts.manage', 'Gerenciar contatos do CRM')");
        $database->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code IN ('super_admin', 'manager', 'agent') AND p.code IN ('crm.contacts.view', 'crm.contacts.manage')");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS crm_contacts');
        $database->exec('DROP TABLE IF EXISTS crm_statuses');
        $database->exec("DELETE FROM permissions WHERE code IN ('crm.contacts.view', 'crm.contacts.manage')");
    }
};
