<?php
declare(strict_types=1);
use Interferencia\Kernel\Database\Migration;
return new class implements Migration {
 public function id():string{return '20260731_260000_add_external_form_fields';}
 public function up(PDO $db):void{$db->exec('ALTER TABLE crm_contacts ADD external_submission_id VARCHAR(190) NULL AFTER registration_source, ADD consent_at DATETIME NULL AFTER external_submission_id, ADD privacy_notice_version VARCHAR(50) NULL AFTER consent_at, ADD UNIQUE KEY crm_contacts_external_submission_unique (external_submission_id)');$db->exec('CREATE TABLE external_form_rate_limits (fingerprint CHAR(64) NOT NULL, window_started_at DATETIME NOT NULL, request_count SMALLINT UNSIGNED NOT NULL DEFAULT 1, PRIMARY KEY (fingerprint,window_started_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');}
 public function down(PDO $db):void{$db->exec('DROP TABLE IF EXISTS external_form_rate_limits');$db->exec('ALTER TABLE crm_contacts DROP INDEX crm_contacts_external_submission_unique, DROP COLUMN privacy_notice_version, DROP COLUMN consent_at, DROP COLUMN external_submission_id');}
};
