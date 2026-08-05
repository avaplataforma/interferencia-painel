<?php declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string{return '20260805_660000_create_student_enrollment_events';}

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE student_enrollment_events(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,enrollment_id BIGINT UNSIGNED NOT NULL,event_key VARCHAR(160) NOT NULL,event_type VARCHAR(50) NOT NULL,description VARCHAR(500) NOT NULL,created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY student_enrollment_events_key_unique(event_key),KEY student_enrollment_events_enrollment_idx(enrollment_id,created_at),CONSTRAINT student_enrollment_events_enrollment_fk FOREIGN KEY(enrollment_id) REFERENCES student_enrollments(id) ON DELETE CASCADE,CONSTRAINT student_enrollment_events_user_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("INSERT INTO student_enrollment_events(enrollment_id,event_key,event_type,description,created_by,created_at) SELECT id,CONCAT('enrollment-created:',id),'enrollment_created','Matrícula cadastrada no Painel.',created_by,created_at FROM student_enrollments");
        $db->exec("INSERT IGNORE INTO student_enrollment_events(enrollment_id,event_key,event_type,description,created_at) SELECT id,CONCAT('payment-linked:',id,':',finance_payment_id),'charge_created','Cobrança vinculada à matrícula.',updated_at FROM student_enrollments WHERE finance_payment_id IS NOT NULL");
    }

    public function down(PDO $db): void{$db->exec('DROP TABLE IF EXISTS student_enrollment_events');}
};
