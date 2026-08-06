<?php declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string{return '20260806_690000_create_moodle_unit_mappings';}
    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE moodle_unit_mappings(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,field_id BIGINT UNSIGNED NOT NULL,field_value VARCHAR(255) NOT NULL,unit_id BIGINT UNSIGNED NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY moodle_unit_mappings_value_unique(field_id,field_value),UNIQUE KEY moodle_unit_mappings_unit_unique(field_id,unit_id),CONSTRAINT moodle_unit_mappings_field_fk FOREIGN KEY(field_id) REFERENCES moodle_profile_fields(id) ON DELETE CASCADE,CONSTRAINT moodle_unit_mappings_unit_fk FOREIGN KEY(unit_id) REFERENCES units(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("INSERT IGNORE INTO moodle_unit_mappings(field_id,field_value,unit_id) SELECT f.id,v.field_value,u.id FROM moodle_profile_fields f INNER JOIN moodle_user_profile_values v ON v.field_id=f.id INNER JOIN units u ON LOWER(TRIM(u.name))=LOWER(TRIM(v.field_value)) WHERE (LOWER(f.source_name)='polo presencial' OR LOWER(f.shortname) IN ('polo_presencial','polopresencial')) AND NULLIF(TRIM(v.field_value),'') IS NOT NULL GROUP BY f.id,v.field_value,u.id");
    }
    public function down(PDO $db): void{$db->exec('DROP TABLE IF EXISTS moodle_unit_mappings');}
};
