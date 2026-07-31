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
use Interferencia\Modules\Identity\UserRepository;
use Interferencia\Modules\Identity\UserManager;
use Interferencia\Modules\Organization\UnitManager;
use Interferencia\Modules\Organization\UnitRepository;

return static function (
    Router $router,
    Config $config,
    View $view,
    Session $session,
    Csrf $csrf,
    Validator $validator,
    Auth $auth,
    UserRepository $users,
    UserManager $userManager,
    UnitRepository $units,
    UnitManager $unitManager,
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
            'canManageUsers' => $auth->can('users.manage'),
            'canManageUnits' => $auth->can('units.manage'),
        ]);
    }, [$requireAuth, new RequirePermission($auth, 'dashboard.view')]);

    $router->post('/logout', static function () use ($auth, $basePath): Response {
        $auth->logout();
        return Response::redirect($basePath . '/login');
    }, [$requireAuth]);

    $manageUsers = [$requireAuth, new RequirePermission($auth, 'users.manage')];
    $ids = static fn (mixed $value): array => array_values(array_unique(array_map('intval', is_array($value) ? $value : [])));

    $router->get('/users', static function () use ($view, $config, $users, $session, $basePath): Response {
        return $view->render('users/index', ['title' => 'Usuários — ' . $config->string('app.name'), 'users' => $users->allForManagement(), 'message' => $session->get('users.message'), 'error' => $session->get('users.error'), 'basePath' => $basePath]);
    }, $manageUsers);

    $form = static function (?int $id = null) use ($view, $config, $users, $session, $csrf, $basePath): Response {
        $user = $id === null ? null : $users->findById($id);
        if ($id !== null && $user === null) return Response::text("Usuário não encontrado.\n", 404);
        return $view->render('users/form', ['title' => ($id === null ? 'Novo usuário' : 'Editar usuário') . ' — ' . $config->string('app.name'), 'user' => $user, 'roles' => $users->availableRoles(), 'units' => $users->availableUnits(), 'selectedRoles' => $id === null ? [] : $users->roleIds($id), 'selectedUnits' => $id === null ? [] : $users->unitIds($id), 'error' => $session->get('users.error'), 'csrfField' => $csrf->field(), 'basePath' => $basePath]);
    };
    $router->get('/users/create', static fn (): Response => $form(), $manageUsers);
    $router->get('/users/{id:\d+}/edit', static fn (Request $request, array $params): Response => $form((int) $params['id']), $manageUsers);

    $save = static function (Request $request, ?int $id = null) use ($validator, $userManager, $session, $basePath, $ids): Response {
        $result = $validator->validate($request->inputData(), ['name' => 'required|string|min:3|max:120', 'email' => 'required|string|email|max:190', 'password' => ($id === null ? 'required|' : 'nullable|') . 'string|min:12|max:4096|confirmed'], ['name' => 'nome', 'email' => 'e-mail', 'password' => 'senha']);
        try {
            if ($result->fails()) throw new RuntimeException(implode(' ', array_map(static fn (array $errors): string => $errors[0], $result->errors())));
            $name = (string) $result->value('name'); $email = (string) $result->value('email'); $password = $result->value('password');
            $roles = $ids($request->input('roles')); $units = $ids($request->input('units')); $active = $request->input('is_active') === '1';
            if ($id === null) $userManager->create($name, $email, (string) $password, $active, $roles, $units);
            else $userManager->update($id, $name, $email, is_string($password) ? $password : null, $active, $roles, $units);
            $session->flash('users.message', $id === null ? 'Usuário criado.' : 'Usuário atualizado.');
            return Response::redirect($basePath . '/users');
        } catch (Throwable $exception) {
            $session->flash('users.error', $exception->getMessage());
            return Response::redirect($basePath . ($id === null ? '/users/create' : "/users/{$id}/edit"));
        }
    };
    $router->post('/users', static fn (Request $request): Response => $save($request), $manageUsers);
    $router->post('/users/{id:\d+}', static fn (Request $request, array $params): Response => $save($request, (int) $params['id']), $manageUsers);

    $manageUnits = [$requireAuth, new RequirePermission($auth, 'units.manage')];

    $router->get('/units', static function () use ($view, $config, $units, $session, $basePath): Response {
        return $view->render('units/index', ['title' => 'Unidades — ' . $config->string('app.name'), 'units' => $units->all(), 'message' => $session->get('units.message'), 'error' => $session->get('units.error'), 'basePath' => $basePath]);
    }, $manageUnits);

    $unitForm = static function (?int $id = null) use ($view, $config, $units, $session, $csrf, $basePath): Response {
        $unit = $id === null ? null : $units->find($id);
        if ($id !== null && $unit === null) return Response::text("Unidade não encontrada.\n", 404);
        return $view->render('units/form', ['title' => ($id === null ? 'Nova unidade' : 'Editar unidade') . ' — ' . $config->string('app.name'), 'unit' => $unit, 'error' => $session->get('units.error'), 'csrfField' => $csrf->field(), 'basePath' => $basePath]);
    };
    $router->get('/units/create', static fn (): Response => $unitForm(), $manageUnits);
    $router->get('/units/{id:\d+}/edit', static fn (Request $request, array $params): Response => $unitForm((int) $params['id']), $manageUnits);

    $saveUnit = static function (Request $request, ?int $id = null) use ($validator, $unitManager, $session, $basePath): Response {
        $result = $validator->validate($request->inputData(), ['name' => 'required|string|min:2|max:120', 'city' => 'required|string|min:2|max:120'], ['name' => 'nome', 'city' => 'cidade']);
        try {
            if ($result->fails()) throw new RuntimeException(implode(' ', array_map(static fn (array $errors): string => $errors[0], $result->errors())));
            $name = (string) $result->value('name'); $city = (string) $result->value('city'); $active = $request->input('is_active') === '1';
            if ($id === null) $unitManager->create($name, $city, $active);
            else $unitManager->update($id, $name, $city, $active);
            $session->flash('units.message', $id === null ? 'Unidade criada.' : 'Unidade atualizada.');
            return Response::redirect($basePath . '/units');
        } catch (Throwable $exception) {
            $session->flash('units.error', $exception->getMessage());
            return Response::redirect($basePath . ($id === null ? '/units/create' : "/units/{$id}/edit"));
        }
    };
    $router->post('/units', static fn (Request $request): Response => $saveUnit($request), $manageUnits);
    $router->post('/units/{id:\d+}', static fn (Request $request, array $params): Response => $saveUnit($request, (int) $params['id']), $manageUnits);
};
