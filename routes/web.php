<?php

declare(strict_types=1);

use Interferencia\Kernel\Config\Config;
use Interferencia\Kernel\Http\Request;
use Interferencia\Kernel\Http\Response;
use Interferencia\Kernel\Http\Router;
use Interferencia\Kernel\View\View;

return static function (Router $router, Config $config, View $view): void {
    $status = static function (Request $request, array $parameters) use ($config, $view): Response {
        return $view->render('status', [
            'title' => $config->string('app.name'),
            'name' => $config->string('app.name'),
            'environment' => $config->string('app.environment'),
            'basePath' => $config->string('app.base_path'),
        ]);
    };

    $router->get('/', $status);
    $router->get('/status', $status);
};

