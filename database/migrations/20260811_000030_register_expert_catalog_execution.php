<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260811_000030_register_expert_catalog_execution';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE course_catalogs
            ADD COLUMN execution_environment VARCHAR(30) NOT NULL DEFAULT 'provider_ava' AFTER description");

        $database->exec("UPDATE course_catalogs SET execution_environment=CASE code
            WHEN 'ava-cursos' THEN 'shared_ava'
            WHEN 'catalogo-up' THEN 'shared_ava'
            WHEN 'catalogo-master' THEN 'shared_ava'
            ELSE 'provider_ava' END");

        $database->exec("INSERT INTO course_catalogs(code,name,description,execution_environment,is_active)
            VALUES('catalogo-expert','Catálogo EXPERT','Fornecedor: CONTED TECH. Catálogo integrado e executado dentro do AVA Cursos.','shared_ava',1)
            ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),execution_environment=VALUES(execution_environment),is_active=1");

        $database->exec("INSERT INTO course_provider_integrations(provider_code,name,base_url,catalog_id,delivery_mode,launch_url_template,is_active)
            SELECT 'conted_tech','CONTED TECH','https://gerenciador.conted.tech/api/v2',catalog.id,'sso','https://avacursos.com.br/{franquia}',0
            FROM course_catalogs catalog WHERE catalog.code='catalogo-expert'
            ON DUPLICATE KEY UPDATE name=VALUES(name),catalog_id=VALUES(catalog_id),base_url=COALESCE(NULLIF(base_url,''),VALUES(base_url)),delivery_mode='sso',launch_url_template=VALUES(launch_url_template)");

        $database->exec("INSERT INTO course_provider_capabilities(provider_id,catalog_sync,automatic_enrollment,single_sign_on,progress_tracking,grade_tracking,certificate_access,suspend_access,send_access)
            SELECT id,0,0,0,0,0,0,0,0 FROM course_provider_integrations WHERE provider_code='conted_tech'
            ON DUPLICATE KEY UPDATE provider_id=VALUES(provider_id)");

        $database->exec("UPDATE course_catalogs SET
            description='Fornecedor: SIE. Catálogo integrado e executado dentro do AVA Cursos.',
            execution_environment='shared_ava' WHERE code='catalogo-up'");
        $database->exec("UPDATE course_catalogs SET
            description='Fornecedor: IESDE. Conteúdo integrado por LTI 1.3 e executado dentro do AVA Cursos.',
            execution_environment='shared_ava' WHERE code='catalogo-master'");
        $database->exec("UPDATE course_catalogs SET
            description='Fornecedor: AVA Cursos. Catálogo central executado no Moodle compartilhado do Mundo Inter.',
            execution_environment='shared_ava' WHERE code='ava-cursos'");
    }

    public function down(PDO $database): void
    {
        $database->exec("DELETE FROM course_provider_integrations WHERE provider_code='conted_tech'");
        $database->exec("DELETE FROM course_catalogs WHERE code='catalogo-expert'");
        $database->exec('ALTER TABLE course_catalogs DROP COLUMN execution_environment');
    }
};
