<?php

declare(strict_types=1);

use Interferencia\Kernel\Config\Config;
use Interferencia\Kernel\Http\Request;
use Interferencia\Kernel\Http\Response;
use Interferencia\Kernel\Http\Router;
use Interferencia\Kernel\Security\Csrf;
use Interferencia\Kernel\Session\Session;
use Interferencia\Kernel\Validation\Validator;
use Interferencia\Kernel\View\View;
use Interferencia\Modules\Identity\Auth;
use Interferencia\Modules\Identity\RequireAuth;
use Interferencia\Modules\Identity\RequireGuest;
use Interferencia\Modules\Identity\RequirePermission;

return static function (
    Router $router,
    Config $config,
    View $view,
    Session $session,
    Csrf $csrf,
    Validator $validator,
    Auth $auth,
): void {
    $basePath = $config->string('app.base_path');
    $requireAuth = new RequireAuth($auth, $basePath);
    $requireGuest = new RequireGuest($auth, $basePath);

    $router->get('/status', static function () use ($config, $view): Response {
        return $view->render('status', [
            'title' => $config->string('app.name'),
            'name' => $config->string('app.name'),
            'environment' => $config->string('app.environment'),
            'basePath' => $config->string('app.base_path'),
        ]);
    });

    $router->get('/login', static function () use ($config, $view, $session, $csrf): Response {
        return $view->render('auth/login', [
            'title' => 'Entrar — ' . $config->string('app.name'),
            'csrfField' => $csrf->field(),
            'error' => $session->get('auth.error'),
            'email' => (string) $session->get('auth.email', ''),
            'basePath' => $config->string('app.base_path'),
        ]);
    }, [$requireGuest]);

    $router->post('/login', static function (Request $request) use ($auth, $basePath, $session, $validator): Response {
        $result = $validator->validate($request->inputData(), [
            'email' => 'required|string|email|max:190',
            'password' => 'required|string|max:4096',
        ], ['email' => 'e-mail', 'password' => 'senha']);
        $email = is_string($result->value('email')) ? strtolower(trim($result->value('email'))) : '';
        $password = is_string($result->value('password')) ? $result->value('password') : '';

        if ($result->fails() || !$auth->attempt($email, $password)->successful) {
            $session->flash('auth.error', 'E-mail ou senha inválidos. Tente novamente mais tarde se o acesso estiver temporariamente bloqueado.');
            $session->flash('auth.email', $email);
            return Response::redirect($basePath . '/login');
        }

        return Response::redirect($basePath . '/');
    }, [$requireGuest]);

    $router->get('/', static function () use ($auth, $config, $view, $csrf): Response {
        return $view->render('dashboard', [
            'title' => $config->string('app.name'),
            'user' => $auth->user(),
            'unitScopes' => $auth->unitScopes(),
            'csrfField' => $csrf->field(),
            'basePath' => $config->string('app.base_path'),
        ]);
    }, [$requireAuth, new RequirePermission($auth, 'dashboard.view')]);

    $router->post('/logout', static function () use ($auth, $basePath): Response {
        $auth->logout();
        return Response::redirect($basePath . '/login');
    }, [$requireAuth]);
};
