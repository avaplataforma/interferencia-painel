<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260806_830000_create_franchise_contracts'; }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE franchise_contract_templates(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,title VARCHAR(180) NOT NULL,version VARCHAR(30) NOT NULL DEFAULT '1.0',body LONGTEXT NOT NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY franchise_contract_templates_active_idx(is_active,title)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE franchise_contracts(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,franchise_application_id BIGINT UNSIGNED NOT NULL,organization_id BIGINT UNSIGNED NULL,template_id BIGINT UNSIGNED NULL,title VARCHAR(180) NOT NULL,content LONGTEXT NOT NULL,public_token CHAR(64) NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'draft',billing_required TINYINT(1) NOT NULL DEFAULT 0,billing_amount DECIMAL(12,2) NULL,billing_description VARCHAR(190) NULL,signer_name VARCHAR(160) NULL,signer_email VARCHAR(190) NULL,signer_document VARCHAR(20) NULL,signer_ip VARCHAR(64) NULL,signer_user_agent VARCHAR(500) NULL,evidence_hash CHAR(64) NULL,sent_at DATETIME NULL,viewed_at DATETIME NULL,signed_at DATETIME NULL,created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY franchise_contracts_token_unique(public_token),KEY franchise_contracts_application_idx(franchise_application_id,status),CONSTRAINT franchise_contracts_application_fk FOREIGN KEY(franchise_application_id) REFERENCES franchise_applications(id) ON DELETE RESTRICT,CONSTRAINT franchise_contracts_organization_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE SET NULL,CONSTRAINT franchise_contracts_template_fk FOREIGN KEY(template_id) REFERENCES franchise_contract_templates(id) ON DELETE SET NULL,CONSTRAINT franchise_contracts_user_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,CONSTRAINT franchise_contracts_status_check CHECK(status IN('draft','sent','viewed','signed','cancelled'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $body = <<<'HTML'
CONTRATO DE PRESTAÇÃO DE SERVIÇOS

CONTRATANTE: {{razao_social}}, inscrita no CNPJ sob nº {{cnpj}}, com sede em {{endereco_completo}}, neste ato representada por {{gestor_nome}}, e MUNDO INTER, doravante denominada CONTRATADA, celebram o presente contrato.

1. OBJETO
Prestação dos serviços de implantação, licenciamento e suporte da plataforma Mundo Inter para a franquia {{nome_franquia}}.

2. CONDIÇÕES COMERCIAIS
{{condicoes_comerciais}}

3. VIGÊNCIA
{{vigencia}}

4. RESPONSABILIDADES
As partes comprometem-se a cumprir as obrigações descritas neste instrumento, manter seus dados atualizados e observar a legislação aplicável, inclusive a Lei Geral de Proteção de Dados.

5. ACEITE ELETRÔNICO
O aceite eletrônico registrado pela plataforma, acompanhado de data, hora, endereço IP e código de integridade, representa manifestação inequívoca de vontade das partes.

{{cidade}}, {{data_extenso}}.
HTML;
        $statement = $db->prepare("INSERT INTO franchise_contract_templates(title,version,body,is_active) VALUES('Prestação de serviços — Franquia Mundo Inter','1.0',:body,1)");
        $statement->execute(['body' => $body]);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS franchise_contracts,franchise_contract_templates');
    }
};
