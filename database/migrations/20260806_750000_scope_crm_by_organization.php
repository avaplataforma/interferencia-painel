<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260806_750000_scope_crm_by_organization'; }

    public function up(PDO $db): void
    {
        $db->exec('ALTER TABLE crm_statuses ADD organization_id BIGINT UNSIGNED NULL AFTER id');
        $db->exec('ALTER TABLE crm_tags ADD organization_id BIGINT UNSIGNED NULL AFTER id');
        $db->exec('ALTER TABLE crm_contacts ADD organization_id BIGINT UNSIGNED NULL AFTER id');
        $db->exec('ALTER TABLE external_forms ADD organization_id BIGINT UNSIGNED NULL AFTER id');

        $db->exec('UPDATE crm_contacts c INNER JOIN units u ON u.id=c.unit_id SET c.organization_id=u.organization_id');
        $db->exec('UPDATE crm_statuses SET organization_id=(SELECT id FROM organizations WHERE code=\'interferencia\' LIMIT 1) WHERE organization_id IS NULL');
        $db->exec('UPDATE crm_tags SET organization_id=(SELECT id FROM organizations WHERE code=\'interferencia\' LIMIT 1) WHERE organization_id IS NULL');
        $db->exec('UPDATE external_forms f INNER JOIN crm_tags t ON t.id=f.tag_id SET f.organization_id=t.organization_id');

        $db->exec('ALTER TABLE crm_statuses MODIFY organization_id BIGINT UNSIGNED NOT NULL, DROP INDEX crm_statuses_code_unique, ADD UNIQUE KEY crm_statuses_org_code_unique(organization_id,code), ADD CONSTRAINT crm_statuses_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id)');
        $db->exec('ALTER TABLE crm_tags MODIFY organization_id BIGINT UNSIGNED NOT NULL, DROP INDEX crm_tags_name_unique, ADD UNIQUE KEY crm_tags_org_name_unique(organization_id,name), ADD CONSTRAINT crm_tags_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id)');
        $db->exec('ALTER TABLE crm_contacts MODIFY organization_id BIGINT UNSIGNED NOT NULL, DROP INDEX crm_contacts_external_submission_unique, ADD UNIQUE KEY crm_contacts_org_submission_unique(organization_id,external_submission_id), ADD KEY crm_contacts_org_unit_idx(organization_id,unit_id), ADD CONSTRAINT crm_contacts_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id)');
        $db->exec('ALTER TABLE external_forms MODIFY organization_id BIGINT UNSIGNED NOT NULL, DROP INDEX external_forms_slug_unique, ADD UNIQUE KEY external_forms_org_slug_unique(organization_id,slug), ADD CONSTRAINT external_forms_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id)');
    }

    public function down(PDO $db): void
    {
        $db->exec('ALTER TABLE external_forms DROP FOREIGN KEY external_forms_org_fk, DROP INDEX external_forms_org_slug_unique, DROP COLUMN organization_id, ADD UNIQUE KEY external_forms_slug_unique(slug)');
        $db->exec('ALTER TABLE crm_contacts DROP FOREIGN KEY crm_contacts_org_fk, DROP INDEX crm_contacts_org_submission_unique, DROP INDEX crm_contacts_org_unit_idx, DROP COLUMN organization_id, ADD UNIQUE KEY crm_contacts_external_submission_unique(external_submission_id)');
        $db->exec('ALTER TABLE crm_tags DROP FOREIGN KEY crm_tags_org_fk, DROP INDEX crm_tags_org_name_unique, DROP COLUMN organization_id, ADD UNIQUE KEY crm_tags_name_unique(name)');
        $db->exec('ALTER TABLE crm_statuses DROP FOREIGN KEY crm_statuses_org_fk, DROP INDEX crm_statuses_org_code_unique, DROP COLUMN organization_id, ADD UNIQUE KEY crm_statuses_code_unique(code)');
    }
};
