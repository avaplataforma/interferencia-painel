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
use Interferencia\Modules\Identity\RoleManager;
use Interferencia\Modules\Identity\RoleRepository;
use Interferencia\Modules\Organization\UnitManager;
use Interferencia\Modules\Organization\UnitRepository;
use Interferencia\Modules\Organization\UnitContext;
use Interferencia\Modules\Crm\ContactManager;
use Interferencia\Modules\Crm\ContactRepository;
use Interferencia\Modules\Crm\ExternalContactIntake;

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
    RoleRepository $roles,
    RoleManager $roleManager,
    UnitContext $unitContext,
    ContactRepository $contacts,
    ContactManager $contactManager,
    ExternalContactIntake $externalIntake,
): void {
    $basePath = $config->string('app.base_path');
    $browserTitle = $config->string('app.browser_title');
    $requireAuth = new RequireAuth($auth, $basePath);
    $requireGuest = new RequireGuest($auth, $basePath);

    $router->get('/status', static function () use ($config, $view, $browserTitle): Response {
        return $view->render('status', [
            'title' => $browserTitle,
            'name' => $config->string('app.name'),
            'environment' => $config->string('app.environment'),
            'basePath' => $config->string('app.base_path'),
        ]);
    });

    $router->get('/login', static function () use ($view, $session, $csrf, $browserTitle, $basePath): Response {
        return $view->render('auth/login', [
            'title' => 'Entrar — ' . $browserTitle,
            'csrfField' => $csrf->field(),
            'error' => $session->get('auth.error'),
            'email' => (string) $session->get('auth.email', ''),
            'basePath' => $basePath,
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

    $router->get('/', static function () use ($auth, $view, $csrf, $session, $browserTitle, $basePath): Response {
        return $view->render('dashboard', [
            'title' => $browserTitle,
            'user' => $auth->user(),
            'unitScopes' => $auth->unitScopes(),
            'csrfField' => $csrf->field(),
            'basePath' => $basePath,
            'canManageUsers' => $auth->can('users.manage'),
            'canManageUnits' => $auth->can('units.manage'),
            'canManageRoles' => $auth->can('roles.manage'),
            'message' => $session->get('dashboard.message'),
            'error' => $session->get('dashboard.error'),
        ]);
    }, [$requireAuth, new RequirePermission($auth, 'dashboard.view')]);

    $router->post('/logout', static function () use ($auth, $basePath): Response {
        $auth->logout();
        return Response::redirect($basePath . '/login');
    }, [$requireAuth]);

    $router->post('/context/unit', static function (Request $request) use ($unitContext, $session, $basePath): Response {
        try {
            $unitContext->select((string) $request->input('unit_code', ''));
            $session->flash('dashboard.message', 'Unidade ativa atualizada.');
        } catch (Throwable $exception) {
            $session->flash('dashboard.error', $exception->getMessage());
        }
        return Response::redirect($basePath . '/');
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

    $manageRoles = [$requireAuth, new RequirePermission($auth, 'roles.manage')];

    $router->get('/roles', static function () use ($view, $config, $roles, $session, $basePath): Response {
        return $view->render('roles/index', ['title' => 'Perfis e permissões — ' . $config->string('app.name'), 'roles' => $roles->all(), 'message' => $session->get('roles.message'), 'error' => $session->get('roles.error'), 'basePath' => $basePath]);
    }, $manageRoles);

    $roleForm = static function (?int $id = null) use ($view, $config, $roles, $session, $csrf, $basePath): Response {
        $role = $id === null ? null : $roles->find($id);
        if ($id !== null && $role === null) return Response::text("Perfil não encontrado.\n", 404);
        return $view->render('roles/form', ['title' => ($id === null ? 'Novo perfil' : 'Editar perfil') . ' — ' . $config->string('app.name'), 'role' => $role, 'permissions' => $roles->permissions(), 'selectedPermissions' => $id === null ? [] : $roles->permissionIds($id), 'error' => $session->get('roles.error'), 'csrfField' => $csrf->field(), 'basePath' => $basePath]);
    };
    $router->get('/roles/create', static fn (): Response => $roleForm(), $manageRoles);
    $router->get('/roles/{id:\d+}/edit', static fn (Request $request, array $params): Response => $roleForm((int) $params['id']), $manageRoles);

    $saveRole = static function (Request $request, ?int $id = null) use ($validator, $roleManager, $session, $basePath, $ids): Response {
        $result = $validator->validate($request->inputData(), ['name' => 'required|string|min:2|max:120'], ['name' => 'nome']);
        try {
            if ($result->fails()) throw new RuntimeException(implode(' ', array_map(static fn (array $errors): string => $errors[0], $result->errors())));
            $name = (string) $result->value('name'); $permissions = $ids($request->input('permissions'));
            if ($id === null) $roleManager->create($name, $permissions);
            else $roleManager->update($id, $name, $permissions);
            $session->flash('roles.message', $id === null ? 'Perfil criado.' : 'Perfil atualizado.');
            return Response::redirect($basePath . '/roles');
        } catch (Throwable $exception) {
            $session->flash('roles.error', $exception->getMessage());
            return Response::redirect($basePath . ($id === null ? '/roles/create' : "/roles/{$id}/edit"));
        }
    };
    $router->post('/roles', static fn (Request $request): Response => $saveRole($request), $manageRoles);
    $router->post('/roles/{id:\d+}', static fn (Request $request, array $params): Response => $saveRole($request, (int) $params['id']), $manageRoles);

    $viewContacts = [$requireAuth, new RequirePermission($auth, 'crm.contacts.view')];
    $manageContacts = [$requireAuth, new RequirePermission($auth, 'crm.contacts.manage')];
    $contactUnit = static fn (): ?array => $unitContext->current();

    $router->get('/crm/contacts', static function (Request $request) use ($view, $contacts, $contactUnit, $unitContext, $session, $basePath, $browserTitle): Response {
        $unit = $contactUnit(); if ($unit === null) return Response::text("Nenhuma unidade ativa.\n", 422);
        $search = trim((string) $request->queryValue('q', ''));
        $items=$unit['id']===null?$contacts->allForUnits(array_map(static fn(array $item):int=>(int)$item['id'],$unitContext->available()),$search):$contacts->all((int)$unit['id'],$search);
        return $view->render('crm/contacts/index', ['title'=>'Contatos — '.$browserTitle, 'contacts'=>$items, 'search'=>$search, 'unit'=>$unit, 'message'=>$session->get('contacts.message'), 'error'=>$session->get('contacts.error'), 'basePath'=>$basePath]);
    }, $viewContacts);

    $contactForm = static function (?int $id=null) use ($view,$contacts,$contactUnit,$session,$csrf,$basePath,$browserTitle): Response {
        $unit=$contactUnit(); if ($unit===null||$unit['id']===null) return Response::text("Selecione uma unidade específica para cadastrar ou editar contatos.\n",422);
        $contact=$id===null?null:$contacts->find($id,(int)$unit['id']); if ($id!==null&&$contact===null) return Response::text("Contato não encontrado.\n",404);
        return $view->render('crm/contacts/form',['title'=>($id===null?'Novo contato':'Editar contato').' — '.$browserTitle,'contact'=>$contact,'unit'=>$unit,'statuses'=>$contacts->statuses(),'responsibles'=>$contacts->users((int)$unit['id']),'error'=>$session->get('contacts.error'),'csrfField'=>$csrf->field(),'basePath'=>$basePath]);
    };
    $router->get('/crm/contacts/create',static fn():Response=>$contactForm(),$manageContacts);
    $router->get('/crm/contacts/{id:\d+}/edit',static fn(Request $request,array $params):Response=>$contactForm((int)$params['id']),$manageContacts);

    $saveContact=static function(Request $request,?int $id=null) use($validator,$contactManager,$contactUnit,$auth,$session,$basePath):Response {
        $unit=$contactUnit(); if($unit===null||$unit['id']===null)return Response::text("Selecione uma unidade específica.\n",422);
        $result=$validator->validate($request->inputData(),['name'=>'required|string|min:2|max:160','email'=>'nullable|string|email|max:190','phone'=>'nullable|string|max:32','course'=>'nullable|string|max:160','origin_city'=>'nullable|string|max:120','document'=>'nullable|string|max:24','notes'=>'nullable|string|max:5000'],['name'=>'nome','email'=>'e-mail','phone'=>'telefone','course'=>'curso','origin_city'=>'polo','document'=>'documento','notes'=>'observações']);
        try { if($result->fails())throw new RuntimeException(implode(' ',array_map(static fn(array $errors):string=>$errors[0],$result->errors()))); $data=$request->inputData(); foreach($result->values() as $key=>$value)$data[$key]=$value; $contactManager->save($id,(int)$unit['id'],$auth->user()->id,$data); $session->flash('contacts.message',$id===null?'Contato criado.':'Contato atualizado.'); return Response::redirect($basePath.'/crm/contacts'); }
        catch(Throwable $exception){$session->flash('contacts.error',$exception->getMessage());return Response::redirect($basePath.($id===null?'/crm/contacts/create':"/crm/contacts/{$id}/edit"));}
    };
    $router->post('/crm/contacts',static fn(Request $request):Response=>$saveContact($request),$manageContacts);
    $router->post('/crm/contacts/{id:\d+}',static fn(Request $request,array $params):Response=>$saveContact($request,(int)$params['id']),$manageContacts);

    $router->postWithoutCsrf('/api/v1/external-contacts',static function(Request $request)use($externalIntake):Response{
        $authorization=(string)$request->header('authorization','');$key=str_starts_with($authorization,'Bearer ')?substr($authorization,7):(string)$request->header('x-form-key','');
        $data=json_decode($request->body(),true);if(!is_array($data))return Response::json(['ok'=>false,'error'=>'JSON inválido.'],400);
        try{$source=(string)$request->header('cf-connecting-ip',$request->header('x-forwarded-for','unknown'));$result=$externalIntake->receive($key,$data,$source);return Response::json(['ok'=>true]+$result,$result['duplicate']?200:201);}catch(Throwable $exception){$status=in_array($exception->getCode(),[401,422,429],true)?$exception->getCode():500;return Response::json(['ok'=>false,'error'=>$status===500?'Erro interno.':$exception->getMessage()],$status);}
    });
};
