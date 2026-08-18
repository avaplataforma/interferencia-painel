<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260818_999995_document_types_per_organization'; }

    public function up(PDO $db): void
    {
        $hasColumn = (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='document_types' AND COLUMN_NAME='organization_id'")->fetchColumn();
        if ($hasColumn === 0) {
            $db->exec("ALTER TABLE document_types ADD organization_id BIGINT UNSIGNED NULL AFTER scope");
            $db->exec("UPDATE document_types SET organization_id=(SELECT MIN(id) FROM organizations)");
        }
        try { $db->exec('ALTER TABLE document_types DROP INDEX document_types_scope_code_unique'); } catch (Throwable) {}
        $db->exec("INSERT INTO document_types(scope,organization_id,code,name,is_required,is_active,sort_order) SELECT dt.scope,o.id,dt.code,dt.name,dt.is_required,dt.is_active,dt.sort_order FROM document_types dt CROSS JOIN organizations o WHERE dt.organization_id=(SELECT MIN(id) FROM organizations) AND o.id<>(SELECT MIN(id) FROM organizations) AND NOT EXISTS(SELECT 1 FROM document_types x WHERE x.organization_id=o.id AND x.code=dt.code)");
        $db->exec("UPDATE document_types SET organization_id=(SELECT MIN(id) FROM organizations) WHERE organization_id IS NULL");
        try { $db->exec('ALTER TABLE document_types ADD UNIQUE KEY document_types_org_code_unique(organization_id,code)'); } catch (Throwable) {}
        try { $db->exec('ALTER TABLE document_types ADD KEY document_types_org_idx(organization_id)'); } catch (Throwable) {}
        try { $db->exec('ALTER TABLE document_types ADD CONSTRAINT document_types_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE'); } catch (Throwable) {}
    }

    public function down(PDO $db): void
    {
        try { $db->exec('ALTER TABLE document_types DROP FOREIGN KEY document_types_org_fk'); } catch (Throwable) {}
        try { $db->exec('ALTER TABLE document_types DROP INDEX document_types_org_code_unique'); } catch (Throwable) {}
        try { $db->exec('ALTER TABLE document_types DROP INDEX document_types_org_idx'); } catch (Throwable) {}
        try { $db->exec('ALTER TABLE document_types DROP COLUMN organization_id'); } catch (Throwable) {}
        $db->exec('ALTER TABLE document_types ADD UNIQUE KEY document_types_scope_code_unique(scope,code)');
    }
};
