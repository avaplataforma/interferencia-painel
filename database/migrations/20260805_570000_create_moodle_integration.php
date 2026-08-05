<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id():string{return'20260805_570000_create_moodle_integration';}
    public function up(PDO$db):void
    {
        $db->exec("INSERT IGNORE INTO permissions(code,name) VALUES('moodle.settings.manage','Gerenciar integração Moodle')");
        $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='super_admin' AND p.code='moodle.settings.manage'");
        $db->exec("CREATE TABLE moodle_integrations(id TINYINT UNSIGNED NOT NULL,base_url VARCHAR(255) NULL,token_encrypted TEXT NULL,token_last4 VARCHAR(4) NULL,is_active TINYINT(1) NOT NULL DEFAULT 0,sync_cursor BIGINT NOT NULL DEFAULT 0,sync_complete TINYINT(1) NOT NULL DEFAULT 0,last_tested_at DATETIME NULL,last_synced_at DATETIME NULL,last_error VARCHAR(1000) NULL,updated_by BIGINT UNSIGNED NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),CONSTRAINT moodle_integrations_user_fk FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("INSERT INTO moodle_integrations(id) VALUES(1)");
        $db->exec("CREATE TABLE moodle_courses(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,moodle_course_id BIGINT NOT NULL,shortname VARCHAR(255) NOT NULL,fullname VARCHAR(500) NOT NULL,idnumber VARCHAR(255) NULL,category_id BIGINT NOT NULL DEFAULT 0,visible TINYINT(1) NOT NULL DEFAULT 1,start_at DATETIME NULL,end_at DATETIME NULL,raw_json LONGTEXT NULL,synced_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY moodle_courses_external_unique(moodle_course_id),KEY moodle_courses_name_idx(fullname(190))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE moodle_users(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,moodle_user_id BIGINT NOT NULL,finance_customer_id BIGINT UNSIGNED NULL,username VARCHAR(255) NOT NULL,firstname VARCHAR(255) NOT NULL,lastname VARCHAR(255) NOT NULL,fullname VARCHAR(500) NOT NULL,email VARCHAR(255) NULL,idnumber VARCHAR(255) NULL,suspended TINYINT(1) NOT NULL DEFAULT 0,raw_json LONGTEXT NULL,synced_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY moodle_users_external_unique(moodle_user_id),KEY moodle_users_email_idx(email),KEY moodle_users_idnumber_idx(idnumber),CONSTRAINT moodle_users_finance_fk FOREIGN KEY(finance_customer_id) REFERENCES finance_customers(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE moodle_enrolments(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,moodle_course_id BIGINT NOT NULL,moodle_user_id BIGINT NOT NULL,time_start DATETIME NULL,time_end DATETIME NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,synced_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY moodle_enrolments_unique(moodle_course_id,moodle_user_id),KEY moodle_enrolments_user_idx(moodle_user_id),KEY moodle_enrolments_course_idx(moodle_course_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(PDO$db):void
    {
        $db->exec('DROP TABLE IF EXISTS moodle_enrolments');$db->exec('DROP TABLE IF EXISTS moodle_users');$db->exec('DROP TABLE IF EXISTS moodle_courses');$db->exec('DROP TABLE IF EXISTS moodle_integrations');
        $db->exec("DELETE rp FROM role_permissions rp INNER JOIN permissions p ON p.id=rp.permission_id WHERE p.code='moodle.settings.manage'");$db->exec("DELETE FROM permissions WHERE code='moodle.settings.manage'");
    }
};
