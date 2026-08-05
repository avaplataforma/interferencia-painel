<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260805_590000_create_moodle_profile_fields'; }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE moodle_profile_fields(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,shortname VARCHAR(190) NOT NULL,source_name VARCHAR(255) NOT NULL,data_type VARCHAR(60) NULL,destination_key VARCHAR(40) NOT NULL DEFAULT 'supplemental',is_visible TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY moodle_profile_fields_shortname_unique(shortname)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE moodle_user_profile_values(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,moodle_user_id BIGINT NOT NULL,field_id BIGINT UNSIGNED NOT NULL,field_value TEXT NULL,raw_json LONGTEXT NULL,synced_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY moodle_user_profile_value_unique(moodle_user_id,field_id),KEY moodle_user_profile_user_idx(moodle_user_id),CONSTRAINT moodle_user_profile_field_fk FOREIGN KEY(field_id) REFERENCES moodle_profile_fields(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS moodle_user_profile_values');
        $db->exec('DROP TABLE IF EXISTS moodle_profile_fields');
    }
};
