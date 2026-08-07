<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260806_800000_activate_new_mundo_inter_brand';
    }

    public function up(PDO $db): void
    {
        $statement = $db->prepare(
            'UPDATE platform_settings SET logo_path = :logo, favicon_path = :favicon WHERE id = 1',
        );
        $statement->execute([
            'logo' => '/assets/media/mundo-inter-logo.png?v=20260806',
            'favicon' => '/assets/media/mundo-inter-favicon.png?v=20260806',
        ]);
    }

    public function down(PDO $db): void
    {
        // Os arquivos anteriores não são restaurados para evitar referências quebradas.
    }
};
