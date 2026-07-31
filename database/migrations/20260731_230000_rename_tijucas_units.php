<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260731_230000_rename_tijucas_units';
    }

    public function up(PDO $database): void
    {
        $statement = $database->prepare('UPDATE units SET name = :name WHERE code = :code');

        $statement->execute(['name' => 'Central', 'code' => 'sede-central-tijucas']);
        $statement->execute(['name' => 'Tijucas', 'code' => 'filial-tijucas']);
    }

    public function down(PDO $database): void
    {
        $statement = $database->prepare('UPDATE units SET name = :name WHERE code = :code');

        $statement->execute(['name' => 'Sede/Central — Tijucas', 'code' => 'sede-central-tijucas']);
        $statement->execute(['name' => 'Filial Tijucas', 'code' => 'filial-tijucas']);
    }
};
