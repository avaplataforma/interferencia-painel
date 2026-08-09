<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260809_991000_create_organization_poles';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE organization_poles (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, organization_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NULL, code VARCHAR(100) NOT NULL, name VARCHAR(160) NOT NULL, legacy_value VARCHAR(255) NULL, is_primary TINYINT(1) NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY organization_poles_org_code_unique (organization_id, code), UNIQUE KEY organization_poles_unit_unique (unit_id), KEY organization_poles_org_active_idx (organization_id, is_active), CONSTRAINT organization_poles_org_fk FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE, CONSTRAINT organization_poles_unit_fk FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $database->exec("INSERT INTO organization_poles (organization_id, unit_id, code, name, legacy_value, is_primary, is_active) SELECT u.organization_id, u.id, LOWER(REPLACE(REPLACE(TRIM(u.code), ' ', '-'), '_', '-')), u.name, u.name, NOT EXISTS (SELECT 1 FROM units first_unit WHERE first_unit.organization_id=u.organization_id AND first_unit.is_active=1 AND first_unit.id<u.id), u.is_active FROM units u WHERE u.organization_id IS NOT NULL");
        $database->exec("UPDATE organization_poles p INNER JOIN organizations o ON o.id=p.organization_id SET p.legacy_value=o.ava_polo_name WHERE p.is_primary=1 AND NULLIF(TRIM(o.ava_polo_name),'') IS NOT NULL");
        $database->exec('ALTER TABLE student_enrollments ADD organization_pole_id BIGINT UNSIGNED NULL AFTER unit_id, ADD KEY student_enrollments_pole_idx (organization_pole_id), ADD CONSTRAINT student_enrollments_pole_fk FOREIGN KEY (organization_pole_id) REFERENCES organization_poles (id) ON DELETE SET NULL');
        $database->exec('UPDATE student_enrollments e INNER JOIN organization_poles p ON p.unit_id=e.unit_id SET e.organization_pole_id=p.id');
    }

    public function down(PDO $database): void
    {
        $database->exec('ALTER TABLE student_enrollments DROP FOREIGN KEY student_enrollments_pole_fk, DROP INDEX student_enrollments_pole_idx, DROP COLUMN organization_pole_id');
        $database->exec('DROP TABLE organization_poles');
    }
};
