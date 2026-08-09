<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260808_970000_create_ava_connection_checks'; }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE ava_connection_checks(
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            connection_id BIGINT UNSIGNED NOT NULL,
            moodle_status VARCHAR(20) NOT NULL,
            plugin_status VARCHAR(30) NOT NULL,
            installed_version VARCHAR(40) NULL,
            expected_version VARCHAR(40) NOT NULL,
            message VARCHAR(1000) NULL,
            checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            KEY ava_connection_checks_connection_date_idx(connection_id,checked_at),
            CONSTRAINT ava_connection_checks_connection_fk FOREIGN KEY(connection_id) REFERENCES ava_connections(id) ON DELETE CASCADE,
            CONSTRAINT ava_connection_checks_moodle_status_check CHECK(moodle_status IN('connected','error')),
            CONSTRAINT ava_connection_checks_plugin_status_check CHECK(plugin_status IN('current','outdated','disabled','missing','unknown'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $db): void { $db->exec('DROP TABLE IF EXISTS ava_connection_checks'); }
};
