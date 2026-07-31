<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260731_210000_create_identity_and_access';
    }

    public function up(PDO $database): void
    {
        $statements = [
            'CREATE TABLE `units` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(64) NOT NULL, `name` VARCHAR(120) NOT NULL, `city` VARCHAR(120) NOT NULL, `is_active` TINYINT(1) NOT NULL DEFAULT 1, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`id`), UNIQUE KEY `units_code_unique` (`code`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE `users` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(120) NOT NULL, `email` VARCHAR(190) NOT NULL, `password_hash` VARCHAR(255) NOT NULL, `is_active` TINYINT(1) NOT NULL DEFAULT 1, `failed_login_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0, `locked_until` DATETIME NULL, `last_login_at` DATETIME NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`id`), UNIQUE KEY `users_email_unique` (`email`), KEY `users_active_index` (`is_active`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE `roles` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(80) NOT NULL, `name` VARCHAR(120) NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `roles_code_unique` (`code`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE `permissions` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(120) NOT NULL, `name` VARCHAR(160) NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `permissions_code_unique` (`code`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE `user_roles` (`user_id` BIGINT UNSIGNED NOT NULL, `role_id` BIGINT UNSIGNED NOT NULL, PRIMARY KEY (`user_id`, `role_id`), CONSTRAINT `user_roles_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE, CONSTRAINT `user_roles_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE `role_permissions` (`role_id` BIGINT UNSIGNED NOT NULL, `permission_id` BIGINT UNSIGNED NOT NULL, PRIMARY KEY (`role_id`, `permission_id`), CONSTRAINT `role_permissions_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE, CONSTRAINT `role_permissions_permission_fk` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE `user_unit_scopes` (`user_id` BIGINT UNSIGNED NOT NULL, `unit_id` BIGINT UNSIGNED NOT NULL, PRIMARY KEY (`user_id`, `unit_id`), CONSTRAINT `user_unit_scopes_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE, CONSTRAINT `user_unit_scopes_unit_fk` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];

        foreach ($statements as $statement) {
            $database->exec($statement);
        }

        $units = [
            ['sede-central-tijucas', 'Sede/Central — Tijucas', 'Tijucas'],
            ['filial-tijucas', 'Filial Tijucas', 'Tijucas'],
            ['itapema', 'Itapema', 'Itapema'],
            ['porto-belo', 'Porto Belo', 'Porto Belo'],
            ['sao-joao-batista', 'São João Batista', 'São João Batista'],
            ['nova-trento', 'Nova Trento', 'Nova Trento'],
        ];
        $insertUnit = $database->prepare('INSERT INTO `units` (`code`, `name`, `city`) VALUES (?, ?, ?)');

        foreach ($units as $unit) {
            $insertUnit->execute($unit);
        }

        $database->exec("INSERT INTO `roles` (`code`, `name`) VALUES ('super_admin', 'Administrador global'), ('manager', 'Gestor'), ('agent', 'Atendente')");
        $database->exec("INSERT INTO `permissions` (`code`, `name`) VALUES ('dashboard.view', 'Visualizar painel'), ('users.manage', 'Gerenciar usuários'), ('roles.manage', 'Gerenciar papéis e permissões'), ('units.access_all', 'Acessar todas as unidades')");
        $database->exec("INSERT INTO `role_permissions` (`role_id`, `permission_id`) SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p WHERE r.code = 'super_admin'");
    }

    public function down(PDO $database): void
    {
        foreach (['user_unit_scopes', 'role_permissions', 'user_roles', 'permissions', 'roles', 'users', 'units'] as $table) {
            $database->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
    }
};

