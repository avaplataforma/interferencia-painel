<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260807_920000_link_organizations_to_contract_history'; }
    public function up(PDO$db):void
    {
        $db->exec("UPDATE franchise_applications a INNER JOIN organizations o ON o.cnpj=a.cnpj SET a.organization_id=o.id,a.status='approved',a.reviewed_at=COALESCE(a.reviewed_at,NOW()) WHERE a.organization_id IS NULL");
        $db->exec("INSERT INTO franchise_applications(public_token,organization_id,display_name,legal_name,cnpj,state_registration,municipal_registration,postal_code,address,address_number,address_complement,neighborhood,city,state,manager_name,manager_document,manager_email,manager_phone,general_manager_name,general_manager_email,general_manager_phone,site_host,status,submitted_at,reviewed_at) SELECT SHA2(CONCAT(UUID(),':',o.id,':',RAND()),256),o.id,o.display_name,o.legal_name,o.cnpj,o.state_registration,o.municipal_registration,o.postal_code,o.address,o.address_number,o.address_complement,o.neighborhood,o.city,o.state,o.manager_name,o.manager_document,o.manager_email,o.manager_phone,o.general_manager_name,o.general_manager_email,o.general_manager_phone,d.host,'approved',NOW(),NOW() FROM organizations o LEFT JOIN organization_domains d ON d.organization_id=o.id AND d.is_primary=1 AND d.purpose='site' WHERE NOT EXISTS(SELECT 1 FROM franchise_applications a WHERE a.organization_id=o.id)");
    }
    public function down(PDO$db):void{}
};
