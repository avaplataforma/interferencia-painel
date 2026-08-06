<?php
declare(strict_types=1);
use Interferencia\Kernel\Database\Migration;
return new class implements Migration {
 public function id():string{return'20260806_760000_scope_finance_and_students_by_organization';}
 public function up(PDO$db):void{
  $org=(int)$db->query("SELECT id FROM organizations WHERE code='interferencia' LIMIT 1")->fetchColumn();if($org<1)throw new RuntimeException('Organizacao inicial nao encontrada.');
  foreach(['finance_customers','finance_payments','finance_subscriptions','student_enrollments']as$table){$db->exec("ALTER TABLE {$table} ADD organization_id BIGINT UNSIGNED NULL AFTER id");$db->exec("UPDATE {$table} SET organization_id={$org}");$db->exec("ALTER TABLE {$table} MODIFY organization_id BIGINT UNSIGNED NOT NULL DEFAULT {$org},ADD KEY {$table}_org_idx(organization_id),ADD CONSTRAINT {$table}_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id)");}
  $db->exec('ALTER TABLE finance_customers DROP INDEX finance_customers_asaas_unique,ADD UNIQUE KEY finance_customers_org_asaas_unique(organization_id,asaas_customer_id)');
  $db->exec('ALTER TABLE finance_payments DROP INDEX finance_payments_asaas_unique,ADD UNIQUE KEY finance_payments_org_asaas_unique(organization_id,asaas_payment_id)');
  $db->exec('ALTER TABLE finance_subscriptions DROP INDEX finance_subscriptions_asaas_unique,ADD UNIQUE KEY finance_subscriptions_org_asaas_unique(organization_id,asaas_subscription_id)');
 }
 public function down(PDO$db):void{
  $db->exec('ALTER TABLE student_enrollments DROP FOREIGN KEY student_enrollments_org_fk,DROP INDEX student_enrollments_org_idx,DROP COLUMN organization_id');
  $db->exec('ALTER TABLE finance_subscriptions DROP FOREIGN KEY finance_subscriptions_org_fk,DROP INDEX finance_subscriptions_org_asaas_unique,DROP INDEX finance_subscriptions_org_idx,DROP COLUMN organization_id,ADD UNIQUE KEY finance_subscriptions_asaas_unique(asaas_subscription_id)');
  $db->exec('ALTER TABLE finance_payments DROP FOREIGN KEY finance_payments_org_fk,DROP INDEX finance_payments_org_asaas_unique,DROP INDEX finance_payments_org_idx,DROP COLUMN organization_id,ADD UNIQUE KEY finance_payments_asaas_unique(asaas_payment_id)');
  $db->exec('ALTER TABLE finance_customers DROP FOREIGN KEY finance_customers_org_fk,DROP INDEX finance_customers_org_asaas_unique,DROP INDEX finance_customers_org_idx,DROP COLUMN organization_id,ADD UNIQUE KEY finance_customers_asaas_unique(asaas_customer_id)');
 }
};
