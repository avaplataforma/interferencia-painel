<?php declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id():string{return '20260806_710000_create_ava_access_communications';}
    public function up(PDO$db):void
    {
        $db->exec("CREATE TABLE ava_access_communications(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,enrollment_id BIGINT UNSIGNED NOT NULL,channel ENUM('whatsapp','email') NOT NULL,destination VARCHAR(190) NOT NULL,status ENUM('opened','failed') NOT NULL DEFAULT 'opened',error_message VARCHAR(500) NULL,created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY ava_access_communications_enrollment_idx(enrollment_id,created_at),CONSTRAINT ava_access_communications_enrollment_fk FOREIGN KEY(enrollment_id) REFERENCES student_enrollments(id) ON DELETE CASCADE,CONSTRAINT ava_access_communications_user_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(PDO$db):void{$db->exec('DROP TABLE IF EXISTS ava_access_communications');}
};
