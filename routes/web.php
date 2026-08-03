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
use Interferencia\Modules\Crm\TagRepository;
use Interferencia\Modules\Crm\StatusRepository;
use Interferencia\Modules\Crm\FollowUpRepository;
use Interferencia\Modules\Crm\ExternalFormRepository;

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
    TagRepository $tags,
    StatusRepository $statuses,
    FollowUpRepository $followUps,
    ExternalFormRepository $externalForms,
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

    $router->get('/', static function (Request $request) use ($auth, $view, $csrf, $session, $browserTitle, $basePath, $followUps, $unitContext, $contacts, $tags): Response {
        $dashboardUnit=$unitContext->current();
        $dashboardUnitIds=$dashboardUnit===null?[]:($dashboardUnit['id']===null?array_map(static fn(array $item):int=>(int)$item['id'],$unitContext->available()):[(int)$dashboardUnit['id']]);
        $source=(string)$request->queryValue('source','');
        if(!in_array($source,['','internal','external_form','whatsapp'],true))$source='';
        $tagId=max(0,(int)$request->queryValue('tag','0'));
        $canViewContacts=$auth->can('crm.contacts.view');
        return $view->render('dashboard', [
            'title' => $browserTitle,
            'user' => $auth->user(),
            'unitScopes' => $auth->unitScopes(),
            'csrfField' => $csrf->field(),
            'basePath' => $basePath,
            'canManageUsers' => $auth->can('users.manage'),
            'canManageUnits' => $auth->can('units.manage'),
            'canManageRoles' => $auth->can('roles.manage'),
            'canManageTags' => $auth->can('crm.tags.manage'),
            'canManageStatuses' => $auth->can('crm.statuses.manage'),
            'canManageExternalForms' => $auth->can('external_forms.manage'),
            'followUpSummary' => $canViewContacts ? $followUps->summary($dashboardUnitIds) : null,
            'newContacts' => $canViewContacts ? $contacts->newContactsDashboard($dashboardUnitIds,$source,$tagId) : null,
            'contactTags' => $canViewContacts ? $tags->all(true) : [],
            'selectedSource' => $source,
            'selectedTag' => $tagId,
            'allUnits' => $dashboardUnit !== null && array_key_exists('id',$dashboardUnit) && $dashboardUnit['id'] === null,
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

    $router->get('/crm/contacts', static function (Request $request) use ($view, $contacts, $tags, $contactUnit, $unitContext, $session, $basePath, $browserTitle): Response {
        $unit = $contactUnit(); if ($unit === null) return Response::text("Nenhuma unidade ativa.\n", 422);
        $search = trim((string) $request->queryValue('q', ''));
        $tagId=max(0,(int)$request->queryValue('tag','0'));
        $items=$unit['id']===null?$contacts->allForUnits(array_map(static fn(array $item):int=>(int)$item['id'],$unitContext->available()),$search,$tagId):$contacts->all((int)$unit['id'],$search,$tagId);
        return $view->render('crm/contacts/index', ['title'=>'Contatos — '.$browserTitle, 'contacts'=>$items, 'search'=>$search, 'tags'=>$tags->all(true), 'selectedTag'=>$tagId, 'unit'=>$unit, 'message'=>$session->get('contacts.message'), 'error'=>$session->get('contacts.error'), 'basePath'=>$basePath]);
    }, $viewContacts);

    $contactForm = static function (?int $id=null) use ($view,$contacts,$tags,$units,$contactUnit,$session,$csrf,$basePath,$browserTitle): Response {
        $unit=$contactUnit(); if ($unit===null||$unit['id']===null) return Response::text("Selecione uma unidade específica para cadastrar ou editar contatos.\n",422);
        $contact=$id===null?null:$contacts->find($id,(int)$unit['id']); if ($id!==null&&$contact===null) return Response::text("Contato não encontrado.\n",404);
        return $view->render('crm/contacts/form',['title'=>($id===null?'Novo contato':'Editar contato').' — '.$browserTitle,'contact'=>$contact,'unit'=>$unit,'statuses'=>$contacts->statuses(),'responsibles'=>$contacts->users((int)$unit['id']),'poles'=>array_values(array_filter($units->all(),static fn(array $item):bool=>(int)$item['is_active']===1)),'tags'=>$tags->all(true),'selectedTags'=>$id===null?[]:$tags->idsForContact($id),'error'=>$session->get('contacts.error'),'csrfField'=>$csrf->field(),'basePath'=>$basePath]);
    };
    $router->get('/crm/contacts/create',static fn():Response=>$contactForm(),$manageContacts);
    $router->get('/crm/contacts/{id:\d+}/edit',static fn(Request $request,array $params):Response=>$contactForm((int)$params['id']),$manageContacts);

    $saveContact=static function(Request $request,?int $id=null) use($validator,$contactManager,$units,$contactUnit,$auth,$session,$basePath):Response {
        $unit=$contactUnit(); if($unit===null||$unit['id']===null)return Response::text("Selecione uma unidade específica.\n",422);
        $result=$validator->validate($request->inputData(),['name'=>'required|string|min:2|max:160','email'=>'required|string|email|max:190','phone'=>'required|string|max:16','course'=>'required|string|max:160','origin_city'=>'required|string|max:120','document'=>'required|string|max:18','notes'=>'required|string|min:2|max:5000','interest_score'=>'required|string','responsible_user_id'=>'required|string','registration_source'=>'required|string|in:internal,external_form','registered_at'=>'required|string'],['name'=>'nome','email'=>'e-mail','phone'=>'telefone','course'=>'curso','origin_city'=>'polo','document'=>'documento','notes'=>'observações','interest_score'=>'interesse','responsible_user_id'=>'atendente','registration_source'=>'origem do cadastro','registered_at'=>'data e hora']);
        try { if($result->fails())throw new RuntimeException(implode(' ',array_map(static fn(array $errors):string=>$errors[0],$result->errors()))); $allowedPoles=array_map(static fn(array $item):string=>(string)$item['name'],array_filter($units->all(),static fn(array $item):bool=>(int)$item['is_active']===1));if(!in_array((string)$result->value('origin_city'),$allowedPoles,true))throw new RuntimeException('Selecione um Polo/Cidade válido.'); $data=$request->inputData(); foreach($result->values() as $key=>$value)$data[$key]=$value; $contactManager->save($id,(int)$unit['id'],$auth->user()->id,$data); $session->flash('contacts.message',$id===null?'Contato criado.':'Contato atualizado.'); return Response::redirect($basePath.'/crm/contacts'); }
        catch(Throwable $exception){$session->flash('contacts.error',$exception->getMessage());return Response::redirect($basePath.($id===null?'/crm/contacts/create':"/crm/contacts/{$id}/edit"));}
    };
    $router->post('/crm/contacts',static fn(Request $request):Response=>$saveContact($request),$manageContacts);
    $router->post('/crm/contacts/{id:\d+}',static fn(Request $request,array $params):Response=>$saveContact($request,(int)$params['id']),$manageContacts);

    $router->get('/crm/follow-ups',static function(Request $request)use($view,$followUps,$contactUnit,$unitContext,$session,$basePath,$browserTitle):Response{$unit=$contactUnit();if($unit===null)return Response::text("Nenhuma unidade ativa.\n",422);$unitIds=$unit['id']===null?array_map(static fn(array $item):int=>(int)$item['id'],$unitContext->available()):[(int)$unit['id']];$status=(string)$request->queryValue('status','pending');$period=(string)$request->queryValue('period','');$responsible=max(0,(int)$request->queryValue('responsible','0'));$search=trim((string)$request->queryValue('q',''));$items=$followUps->allForUnits($unitIds,$status,$period,$responsible,$search);return $view->render('crm/follow-ups/index',['title'=>'Follow-ups — '.$browserTitle,'followUps'=>$items,'resultCount'=>count($items),'search'=>$search,'summary'=>$followUps->summary($unitIds),'responsibles'=>$followUps->responsiblesForUnits($unitIds),'selectedStatus'=>$status,'selectedPeriod'=>$period,'selectedResponsible'=>$responsible,'unit'=>$unit,'message'=>$session->get('followups.message'),'error'=>$session->get('followups.error'),'basePath'=>$basePath]);},$viewContacts);

    $router->get('/crm/contacts/{id:\d+}/follow-ups/create',static function(Request $request,array $params)use($view,$contacts,$followUps,$contactUnit,$session,$csrf,$basePath,$browserTitle,$auth):Response{$unit=$contactUnit();if($unit===null||$unit['id']===null)return Response::text("Selecione uma unidade específica.\n",422);$contact=$contacts->find((int)$params['id'],(int)$unit['id']);if($contact===null)return Response::text("Contato não encontrado.\n",404);return $view->render('crm/follow-ups/form',['title'=>'Novo follow-up — '.$browserTitle,'contact'=>$contact,'unit'=>$unit,'responsibles'=>$contacts->users((int)$unit['id']),'selectedResponsibleId'=>$auth->user()->id,'history'=>$followUps->forContact((int)$contact['id'],(int)$unit['id']),'error'=>$session->get('followups.error'),'csrfField'=>$csrf->field(),'basePath'=>$basePath]);},$manageContacts);

    $router->post('/crm/contacts/{id:\d+}/follow-ups',static function(Request $request,array $params)use($validator,$contacts,$followUps,$contactUnit,$auth,$session,$basePath):Response{$unit=$contactUnit();if($unit===null||$unit['id']===null)return Response::text("Selecione uma unidade específica.\n",422);$contactId=(int)$params['id'];if($contacts->find($contactId,(int)$unit['id'])===null)return Response::text("Contato não encontrado.\n",404);$result=$validator->validate($request->inputData(),['action'=>'required|string|min:2|max:160','scheduled_at'=>'required|string','responsible_user_id'=>'required|string','notes'=>'required|string|min:2|max:5000'],['action'=>'próxima ação','scheduled_at'=>'data e hora','responsible_user_id'=>'atendente','notes'=>'observações']);try{if($result->fails())throw new RuntimeException(implode(' ',array_map(static fn(array $errors):string=>$errors[0],$result->errors())));$responsible=(int)$result->value('responsible_user_id');if(!$contacts->userBelongsToUnit($responsible,(int)$unit['id']))throw new RuntimeException('Atendente indisponível nesta unidade.');$scheduled=DateTimeImmutable::createFromFormat('Y-m-d\TH:i',(string)$result->value('scheduled_at'));if($scheduled===false)throw new RuntimeException('Informe uma data e hora válidas.');$followUps->create($contactId,$responsible,(string)$result->value('action'),$scheduled->format('Y-m-d H:i:s'),(string)$result->value('notes'),$auth->user()->id);$session->flash('followups.message','Follow-up criado.');return Response::redirect($basePath.'/crm/follow-ups');}catch(Throwable $exception){$session->flash('followups.error',$exception->getMessage());return Response::redirect($basePath."/crm/contacts/{$contactId}/follow-ups/create");}},$manageContacts);

    $router->post('/crm/follow-ups/{id:\d+}/status',static function(Request $request,array $params)use($followUps,$contactUnit,$session,$basePath):Response{$unit=$contactUnit();if($unit===null||$unit['id']===null)return Response::text("Selecione uma unidade específica.\n",422);$id=(int)$params['id'];$item=$followUps->findInUnit($id,(int)$unit['id']);$status=(string)$request->input('status','');if($item===null||!in_array($status,['pending','completed','cancelled'],true)){$session->flash('followups.error',$item===null?'Follow-up não encontrado nesta unidade.':'Situação inválida.');return Response::redirect($basePath.'/crm/follow-ups');}$followUps->setStatus($id,(int)$unit['id'],$status);$session->flash('followups.message','Situação do follow-up atualizada.');if($status==='completed'&&$request->input('create_next')==='1')return Response::redirect($basePath.'/crm/contacts/'.(int)$item['contact_id'].'/follow-ups/create');return Response::redirect($basePath.'/crm/follow-ups');},$manageContacts);

    $manageTags=[$requireAuth,new RequirePermission($auth,'crm.tags.manage')];
    $router->get('/tags',static function()use($view,$tags,$session,$basePath,$browserTitle):Response{return $view->render('tags/index',['title'=>'Etiquetas — '.$browserTitle,'tags'=>$tags->all(),'message'=>$session->get('tags.message'),'error'=>$session->get('tags.error'),'basePath'=>$basePath]);},$manageTags);
    $tagForm=static function(?int $id=null)use($view,$tags,$session,$csrf,$basePath,$browserTitle):Response{$tag=$id===null?null:$tags->find($id);if($id!==null&&$tag===null)return Response::text("Etiqueta não encontrada.\n",404);return $view->render('tags/form',['title'=>($id===null?'Nova etiqueta':'Editar etiqueta').' — '.$browserTitle,'tag'=>$tag,'error'=>$session->get('tags.error'),'csrfField'=>$csrf->field(),'basePath'=>$basePath]);};
    $router->get('/tags/create',static fn():Response=>$tagForm(),$manageTags);
    $router->get('/tags/{id:\d+}/edit',static fn(Request $request,array $params):Response=>$tagForm((int)$params['id']),$manageTags);
    $saveTag=static function(Request $request,?int $id=null)use($validator,$tags,$session,$basePath):Response{$result=$validator->validate($request->inputData(),['name'=>'required|string|min:2|max:80'],['name'=>'nome']);$color=strtolower(trim((string)$request->input('color','')));try{if($result->fails())throw new RuntimeException(implode(' ',array_map(static fn(array $errors):string=>$errors[0],$result->errors())));if(preg_match('/^#[0-9a-f]{6}$/',$color)!==1)throw new RuntimeException('Escolha uma cor válida.');$tags->save($id,(string)$result->value('name'),$color,$request->input('is_active')==='1');$session->flash('tags.message',$id===null?'Etiqueta criada.':'Etiqueta atualizada.');return Response::redirect($basePath.'/tags');}catch(Throwable $exception){$session->flash('tags.error',$exception->getPrevious()!==null?'Já existe uma etiqueta com esse nome.':$exception->getMessage());return Response::redirect($basePath.($id===null?'/tags/create':"/tags/{$id}/edit"));}};
    $router->post('/tags',static fn(Request $request):Response=>$saveTag($request),$manageTags);
    $router->post('/tags/{id:\d+}',static fn(Request $request,array $params):Response=>$saveTag($request,(int)$params['id']),$manageTags);

    $manageStatuses=[$requireAuth,new RequirePermission($auth,'crm.statuses.manage')];
    $router->get('/statuses',static function()use($view,$statuses,$session,$basePath,$browserTitle):Response{return $view->render('statuses/index',['title'=>'Status do CRM — '.$browserTitle,'statuses'=>$statuses->all(),'message'=>$session->get('statuses.message'),'error'=>$session->get('statuses.error'),'basePath'=>$basePath]);},$manageStatuses);
    $statusForm=static function(?int $id=null)use($view,$statuses,$session,$csrf,$basePath,$browserTitle):Response{$status=$id===null?null:$statuses->find($id);if($id!==null&&$status===null)return Response::text("Status não encontrado.\n",404);return $view->render('statuses/form',['title'=>($id===null?'Novo status':'Editar status').' — '.$browserTitle,'statusItem'=>$status,'error'=>$session->get('statuses.error'),'csrfField'=>$csrf->field(),'basePath'=>$basePath]);};
    $router->get('/statuses/create',static fn():Response=>$statusForm(),$manageStatuses);
    $router->get('/statuses/{id:\d+}/edit',static fn(Request $request,array $params):Response=>$statusForm((int)$params['id']),$manageStatuses);
    $saveStatus=static function(Request $request,?int $id=null)use($validator,$statuses,$session,$basePath):Response{$result=$validator->validate($request->inputData(),['name'=>'required|string|min:2|max:100'],['name'=>'nome']);$color=strtolower(trim((string)$request->input('color','')));$order=max(0,min(65535,(int)$request->input('sort_order','0')));try{if($result->fails())throw new RuntimeException(implode(' ',array_map(static fn(array $errors):string=>$errors[0],$result->errors())));if(preg_match('/^#[0-9a-f]{6}$/',$color)!==1)throw new RuntimeException('Escolha uma cor válida.');$statuses->save($id,(string)$result->value('name'),$color,$order,$request->input('is_active')==='1');$session->flash('statuses.message',$id===null?'Status criado.':'Status atualizado.');return Response::redirect($basePath.'/statuses');}catch(Throwable $exception){$session->flash('statuses.error',$exception->getMessage());return Response::redirect($basePath.($id===null?'/statuses/create':"/statuses/{$id}/edit"));}};
    $router->post('/statuses',static fn(Request $request):Response=>$saveStatus($request),$manageStatuses);
    $router->post('/statuses/{id:\d+}',static fn(Request $request,array $params):Response=>$saveStatus($request,(int)$params['id']),$manageStatuses);

    $manageExternalForms=[$requireAuth,new RequirePermission($auth,'external_forms.manage')];
    $router->get('/external-forms',static function()use($view,$externalForms,$session,$basePath,$browserTitle,$config):Response{return $view->render('external-forms/index',['title'=>'Sites externos — '.$browserTitle,'forms'=>$externalForms->all(),'appUrl'=>rtrim($config->string('app.url'),'/'),'message'=>$session->get('external_forms.message'),'error'=>$session->get('external_forms.error'),'basePath'=>$basePath]);},$manageExternalForms);
    $externalFormView=static function(?int $id=null)use($view,$externalForms,$tags,$contacts,$session,$csrf,$basePath,$browserTitle):Response{$form=$id===null?null:$externalForms->find($id);if($id!==null&&$form===null)return Response::text("Site externo não encontrado.\n",404);return $view->render('external-forms/form',['title'=>($id===null?'Novo site externo':'Editar site externo').' — '.$browserTitle,'formItem'=>$form,'tags'=>$tags->all(true),'statuses'=>$contacts->statuses(),'error'=>$session->get('external_forms.error'),'csrfField'=>$csrf->field(),'basePath'=>$basePath]);};
    $router->get('/external-forms/create',static fn():Response=>$externalFormView(),$manageExternalForms);
    $router->get('/external-forms/{id:\d+}/edit',static fn(Request $request,array $params):Response=>$externalFormView((int)$params['id']),$manageExternalForms);
    $saveExternalForm=static function(Request $request,?int $id=null)use($validator,$externalForms,$tags,$contacts,$session,$basePath):Response{$result=$validator->validate($request->inputData(),['name'=>'required|string|min:2|max:120','slug'=>'required|string|min:2|max:100','allowed_domain'=>'required|string|max:190','title'=>'required|string|min:2|max:160','tag_id'=>'required|string','initial_status_id'=>'required|string'],['name'=>'nome','slug'=>'identificador','allowed_domain'=>'domínio','title'=>'título','tag_id'=>'etiqueta','initial_status_id'=>'status inicial']);try{if($result->fails())throw new RuntimeException(implode(' ',array_map(static fn(array $errors):string=>$errors[0],$result->errors())));$slug=strtolower(trim((string)$result->value('slug')));if(preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)!==1)throw new RuntimeException('Use apenas letras minúsculas, números e hífens no identificador.');$domainInput=strtolower(trim((string)$result->value('allowed_domain')));$domain=parse_url(str_contains($domainInput,'://')?$domainInput:'https://'.$domainInput,PHP_URL_HOST);$domain=is_string($domain)?trim($domain,'.'):'';if($domain===''||preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/',$domain)!==1)throw new RuntimeException('Informe um domínio válido, sem caminhos.');$tagId=(int)$result->value('tag_id');$statusId=(int)$result->value('initial_status_id');if(!$tags->validIds([$tagId]))throw new RuntimeException('Selecione uma etiqueta ativa.');if(!$contacts->statusExists($statusId))throw new RuntimeException('Selecione um status inicial ativo.');$externalForms->save($id,(string)$result->value('name'),$slug,$domain,$tagId,$statusId,(string)$result->value('title'),$request->input('is_active')==='1');$session->flash('external_forms.message',$id===null?'Site externo criado.':'Site externo atualizado.');return Response::redirect($basePath.'/external-forms');}catch(Throwable $exception){$session->flash('external_forms.error',$exception->getPrevious()!==null?'O identificador já está sendo utilizado.':$exception->getMessage());return Response::redirect($basePath.($id===null?'/external-forms/create':"/external-forms/{$id}/edit"));}};
    $router->post('/external-forms',static fn(Request $request):Response=>$saveExternalForm($request),$manageExternalForms);
    $router->post('/external-forms/{id:\d+}',static fn(Request $request,array $params):Response=>$saveExternalForm($request,(int)$params['id']),$manageExternalForms);

    $router->get('/formularios/{slug:[a-z0-9-]+}',static function(Request $request,array $params)use($view,$externalForms,$units,$basePath):Response{$form=$externalForms->findPublic($params['slug']);if($form===null)return Response::text("Formulário indisponível.\n",404);$poles=array_values(array_filter($units->all(),static fn(array $item):bool=>(int)$item['is_active']===1));$domain=(string)$form['allowed_domain'];$csp="default-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors https://{$domain} https://*.{$domain}";return $view->renderStandalone('external-forms/embed',['formItem'=>$form,'poles'=>$poles,'sent'=>$request->queryValue('sent','')==='1','submissionId'=>bin2hex(random_bytes(16)),'basePath'=>$basePath])->withHeaders(['Content-Security-Policy'=>$csp]);});
    $router->postWithoutCsrf('/formularios/{slug:[a-z0-9-]+}/enviar',static function(Request $request,array $params)use($externalForms,$externalIntake,$config,$tags,$contacts,$basePath):Response{$form=$externalForms->findPublic($params['slug']);if($form===null)return Response::text("Formulário indisponível.\n",404);if(trim((string)$request->input('website',''))!=='')return Response::redirect($basePath.'/formularios/'.$params['slug'].'?sent=1');$data=['polo'=>$request->input('polo'),'nome'=>$request->input('nome'),'telefone'=>$request->input('telefone'),'email'=>$request->input('email'),'curso'=>$request->input('curso'),'interesse'=>$request->input('interesse'),'cidade_origem'=>$request->input('cidade_origem'),'observacoes'=>$request->input('observacoes'),'submission_id'=>$request->input('submission_id'),'data_hora'=>'now','consentimento_em'=>$request->input('consentimento')==='1'?'now':null,'versao_privacidade'=>'formulario-externo-v1'];try{if($request->input('consentimento')!=='1')throw new RuntimeException('É necessário aceitar o aviso de privacidade.',422);$source=(string)$request->header('cf-connecting-ip',$request->header('x-forwarded-for','unknown'));$result=$externalIntake->receive($config->string('app.external_form_key'),$data,$source);$tags->addToContact($result['id'],(int)$form['tag_id']);$contacts->setExternalStatus($result['id'],(int)$form['initial_status_id']);if(!$result['duplicate'])$externalForms->increment((int)$form['id']);return Response::redirect($basePath.'/formularios/'.$params['slug'].'?sent=1');}catch(Throwable $exception){$domain=(string)$form['allowed_domain'];$csp="default-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors https://{$domain} https://*.{$domain}";$poles=[];return Response::html('<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><style>body{font-family:system-ui;padding:2rem;color:#17212b}.error{padding:1rem;border-radius:.6rem;background:#fff0f1;color:#a3131b}a{color:#ed1c24}</style><p class="error">'.htmlspecialchars($exception->getMessage(),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p><p><a href="'.$basePath.'/formularios/'.$params['slug'].'">Voltar ao formulário</a></p></html>',422)->withHeaders(['Content-Security-Policy'=>$csp]);}});

    $router->postWithoutCsrf('/api/v1/external-contacts',static function(Request $request)use($externalIntake):Response{
        $authorization=(string)$request->header('authorization','');$key=str_starts_with($authorization,'Bearer ')?substr($authorization,7):(string)$request->header('x-form-key','');
        $data=json_decode($request->body(),true);if(!is_array($data))return Response::json(['ok'=>false,'error'=>'JSON inválido.'],400);
        try{$source=(string)$request->header('cf-connecting-ip',$request->header('x-forwarded-for','unknown'));$result=$externalIntake->receive($key,$data,$source);return Response::json(['ok'=>true]+$result,$result['duplicate']?200:201);}catch(Throwable $exception){$status=in_array($exception->getCode(),[401,422,429],true)?$exception->getCode():500;return Response::json(['ok'=>false,'error'=>$status===500?'Erro interno.':$exception->getMessage()],$status);}
    });
};
