<?php

declare(strict_types=1);

use Interferencia\Kernel\Application;
use Interferencia\Kernel\Config\Config;
use Interferencia\Kernel\Environment\Environment;
use Interferencia\Kernel\Error\ErrorHandler;
use Interferencia\Kernel\Log\JsonLogger;

$rootPath = dirname(__DIR__);
$autoload = $rootPath . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Aplicação indisponível: dependências não instaladas.\n";
    exit(1);
}

require $autoload;

Environment::load($rootPath . '/.env');

$config = Config::fromDirectory($rootPath . '/config');
$timezone = $config->string('app.timezone');

if (!date_default_timezone_set($timezone)) {
    throw new RuntimeException(sprintf('Fuso horário inválido: %s', $timezone));
}

$logger = new JsonLogger(
    $rootPath . '/storage/logs/' . $config->string('app.log_channel') . '.log',
    $config->string('app.log_level'),
);

$errorHandler = new ErrorHandler($logger, $config->bool('app.debug'));
$errorHandler->register();

return new Application($config, $logger);

