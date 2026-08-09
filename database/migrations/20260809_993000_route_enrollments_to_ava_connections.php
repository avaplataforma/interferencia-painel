<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260809_993000_route_enrollments_to_ava_connections';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE ava_course_mappings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            connection_id BIGINT UNSIGNED NOT NULL,
            moodle_course_id BIGINT UNSIGNED NOT NULL,
            remote_course_id BIGINT NOT NULL,
            remote_shortname VARCHAR(255) NOT NULL,
            remote_fullname VARCHAR(500) NOT NULL,
            match_method VARCHAR(30) NOT NULL DEFAULT 'automatic',
            synced_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ava_course_mappings_connection_course_unique (connection_id, moodle_course_id),
            UNIQUE KEY ava_course_mappings_connection_remote_unique (connection_id, remote_course_id),
            CONSTRAINT ava_course_mappings_connection_fk FOREIGN KEY (connection_id) REFERENCES ava_connections (id) ON DELETE CASCADE,
            CONSTRAINT ava_course_mappings_course_fk FOREIGN KEY (moodle_course_id) REFERENCES moodle_courses (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("INSERT INTO ava_course_mappings(connection_id,moodle_course_id,remote_course_id,remote_shortname,remote_fullname,match_method)
            SELECT c.id,m.id,m.moodle_course_id,m.shortname,m.fullname,'shared_catalog'
            FROM ava_connections c CROSS JOIN moodle_courses m
            WHERE c.connection_key='shared:ava-cursos'");

        $database->exec("ALTER TABLE student_enrollments
            ADD ava_connection_id BIGINT UNSIGNED NULL AFTER organization_pole_id,
            ADD ava_course_id BIGINT NULL AFTER ava_connection_id,
            ADD ava_username VARCHAR(255) NULL AFTER ava_user_id,
            ADD KEY student_enrollments_ava_connection_idx (ava_connection_id),
            ADD CONSTRAINT student_enrollments_ava_connection_fk FOREIGN KEY (ava_connection_id) REFERENCES ava_connections (id) ON DELETE SET NULL");

        $database->exec("UPDATE student_enrollments e
            INNER JOIN moodle_courses m ON m.id=e.moodle_course_id
            INNER JOIN ava_connections c ON c.connection_key='shared:ava-cursos'
            SET e.ava_connection_id=c.id,e.ava_course_id=m.moodle_course_id
            WHERE e.ava_connection_id IS NULL");
    }

    public function down(PDO $database): void
    {
        $database->exec('ALTER TABLE student_enrollments DROP FOREIGN KEY student_enrollments_ava_connection_fk, DROP INDEX student_enrollments_ava_connection_idx, DROP COLUMN ava_username, DROP COLUMN ava_course_id, DROP COLUMN ava_connection_id');
        $database->exec('DROP TABLE ava_course_mappings');
    }
};
