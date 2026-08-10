<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260810_999900_create_organization_catalog_access';
    }

    public function up(PDO $database): void
    {
        $database->exec("INSERT INTO course_catalogs(code,name,description,is_active)
            VALUES('ava-cursos','AVA Cursos','Catálogo próprio e central do Mundo Inter, entregue pelo AVA Cursos.',1)
            ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),is_active=1");

        $database->exec("CREATE TABLE organization_course_catalog_access(
            organization_id BIGINT UNSIGNED NOT NULL,
            course_catalog_id BIGINT UNSIGNED NOT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 0,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(organization_id,course_catalog_id),
            KEY organization_catalog_enabled_index(organization_id,is_enabled),
            CONSTRAINT organization_catalog_access_organization_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT organization_catalog_access_catalog_fk FOREIGN KEY(course_catalog_id) REFERENCES course_catalogs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("INSERT INTO organization_course_catalog_access(organization_id,course_catalog_id,is_enabled)
            SELECT organization.id,catalog.id,1
            FROM organizations organization
            CROSS JOIN course_catalogs catalog
            WHERE catalog.is_active=1");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS organization_course_catalog_access');
        $database->exec("DELETE FROM course_catalogs WHERE code='ava-cursos'");
    }
};
