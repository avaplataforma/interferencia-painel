<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260813_000020_link_documents_to_entities'; }

    public function up(PDO $db): void
    {
        $db->exec("ALTER TABLE managed_documents ADD COLUMN entity_type VARCHAR(40) NULL AFTER category, ADD COLUMN entity_id BIGINT UNSIGNED NULL AFTER entity_type, ADD KEY managed_documents_entity_idx(scope,organization_id,entity_type,entity_id,deleted_at,created_at)");
    }

    public function down(PDO $db): void
    {
        $db->exec('ALTER TABLE managed_documents DROP INDEX managed_documents_entity_idx, DROP COLUMN entity_id, DROP COLUMN entity_type');
    }
};
