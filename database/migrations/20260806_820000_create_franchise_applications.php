<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260806_820000_create_franchise_applications'; }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE franchise_applications(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_token CHAR(64) NOT NULL,organization_id BIGINT UNSIGNED NULL,display_name VARCHAR(160) NULL,legal_name VARCHAR(190) NULL,cnpj CHAR(14) NULL,state_registration VARCHAR(40) NULL,municipal_registration VARCHAR(40) NULL,postal_code VARCHAR(12) NULL,address VARCHAR(190) NULL,address_number VARCHAR(30) NULL,address_complement VARCHAR(120) NULL,neighborhood VARCHAR(120) NULL,city VARCHAR(120) NULL,state CHAR(2) NULL,manager_name VARCHAR(160) NULL,manager_document CHAR(11) NULL,manager_email VARCHAR(190) NULL,manager_phone VARCHAR(30) NULL,general_manager_name VARCHAR(160) NULL,general_manager_email VARCHAR(190) NULL,general_manager_phone VARCHAR(30) NULL,site_host VARCHAR(253) NULL,negotiation_notes TEXT NULL,billing_required TINYINT(1) NOT NULL DEFAULT 0,status VARCHAR(24) NOT NULL DEFAULT 'invited',contract_status VARCHAR(24) NOT NULL DEFAULT 'pending_definition',billing_status VARCHAR(24) NOT NULL DEFAULT 'pending_definition',submitted_at DATETIME NULL,reviewed_at DATETIME NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY franchise_applications_token_unique(public_token),UNIQUE KEY franchise_applications_cnpj_unique(cnpj),KEY franchise_applications_status_idx(status,updated_at),CONSTRAINT franchise_applications_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE SET NULL,CONSTRAINT franchise_applications_status_check CHECK(status IN('invited','submitted','reviewing','approved','rejected','cancelled'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE platform_tickets(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,franchise_application_id BIGINT UNSIGNED NULL,subject VARCHAR(180) NOT NULL,requester_name VARCHAR(160) NOT NULL,requester_email VARCHAR(190) NULL,description TEXT NOT NULL,priority VARCHAR(20) NOT NULL DEFAULT 'normal',status VARCHAR(20) NOT NULL DEFAULT 'open',created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY platform_tickets_status_idx(status,updated_at),CONSTRAINT platform_tickets_application_fk FOREIGN KEY(franchise_application_id) REFERENCES franchise_applications(id) ON DELETE SET NULL,CONSTRAINT platform_tickets_priority_check CHECK(priority IN('low','normal','high','urgent')),CONSTRAINT platform_tickets_status_check CHECK(status IN('open','in_progress','waiting','resolved','closed'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS platform_tickets,franchise_applications');
    }
};
