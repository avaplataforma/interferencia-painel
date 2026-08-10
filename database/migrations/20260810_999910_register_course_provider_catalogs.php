<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260810_999910_register_course_provider_catalogs';
    }

    public function up(PDO $database): void
    {
        $catalogs = [
            ['ava-cursos', 'Catálogo INTER', 'Fornecedor: AVA Cursos. Catálogo central entregue pelo Moodle compartilhado do Mundo Inter.'],
            ['catalogo-pro', 'Catálogo PRO', 'Fornecedor: Escola Avançada. Cursos entregues no AVA externo do parceiro.'],
            ['catalogo-up', 'Catálogo UP', 'Fornecedor: SIE. Cursos entregues no AVA externo do parceiro.'],
            ['catalogo-master', 'Catálogo MASTER', 'Fornecedor: IESDE. Cursos entregues no AVA externo do parceiro.'],
            ['catalogo-cefe', 'Catálogo CEFE', 'Fornecedor: EJA CEFE. Cursos entregues no AVA externo do parceiro.'],
            ['catalogo-conclusao', 'Catálogo CONCLUSÃO', 'Fornecedor: EJA Conclusão. Cursos entregues no AVA externo do parceiro.'],
            ['catalogo-prepara', 'Catálogo PREPARA', 'Fornecedor: Aprova Concursos. Cursos entregues no AVA externo do parceiro.'],
            ['catalogo-drive', 'Catálogo DRIVE', 'Fornecedor: Trânsito. Cursos entregues no AVA externo do parceiro.'],
        ];
        $catalog = $database->prepare('INSERT INTO course_catalogs(code,name,description,is_active) VALUES(:code,:name,:description,1) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),is_active=1');
        foreach ($catalogs as [$code, $name, $description]) $catalog->execute(['code' => $code, 'name' => $name, 'description' => $description]);

        $providers = [
            ['escola_avancada', 'Escola Avançada', 'catalogo-pro', 'https://interferenciaead.com.br/metodo/login.php'],
            ['sie', 'SIE', 'catalogo-up', 'https://www.sie.com.br/interferenciaead'],
            ['iesde', 'IESDE', 'catalogo-master', 'https://eadservidor.com.br/avacursos/interferencia/'],
            ['eja_cefe', 'EJA CEFE', 'catalogo-cefe', 'https://avacefe.com.br/login/'],
            ['eja_conclusao', 'EJA Conclusão', 'catalogo-conclusao', 'https://avaconclusao.com.br/login/'],
            ['aprova_concursos', 'Aprova Concursos', 'catalogo-prepara', 'https://aprovaconcursos.com.br/?ref=interf2026'],
            ['transito', 'Trânsito', 'catalogo-drive', 'https://ava.eadcursosdetransito.com.br/login'],
        ];
        $provider = $database->prepare("INSERT INTO course_provider_integrations(provider_code,name,catalog_id,delivery_mode,launch_url_template,is_active)
            SELECT :provider_code,:name,catalog.id,'external_link',:launch_url,0 FROM course_catalogs catalog WHERE catalog.code=:catalog_code
            ON DUPLICATE KEY UPDATE name=VALUES(name),catalog_id=VALUES(catalog_id),launch_url_template=IF(NULLIF(launch_url_template,'') IS NULL,VALUES(launch_url_template),launch_url_template)");
        foreach ($providers as [$providerCode, $name, $catalogCode, $launchUrl]) {
            $provider->execute(['provider_code' => $providerCode, 'name' => $name, 'catalog_code' => $catalogCode, 'launch_url' => $launchUrl]);
        }
    }

    public function down(PDO $database): void
    {
        $database->exec("DELETE FROM course_provider_integrations WHERE provider_code IN ('sie','iesde','eja_cefe','eja_conclusao','aprova_concursos','transito')");
        $database->exec("DELETE FROM course_catalogs WHERE code IN ('catalogo-up','catalogo-master','catalogo-cefe','catalogo-conclusao','catalogo-prepara','catalogo-drive')");
        $database->exec("UPDATE course_catalogs SET name='AVA Cursos' WHERE code='ava-cursos'");
    }
};
