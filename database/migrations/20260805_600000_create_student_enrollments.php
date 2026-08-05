<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string{return '20260805_600000_create_student_enrollments';}
    public function up(PDO$db):void
    {
        $db->exec("CREATE TABLE student_enrollments(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,finance_customer_id BIGINT UNSIGNED NOT NULL,moodle_course_id BIGINT UNSIGNED NOT NULL,finance_product_id BIGINT UNSIGNED NULL,unit_id BIGINT UNSIGNED NOT NULL,status VARCHAR(30) NOT NULL DEFAULT 'awaiting_charge',moodle_enrolment_status VARCHAR(30) NOT NULL DEFAULT 'not_released',created_by BIGINT UNSIGNED NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY student_enrollments_unit_status_idx(unit_id,status),KEY student_enrollments_customer_idx(finance_customer_id),CONSTRAINT student_enrollments_customer_fk FOREIGN KEY(finance_customer_id) REFERENCES finance_customers(id),CONSTRAINT student_enrollments_course_fk FOREIGN KEY(moodle_course_id) REFERENCES moodle_courses(id),CONSTRAINT student_enrollments_product_fk FOREIGN KEY(finance_product_id) REFERENCES finance_products(id) ON DELETE SET NULL,CONSTRAINT student_enrollments_unit_fk FOREIGN KEY(unit_id) REFERENCES units(id),CONSTRAINT student_enrollments_creator_fk FOREIGN KEY(created_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(PDO$db):void{$db->exec('DROP TABLE IF EXISTS student_enrollments');}
};
