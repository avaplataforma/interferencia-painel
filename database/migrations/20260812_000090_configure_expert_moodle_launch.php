<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260812_000090_configure_expert_moodle_launch';
    }

    public function up(PDO $database): void
    {
        $statement=$database->prepare("UPDATE course_provider_integrations SET delivery_mode='iframe',launch_url_template=:launch WHERE provider_code='conted_tech'");
        $statement->execute(['launch'=>'https://partner.conted.tech/show-static/{id}']);
    }

    public function down(PDO $database): void
    {
        $statement=$database->prepare("UPDATE course_provider_integrations SET delivery_mode='sso',launch_url_template=:launch WHERE provider_code='conted_tech'");
        $statement->execute(['launch'=>'https://avacursos.com.br/{franquia}']);
    }
};
