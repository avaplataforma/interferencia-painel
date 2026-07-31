<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Database;

use PDO;

interface Migration
{
    public function id(): string;

    public function up(PDO $database): void;

    public function down(PDO $database): void;
}

