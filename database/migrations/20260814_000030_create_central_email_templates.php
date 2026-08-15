<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260814_000030_create_central_email_templates'; }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE central_email_templates (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            template_key VARCHAR(60) NOT NULL,
            name VARCHAR(160) NOT NULL,
            subject_template VARCHAR(255) NOT NULL,
            eyebrow VARCHAR(120) NULL,
            heading VARCHAR(255) NOT NULL,
            intro TEXT NOT NULL,
            button_label VARCHAR(80) NOT NULL,
            footer_text VARCHAR(500) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY central_email_template_key_uq(template_key),
            CONSTRAINT central_email_template_user_fk FOREIGN KEY(updated_by) REFERENCES platform_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $statement=$db->prepare("INSERT INTO central_email_templates(template_key,name,subject_template,eyebrow,heading,intro,button_label,footer_text,is_active) VALUES('ava_access','Acesso ao AVA',:subject,:eyebrow,:heading,:intro,:button,:footer,1)");
        $statement->execute([
            'subject'=>'Seu acesso ao AVA — {{curso}}',
            'eyebrow'=>'ACESSO LIBERADO',
            'heading'=>'Olá, {{aluno}}!',
            'intro'=>'Seu acesso ao curso {{curso}} foi liberado. Use os dados abaixo para começar seus estudos.',
            'button'=>'Acessar sala de aula',
            'footer'=>'Esta é uma mensagem automática. Em caso de dúvida, fale com a equipe da {{franquia}}.',
        ]);

        $db->exec("ALTER TABLE email_delivery_logs
            ADD COLUMN retry_of_id BIGINT UNSIGNED NULL AFTER related_id,
            ADD INDEX email_delivery_retry_idx(retry_of_id),
            ADD CONSTRAINT email_delivery_retry_fk FOREIGN KEY(retry_of_id) REFERENCES email_delivery_logs(id) ON DELETE SET NULL");
    }

    public function down(PDO $db): void
    {
        $db->exec('ALTER TABLE email_delivery_logs DROP FOREIGN KEY email_delivery_retry_fk, DROP INDEX email_delivery_retry_idx, DROP COLUMN retry_of_id');
        $db->exec('DROP TABLE IF EXISTS central_email_templates');
    }
};
