<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260818_999995_document_types_per_organization'; }

    public function up(PDO $db): void
    {
        $db->exec("ALTER TABLE document_types ADD organization_id BIGINT UNSIGNED NULL AFTER scope");
        $db->exec("UPDATE document_types SET organization_id=(SELECT MIN(id) FROM organizations)");
        $db->exec("INSERT INTO document_types(scope,organization_id,code,name,is_required,is_active,sort_order) SELECT dt.scope,o.id,dt.code,dt.name,dt.is_required,dt.is_active,dt.sort_order FROM document_types dt CROSS JOIN organizations o WHERE dt.organization_id=(SELECT MIN(id) FROM organizations) AND o.id<>(SELECT MIN(id) FROM organizations)");
        $db->exec("ALTER TABLE document_types MODIFY organization_id BIGINT UNSIGNED NOT NULL,ADD UNIQUE KEY document_types_org_code_unique(organization_id,code),ADD KEY document_types_org_idx(organization_id),ADD CONSTRAINT document_types_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE");
    }

    public function down(PDO $db): void
    {
        $db->exec('ALTER TABLE document_types DROP FOREIGN KEY document_types_org_fk,DROP INDEX document_types_org_code_unique,DROP INDEX document_types_org_idx,DROP COLUMN organization_id');
    }
};
