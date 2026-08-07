<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260806_790000_create_platform_settings'; }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE platform_settings(id TINYINT UNSIGNED NOT NULL,display_name VARCHAR(120) NOT NULL,primary_color CHAR(7) NOT NULL,secondary_color CHAR(7) NULL,logo_path VARCHAR(255) NOT NULL,favicon_path VARCHAR(255) NOT NULL,login_title VARCHAR(160) NULL,login_welcome_text VARCHAR(500) NULL,support_email VARCHAR(190) NULL,support_phone VARCHAR(30) NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("INSERT INTO platform_settings(id,display_name,primary_color,secondary_color,logo_path,favicon_path,login_title,login_welcome_text) VALUES(1,'MUNDO INTER','#ed1c24','#082d72','/assets/media/mundo-inter-logo.png?v=20260806','/assets/media/mundo-inter-favicon.png?v=20260806','MUNDO INTER','Use suas credenciais para continuar.')");
    }

    public function down(PDO $db): void { $db->exec('DROP TABLE IF EXISTS platform_settings'); }
};
