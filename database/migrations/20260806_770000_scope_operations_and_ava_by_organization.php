<?php
declare(strict_types=1);
use Interferencia\Kernel\Database\Migration;
return new class implements Migration {
 public function id():string{return'20260806_770000_scope_operations_and_ava_by_organization';}
 public function up(PDO$db):void{
  $org=(int)$db->query("SELECT id FROM organizations WHERE code='interferencia' LIMIT 1")->fetchColumn();if($org<1)throw new RuntimeException('Organizacao inicial nao encontrada.');
  $tables=['whatsapp_lines','whatsapp_messages','whatsapp_webhook_events','whatsapp_templates','tickets','ticket_departments','moodle_integrations','moodle_courses','moodle_users','moodle_enrolments','moodle_profile_fields'];
  foreach($tables as$table){$db->exec("ALTER TABLE {$table} ADD organization_id BIGINT UNSIGNED NULL AFTER id");$db->exec("UPDATE {$table} SET organization_id={$org}");$db->exec("ALTER TABLE {$table} MODIFY organization_id BIGINT UNSIGNED NOT NULL DEFAULT {$org},ADD KEY {$table}_org_idx(organization_id),ADD CONSTRAINT {$table}_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id)");}
  $db->exec('ALTER TABLE whatsapp_lines DROP INDEX whatsapp_lines_phone_unique,ADD UNIQUE KEY whatsapp_lines_org_phone_unique(organization_id,phone_e164),DROP INDEX whatsapp_lines_phone_number_id_unique,ADD UNIQUE KEY whatsapp_lines_org_phone_id_unique(organization_id,phone_number_id)');
  $db->exec('ALTER TABLE whatsapp_messages DROP INDEX whatsapp_messages_wamid_unique,ADD UNIQUE KEY whatsapp_messages_org_wamid_unique(organization_id,wamid)');
  $db->exec('ALTER TABLE whatsapp_webhook_events DROP INDEX whatsapp_webhook_event_unique,ADD UNIQUE KEY whatsapp_webhook_org_event_unique(organization_id,event_key)');
  $db->exec('ALTER TABLE whatsapp_templates DROP INDEX whatsapp_templates_meta_name_unique,ADD UNIQUE KEY whatsapp_templates_org_meta_unique(organization_id,meta_name)');
  $db->exec('ALTER TABLE ticket_departments DROP INDEX ticket_departments_name_unique,ADD UNIQUE KEY ticket_departments_org_name_unique(organization_id,name)');
  $db->exec('ALTER TABLE moodle_integrations ADD UNIQUE KEY moodle_integrations_org_unique(organization_id)');
  $db->exec('ALTER TABLE moodle_courses DROP INDEX moodle_courses_external_unique,ADD UNIQUE KEY moodle_courses_org_external_unique(organization_id,moodle_course_id)');
  $db->exec('ALTER TABLE moodle_users DROP INDEX moodle_users_external_unique,ADD UNIQUE KEY moodle_users_org_external_unique(organization_id,moodle_user_id)');
  $db->exec('ALTER TABLE moodle_enrolments DROP INDEX moodle_enrolments_unique,ADD UNIQUE KEY moodle_enrolments_org_unique(organization_id,moodle_course_id,moodle_user_id)');
  $db->exec('ALTER TABLE moodle_profile_fields DROP INDEX moodle_profile_fields_shortname_unique,ADD UNIQUE KEY moodle_profile_fields_org_shortname_unique(organization_id,shortname)');
 }
 public function down(PDO$db):void{
  $db->exec('ALTER TABLE moodle_profile_fields DROP FOREIGN KEY moodle_profile_fields_org_fk,DROP INDEX moodle_profile_fields_org_shortname_unique,DROP INDEX moodle_profile_fields_org_idx,DROP COLUMN organization_id,ADD UNIQUE KEY moodle_profile_fields_shortname_unique(shortname)');
  $db->exec('ALTER TABLE moodle_enrolments DROP FOREIGN KEY moodle_enrolments_org_fk,DROP INDEX moodle_enrolments_org_unique,DROP INDEX moodle_enrolments_org_idx,DROP COLUMN organization_id,ADD UNIQUE KEY moodle_enrolments_unique(moodle_course_id,moodle_user_id)');
  $db->exec('ALTER TABLE moodle_users DROP FOREIGN KEY moodle_users_org_fk,DROP INDEX moodle_users_org_external_unique,DROP INDEX moodle_users_org_idx,DROP COLUMN organization_id,ADD UNIQUE KEY moodle_users_external_unique(moodle_user_id)');
  $db->exec('ALTER TABLE moodle_courses DROP FOREIGN KEY moodle_courses_org_fk,DROP INDEX moodle_courses_org_external_unique,DROP INDEX moodle_courses_org_idx,DROP COLUMN organization_id,ADD UNIQUE KEY moodle_courses_external_unique(moodle_course_id)');
  $db->exec('ALTER TABLE moodle_integrations DROP FOREIGN KEY moodle_integrations_org_fk,DROP INDEX moodle_integrations_org_unique,DROP INDEX moodle_integrations_org_idx,DROP COLUMN organization_id');
  $db->exec('ALTER TABLE ticket_departments DROP FOREIGN KEY ticket_departments_org_fk,DROP INDEX ticket_departments_org_name_unique,DROP INDEX ticket_departments_org_idx,DROP COLUMN organization_id,ADD UNIQUE KEY ticket_departments_name_unique(name)');
  $db->exec('ALTER TABLE tickets DROP FOREIGN KEY tickets_org_fk,DROP INDEX tickets_org_idx,DROP COLUMN organization_id');
  $db->exec('ALTER TABLE whatsapp_templates DROP FOREIGN KEY whatsapp_templates_org_fk,DROP INDEX whatsapp_templates_org_meta_unique,DROP INDEX whatsapp_templates_org_idx,DROP COLUMN organization_id,ADD UNIQUE KEY whatsapp_templates_meta_name_unique(meta_name)');
  $db->exec('ALTER TABLE whatsapp_webhook_events DROP FOREIGN KEY whatsapp_webhook_events_org_fk,DROP INDEX whatsapp_webhook_org_event_unique,DROP INDEX whatsapp_webhook_events_org_idx,DROP COLUMN organization_id,ADD UNIQUE KEY whatsapp_webhook_event_unique(event_key)');
  $db->exec('ALTER TABLE whatsapp_messages DROP FOREIGN KEY whatsapp_messages_org_fk,DROP INDEX whatsapp_messages_org_wamid_unique,DROP INDEX whatsapp_messages_org_idx,DROP COLUMN organization_id,ADD UNIQUE KEY whatsapp_messages_wamid_unique(wamid)');
  $db->exec('ALTER TABLE whatsapp_lines DROP FOREIGN KEY whatsapp_lines_org_fk,DROP INDEX whatsapp_lines_org_phone_unique,DROP INDEX whatsapp_lines_org_phone_id_unique,DROP INDEX whatsapp_lines_org_idx,DROP COLUMN organization_id,ADD UNIQUE KEY whatsapp_lines_phone_unique(phone_e164),ADD UNIQUE KEY whatsapp_lines_phone_number_id_unique(phone_number_id)');
 }
};
