<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260807_950000_create_document_types'; }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE document_types(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,scope VARCHAR(20) NOT NULL DEFAULT 'franchise',code VARCHAR(60) NOT NULL,name VARCHAR(120) NOT NULL,is_required TINYINT(1) NOT NULL DEFAULT 0,is_active TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 100,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY document_types_scope_code_unique(scope,code),KEY document_types_scope_active_order_idx(scope,is_active,sort_order,name),CONSTRAINT document_types_scope_check CHECK(scope IN('central','franchise'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("INSERT INTO document_types(scope,code,name,is_required,is_active,sort_order) VALUES
            ('franchise','contrato_social','Contrato Social',1,1,10),
            ('franchise','cartao_cnpj','Cartão CNPJ',1,1,20),
            ('franchise','cnh_gestor','CNH do gestor',1,1,30),
            ('franchise','documento_gestor','RG/CPF do gestor',0,1,40),
            ('franchise','comprovante_endereco','Comprovante de endereço',1,1,50),
            ('franchise','certidoes','Certidões',0,1,60),
            ('franchise','cadastral','Outros documentos cadastrais',0,1,70),
            ('franchise','juridico','Jurídico',0,1,80),
            ('franchise','financeiro','Financeiro',0,1,90),
            ('franchise','contratos','Contratos',0,1,100),
            ('franchise','marketing','Marketing',0,1,110),
            ('franchise','operacional','Operacional',0,1,120),
            ('franchise','outros','Outros',0,1,130)");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS document_types');
    }
};
