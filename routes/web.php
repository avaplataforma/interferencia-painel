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
use Interferencia\Modules\WhatsApp\LineRepository;
use Interferencia\Modules\WhatsApp\MessageRepository;
use Interferencia\Modules\WhatsApp\WebhookVerifier;
use Interferencia\Modules\WhatsApp\CloudApiClient;
use Interferencia\Modules\WhatsApp\TemplateRepository;
use Interferencia\Modules\WhatsApp\MediaStorage;
use Interferencia\Modules\Finance\AsaasClient;
use Interferencia\Modules\Finance\AsaasSynchronizer;
use Interferencia\Modules\Finance\FinanceRepository;
use Interferencia\Modules\Finance\WebhookVerifier as AsaasWebhookVerifier;
use Interferencia\Modules\Finance\IntegrationRepository;

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
    LineRepository $whatsappLines,
    MessageRepository $whatsappMessages,
    TemplateRepository $whatsappTemplates,
    MediaStorage $whatsappMedia,
    WebhookVerifier $whatsappWebhook,
    CloudApiClient $whatsappCloudApi,
    FinanceRepository $finance,
    AsaasClient $asaas,
    AsaasSynchronizer $asaasSynchronizer,
    AsaasWebhookVerifier $asaasWebhook,
    IntegrationRepository $financeIntegrations,
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

    $router->get('/api/whatsapp/webhook', static function (Request $request) use ($whatsappWebhook): Response {
        $mode=$request->queryValue('hub.mode',$request->queryValue('hub_mode'));
        $token=$request->queryValue('hub.verify_token',$request->queryValue('hub_verify_token'));
        $challenge=$request->queryValue('hub.challenge',$request->queryValue('hub_challenge'));
        $answer=$whatsappWebhook->challenge(is_string($mode)?$mode:null,is_string($token)?$token:null,is_string($challenge)?$challenge:null);
        return $answer===null?Response::text("Verificação recusada.\n",403):Response::text($answer);
    });
    $router->postWithoutCsrf('/api/whatsapp/webhook', static function (Request $request) use ($whatsappWebhook,$whatsappMessages,$whatsappCloudApi,$whatsappMedia): Response {
        if(!$whatsappWebhook->validSignature($request->body(),$request->header('x-hub-signature-256')))return Response::text("Assinatura inválida.\n",401);
        $payload=json_decode($request->body(),true);
        if(!is_array($payload))return Response::text("JSON inválido.\n",400);
        $whatsappMessages->receive($payload,$whatsappCloudApi,$whatsappMedia);
        return Response::text("EVENT_RECEIVED\n");
    });
    $router->postWithoutCsrf('/api/asaas/webhook',static function(Request$request)use($asaasWebhook,$finance):Response{
        if(!$asaasWebhook->valid($request->header('asaas-access-token')))return Response::text("Acesso recusado.\n",401);
        $payload=json_decode($request->body(),true);if(!is_array($payload))return Response::text("JSON inválido.\n",400);
        $eventId=trim((string)($payload['id']??''));$eventType=trim((string)($payload['event']??''));$payment=is_array($payload['payment']??null)?$payload['payment']:null;
        if($eventId===''||$eventType==='')return Response::text("Evento inválido.\n",400);
        if(!$finance->registerWebhook($eventId,$eventType,is_array($payment)?(string)($payment['id']??''):null))return Response::text("EVENT_RECEIVED\n");
        try{if($payment!==null)$finance->upsertPayment($payment);$finance->finishWebhook($eventId);}catch(Throwable$e){$finance->finishWebhook($eventId,mb_substr($e->getMessage(),0,500));return Response::text("Falha temporária.\n",500);}
        return Response::text("EVENT_RECEIVED\n");
    });
    $router->get('/notifications/summary',static function()use($auth,$unitContext,$followUps,$whatsappLines,$whatsappMessages):Response{$user=$auth->user();if($user===null)return Response::json(['error'=>'unauthenticated'],401);$follow=['overdue'=>0,'today'=>0,'future'=>0];if($auth->can('crm.contacts.view')){$unit=$unitContext->current();$unitIds=$unit===null?[]:($unit['id']===null?array_map(static fn(array$item):int=>(int)$item['id'],$unitContext->available()):[(int)$unit['id']]);$follow=$followUps->summary($unitIds,$user->id);}$whatsapp=['unread'=>0,'unassigned'=>0];if($auth->can('whatsapp.inbox.view')){$lineIds=array_map(static fn(array$line):int=>(int)$line['id'],$whatsappLines->authorizedForUser($user->id));$whatsapp=$whatsappMessages->notificationSummary($lineIds);}return Response::json(['followups'=>$follow,'whatsapp'=>$whatsapp,'total'=>$follow['overdue']+$follow['today']+$whatsapp['unread']]);},[$requireAuth]);

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
            'canManageWhatsAppLines' => $auth->can('whatsapp.lines.manage'),
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
        $old = $session->get('users.old', []);
        if (!is_array($old)) $old = [];
        return $view->render('users/form', ['title' => ($id === null ? 'Novo usuário' : 'Editar usuário') . ' — ' . $config->string('app.name'), 'user' => $user, 'roles' => $users->availableRoles(), 'units' => $users->availableUnits(), 'selectedRoles' => $old['roles'] ?? ($id === null ? [] : $users->roleIds($id)), 'selectedUnits' => $old['units'] ?? ($id === null ? [] : $users->unitIds($id)), 'old' => $old, 'error' => $session->get('users.error'), 'csrfField' => $csrf->field(), 'basePath' => $basePath]);
    };
    $router->get('/users/create', static fn (): Response => $form(), $manageUsers);
    $router->get('/users/{id:\d+}/edit', static fn (Request $request, array $params): Response => $form((int) $params['id']), $manageUsers);

    $save = static function (Request $request, ?int $id = null) use ($validator, $userManager, $session, $basePath, $ids): Response {
        $roles = $ids($request->input('roles')); $units = $ids($request->input('units')); $active = $request->input('is_active') === '1';
        $result = $validator->validate($request->inputData(), ['name' => 'required|string|min:3|max:120', 'email' => 'required|string|email|max:190', 'password' => ($id === null ? 'required|' : 'nullable|') . 'string|min:12|max:4096|confirmed'], ['name' => 'nome', 'email' => 'e-mail', 'password' => 'senha']);
        try {
            if ($result->fails()) throw new RuntimeException(implode(' ', array_map(static fn (array $errors): string => $errors[0], $result->errors())));
            $name = (string) $result->value('name'); $email = (string) $result->value('email'); $password = $result->value('password');
            if ($id === null) $userManager->create($name, $email, (string) $password, $active, $roles, $units);
            else $userManager->update($id, $name, $email, is_string($password) ? $password : null, $active, $roles, $units);
            $session->flash('users.message', $id === null ? 'Usuário criado.' : 'Usuário atualizado.');
            return Response::redirect($basePath . '/users');
        } catch (Throwable $exception) {
            $session->flash('users.error', $exception->getMessage());
            $session->flash('users.old', ['name' => (string) $request->input('name', ''), 'email' => (string) $request->input('email', ''), 'roles' => $roles, 'units' => $units, 'is_active' => $active]);
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

    $router->get('/crm/contacts/{id:\d+}', static function (Request $request,array $params) use ($view,$contacts,$followUps,$unitContext,$contactUnit,$auth,$session,$basePath,$browserTitle): Response {
        $unitIds=array_map(static fn(array $item):int=>(int)$item['id'],$unitContext->available());
        $contact=$contacts->findForUnits((int)$params['id'],$unitIds);
        if($contact===null)return Response::text("Contato não encontrado.\n",404);
        $currentUnit=$contactUnit();
        $canManageContacts=$auth->can('crm.contacts.manage');
        $canEdit=$canManageContacts&&$currentUnit!==null&&$currentUnit['id']!==null&&(int)$currentUnit['id']===(int)$contact['unit_id'];
        return $view->render('crm/contacts/show',['title'=>$contact['name'].' — '.$browserTitle,'contact'=>$contact,'currentUnit'=>$currentUnit,'canManageContacts'=>$canManageContacts,'canEdit'=>$canEdit,'nextFollowUp'=>$followUps->nextPendingForContact((int)$contact['id'],(int)$contact['unit_id']),'followUps'=>$followUps->forContact((int)$contact['id'],(int)$contact['unit_id']),'events'=>$contacts->events((int)$contact['id'],(int)$contact['unit_id']),'message'=>$session->get('contacts.message'),'error'=>$session->get('contacts.error'),'basePath'=>$basePath]);
    },$viewContacts);

    $contactForm = static function (?int $id=null,array $prefill=[]) use ($view,$contacts,$tags,$units,$contactUnit,$session,$csrf,$basePath,$browserTitle): Response {
        $unit=$contactUnit(); if ($unit===null||$unit['id']===null) return Response::text("Selecione uma unidade específica para cadastrar ou editar contatos.\n",422);
        $contact=$id===null?null:$contacts->find($id,(int)$unit['id']); if ($id!==null&&$contact===null) return Response::text("Contato não encontrado.\n",404);
        return $view->render('crm/contacts/form',['title'=>($id===null?'Novo contato':'Editar contato').' — '.$browserTitle,'contact'=>$contact,'prefill'=>$prefill,'unit'=>$unit,'statuses'=>$contacts->statuses(),'responsibles'=>$contacts->users((int)$unit['id']),'poles'=>array_values(array_filter($units->all(),static fn(array $item):bool=>(int)$item['is_active']===1)),'tags'=>$tags->all(true),'selectedTags'=>$id===null?[]:$tags->idsForContact($id),'error'=>$session->get('contacts.error'),'csrfField'=>$csrf->field(),'basePath'=>$basePath]);
    };
    $router->get('/crm/contacts/create',static function(Request$request)use($contactForm):Response{return$contactForm(null,['name'=>(string)($request->queryValue('name','')??''),'phone'=>substr((string)($request->queryValue('phone','')??''),-11),'whatsapp_conversation_id'=>(int)($request->queryValue('whatsapp_conversation','0')??'0')]);},$manageContacts);
    $router->get('/crm/contacts/{id:\d+}/edit',static fn(Request $request,array $params):Response=>$contactForm((int)$params['id']),$manageContacts);

    $saveContact=static function(Request $request,?int $id=null) use($validator,$contactManager,$whatsappLines,$whatsappMessages,$units,$contactUnit,$auth,$session,$basePath):Response {
        $unit=$contactUnit(); if($unit===null||$unit['id']===null)return Response::text("Selecione uma unidade específica.\n",422);
        $result=$validator->validate($request->inputData(),['name'=>'required|string|min:2|max:160','email'=>'required|string|email|max:190','phone'=>'required|string|max:16','course'=>'required|string|max:160','origin_city'=>'required|string|max:120','document'=>'required|string|max:18','notes'=>'required|string|min:2|max:5000','interest_score'=>'required|string','responsible_user_id'=>'required|string','registration_source'=>'required|string|in:internal,external_form','registered_at'=>'required|string'],['name'=>'nome','email'=>'e-mail','phone'=>'telefone','course'=>'curso','origin_city'=>'polo','document'=>'documento','notes'=>'observações','interest_score'=>'interesse','responsible_user_id'=>'atendente','registration_source'=>'origem do cadastro','registered_at'=>'data e hora']);
        try { if($result->fails())throw new RuntimeException(implode(' ',array_map(static fn(array $errors):string=>$errors[0],$result->errors()))); $allowedPoles=array_map(static fn(array $item):string=>(string)$item['name'],array_filter($units->all(),static fn(array $item):bool=>(int)$item['is_active']===1));if(!in_array((string)$result->value('origin_city'),$allowedPoles,true))throw new RuntimeException('Selecione um Polo/Cidade válido.'); $data=$request->inputData(); foreach($result->values() as $key=>$value)$data[$key]=$value; $savedId=$contactManager->save($id,(int)$unit['id'],$auth->user()->id,$data);$conversationId=(int)$request->input('whatsapp_conversation_id','0');if($id===null&&$conversationId>0){$lineIds=array_map(static fn(array$l):int=>(int)$l['id'],$whatsappLines->authorizedForUser($auth->user()->id));if($whatsappMessages->linkContact($conversationId,$savedId,$lineIds)){$session->flash('whatsapp.message','Contato criado e vinculado ao WhatsApp.');return Response::redirect($basePath.'/whatsapp?conversation='.$conversationId);}} $session->flash('contacts.message',$id===null?'Contato criado.':'Contato atualizado.'); return Response::redirect($basePath.'/crm/contacts/'.$savedId); }
        catch(Throwable $exception){$session->flash('contacts.error',$exception->getMessage());return Response::redirect($basePath.($id===null?'/crm/contacts/create':"/crm/contacts/{$id}/edit"));}
    };
    $router->post('/crm/contacts',static fn(Request $request):Response=>$saveContact($request),$manageContacts);
    $router->post('/crm/contacts/{id:\d+}',static fn(Request $request,array $params):Response=>$saveContact($request,(int)$params['id']),$manageContacts);

    $router->get('/crm/follow-ups',static function(Request $request)use($view,$followUps,$contactUnit,$unitContext,$session,$basePath,$browserTitle):Response{$unit=$contactUnit();if($unit===null)return Response::text("Nenhuma unidade ativa.\n",422);$unitIds=$unit['id']===null?array_map(static fn(array $item):int=>(int)$item['id'],$unitContext->available()):[(int)$unit['id']];$status=(string)$request->queryValue('status','pending');$period=(string)$request->queryValue('period','');$responsible=max(0,(int)$request->queryValue('responsible','0'));$search=trim((string)$request->queryValue('q',''));$items=$followUps->allForUnits($unitIds,$status,$period,$responsible,$search);return $view->render('crm/follow-ups/index',['title'=>'Follow-ups — '.$browserTitle,'followUps'=>$items,'resultCount'=>count($items),'search'=>$search,'summary'=>$followUps->summary($unitIds),'responsibles'=>$followUps->responsiblesForUnits($unitIds),'selectedStatus'=>$status,'selectedPeriod'=>$period,'selectedResponsible'=>$responsible,'unit'=>$unit,'message'=>$session->get('followups.message'),'error'=>$session->get('followups.error'),'basePath'=>$basePath]);},$viewContacts);

    $router->get('/crm/contacts/{id:\d+}/follow-ups/create',static function(Request $request,array $params)use($view,$contacts,$followUps,$contactUnit,$session,$csrf,$basePath,$browserTitle,$auth):Response{$unit=$contactUnit();if($unit===null||$unit['id']===null)return Response::text("Selecione uma unidade específica.\n",422);$contact=$contacts->find((int)$params['id'],(int)$unit['id']);if($contact===null)return Response::text("Contato não encontrado.\n",404);return $view->render('crm/follow-ups/form',['title'=>'Novo follow-up — '.$browserTitle,'contact'=>$contact,'unit'=>$unit,'responsibles'=>$contacts->users((int)$unit['id']),'selectedResponsibleId'=>$auth->user()->id,'history'=>$followUps->forContact((int)$contact['id'],(int)$unit['id']),'error'=>$session->get('followups.error'),'csrfField'=>$csrf->field(),'basePath'=>$basePath]);},$manageContacts);

    $router->post('/crm/contacts/{id:\d+}/follow-ups',static function(Request $request,array $params)use($validator,$contacts,$followUps,$contactUnit,$auth,$session,$basePath):Response{$unit=$contactUnit();if($unit===null||$unit['id']===null)return Response::text("Selecione uma unidade específica.\n",422);$contactId=(int)$params['id'];if($contacts->find($contactId,(int)$unit['id'])===null)return Response::text("Contato não encontrado.\n",404);$result=$validator->validate($request->inputData(),['action'=>'required|string|min:2|max:160','scheduled_at'=>'required|string','responsible_user_id'=>'required|string','notes'=>'required|string|min:2|max:5000'],['action'=>'próxima ação','scheduled_at'=>'data e hora','responsible_user_id'=>'atendente','notes'=>'observações']);try{if($result->fails())throw new RuntimeException(implode(' ',array_map(static fn(array $errors):string=>$errors[0],$result->errors())));$responsible=(int)$result->value('responsible_user_id');if(!$contacts->userBelongsToUnit($responsible,(int)$unit['id']))throw new RuntimeException('Atendente indisponível nesta unidade.');$scheduled=DateTimeImmutable::createFromFormat('Y-m-d\TH:i',(string)$result->value('scheduled_at'));if($scheduled===false)throw new RuntimeException('Informe uma data e hora válidas.');$followUps->create($contactId,$responsible,(string)$result->value('action'),$scheduled->format('Y-m-d H:i:s'),(string)$result->value('notes'),$auth->user()->id);$session->flash('contacts.message','Follow-up criado.');return Response::redirect($basePath.'/crm/contacts/'.$contactId);}catch(Throwable $exception){$session->flash('followups.error',$exception->getMessage());return Response::redirect($basePath."/crm/contacts/{$contactId}/follow-ups/create");}},$manageContacts);

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

    $viewWhatsApp=[$requireAuth,new RequirePermission($auth,'whatsapp.inbox.view')];
    $router->get('/whatsapp',static function(Request$request)use($view,$whatsappLines,$whatsappMessages,$whatsappTemplates,$whatsappCloudApi,$auth,$csrf,$session,$basePath,$browserTitle):Response{$lines=$whatsappLines->authorizedForUser($auth->user()->id);$lineIds=array_map(static fn(array$l):int=>(int)$l['id'],$lines);$scope=(string)($request->queryValue('scope','open')??'open');if(!in_array($scope,['all','open','mine','unassigned','unread','overdue','closed'],true))$scope='open';$search=trim((string)($request->queryValue('q','')??''));$conversationId=(int)($request->queryValue('conversation','0')??'0');$conversations=$whatsappMessages->conversations($lineIds,$scope,$search,$auth->user()->id);$active=$conversationId>0?$whatsappMessages->conversation($conversationId,$lineIds):($conversations[0]??null);$messages=[];$linkable=[];$sendAvailability=['allowed'=>false,'reason'=>'Selecione uma conversa.'];$templateAvailability=$sendAvailability;if($active!==null){if(isset($active['last_body']))$active=$whatsappMessages->conversation((int)$active['id'],$lineIds);if($active!==null){$messages=$whatsappMessages->messages((int)$active['id']);$whatsappMessages->markRead((int)$active['id']);$linkable=$whatsappMessages->linkableContacts((int)$active['id'],$lineIds);$sendAvailability=$whatsappMessages->sendAvailability((int)$active['id'],$lineIds,$auth->user()->id,$whatsappCloudApi->ready());$templateAvailability=$whatsappMessages->templateAvailability((int)$active['id'],$lineIds,$auth->user()->id,$whatsappCloudApi->ready());}}$hasAny=$active!==null||$conversations!==[]||$whatsappMessages->conversations($lineIds,'all','',$auth->user()->id)!==[];return$view->render('whatsapp/inbox',['title'=>'WhatsApp — '.$browserTitle,'lines'=>$lines,'conversations'=>$conversations,'activeConversation'=>$active,'messages'=>$messages,'attendants'=>$whatsappMessages->attendants($lineIds),'linkableContacts'=>$linkable,'templates'=>$whatsappTemplates->approved(),'scope'=>$scope,'search'=>$search,'sendAvailability'=>$sendAvailability,'templateAvailability'=>$templateAvailability,'demoMode'=>!$hasAny,'message'=>$session->get('whatsapp.message'),'error'=>$session->get('whatsapp.error'),'csrfField'=>$csrf->field(),'basePath'=>$basePath]);},$viewWhatsApp);
    $router->post('/whatsapp/{id:\d+}/send',static function(Request$request,array$params)use($whatsappLines,$whatsappMessages,$whatsappCloudApi,$auth,$session,$basePath):Response{$lineIds=array_map(static fn(array$l):int=>(int)$l['id'],$whatsappLines->authorizedForUser($auth->user()->id));try{$whatsappMessages->sendText((int)$params['id'],$lineIds,$auth->user()->id,(string)$request->input('message',''),$whatsappCloudApi);$session->flash('whatsapp.message','Mensagem enviada.');}catch(Throwable$e){$session->flash('whatsapp.error',$e->getMessage());}return Response::redirect($basePath.'/whatsapp?conversation='.(int)$params['id']);},$viewWhatsApp);
    $router->post('/whatsapp/{id:\d+}/send-template',static function(Request$request,array$params)use($whatsappLines,$whatsappMessages,$whatsappTemplates,$whatsappCloudApi,$auth,$session,$basePath):Response{$conversationId=(int)$params['id'];$lineIds=array_map(static fn(array$l):int=>(int)$l['id'],$whatsappLines->authorizedForUser($auth->user()->id));try{if($request->input('confirm')!=='1')throw new RuntimeException('Confirme a mensagem antes do envio.');$template=$whatsappTemplates->findApproved((int)$request->input('template_id','0'));if($template===null)throw new RuntimeException('Selecione um modelo ativo e aprovado pela Meta.');$conversation=$whatsappMessages->conversation($conversationId,$lineIds);if($conversation===null)throw new RuntimeException('Conversa não encontrada.');$rendered=$whatsappTemplates->render($template,['nome'=>(string)($conversation['crm_name']?:$conversation['contact_name']?:'Contato'),'curso'=>(string)($conversation['crm_course']?:'curso de interesse'),'unidade'=>(string)$conversation['unit_name'],'atendente'=>(string)($conversation['assigned_name']?:$auth->user()->name)]);$whatsappMessages->sendTemplate($conversationId,$lineIds,$auth->user()->id,$template,$rendered,$whatsappCloudApi);$session->flash('whatsapp.message','Modelo oficial enviado.');}catch(Throwable$e){$session->flash('whatsapp.error',$e->getMessage());}return Response::redirect($basePath.'/whatsapp?conversation='.$conversationId);},$viewWhatsApp);
    $router->get('/whatsapp/attachments/{id:\d+}',static function(Request$request,array$params)use($whatsappLines,$whatsappMessages,$whatsappMedia,$auth):Response{$lineIds=array_map(static fn(array$l):int=>(int)$l['id'],$whatsappLines->authorizedForUser($auth->user()->id));$attachment=$whatsappMessages->attachment((int)$params['id'],$lineIds);if($attachment===null)return Response::text("Anexo não encontrado.\n",404);try{$body=$whatsappMedia->read((string)$attachment['storage_path']);$name=preg_replace('/[^\pL\pN._ -]+/u','_',basename((string)$attachment['file_name']))?:'anexo';$mime=(string)$attachment['mime_type'];$inline=$request->queryValue('inline')==='1'&&(str_starts_with($mime,'image/')||str_starts_with($mime,'audio/'));return(new Response($body,200))->withHeaders(['Content-Type'=>$mime,'Content-Length'=>(string)strlen($body),'Content-Disposition'=>($inline?'inline':'attachment').'; filename="'.str_replace('"','',$name).'"','X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store','Content-Security-Policy'=>"default-src 'none'; sandbox"]);}catch(Throwable){return Response::text("Anexo indisponível.\n",404);}},$viewWhatsApp);
    $router->post('/whatsapp/{id:\d+}/assign',static function(Request$request,array$params)use($whatsappLines,$whatsappMessages,$auth,$session,$basePath):Response{$lineIds=array_map(static fn(array$l):int=>(int)$l['id'],$whatsappLines->authorizedForUser($auth->user()->id));$requested=(int)$request->input('user_id',$auth->user()->id);$userId=$auth->can('whatsapp.conversations.assign')?$requested:$auth->user()->id;$ok=$whatsappMessages->assign((int)$params['id'],$userId,$lineIds,$auth->user()->id);$session->flash($ok?'whatsapp.message':'whatsapp.error',$ok?($userId===$auth->user()->id?'Conversa assumida por você.':'Conversa transferida.'):'Não foi possível atribuir a conversa.');return Response::redirect($basePath.'/whatsapp?conversation='.(int)$params['id']);},$viewWhatsApp);
    $router->post('/whatsapp/{id:\d+}/link-contact',static function(Request$request,array$params)use($whatsappLines,$whatsappMessages,$auth,$session,$basePath):Response{$lineIds=array_map(static fn(array$l):int=>(int)$l['id'],$whatsappLines->authorizedForUser($auth->user()->id));$contactId=(int)$request->input('contact_id','0');$ok=$contactId>0&&$whatsappMessages->linkContact((int)$params['id'],$contactId,$lineIds);$session->flash($ok?'whatsapp.message':'whatsapp.error',$ok?'Contato vinculado ao CRM.':'Selecione um contato válido da mesma unidade.');return Response::redirect($basePath.'/whatsapp?conversation='.(int)$params['id']);},$viewWhatsApp);
    $router->post('/whatsapp/{id:\d+}/status',static function(Request$request,array$params)use($whatsappLines,$whatsappMessages,$auth,$session,$basePath):Response{$lineIds=array_map(static fn(array$l):int=>(int)$l['id'],$whatsappLines->authorizedForUser($auth->user()->id));$status=(string)$request->input('status','');$resolution=(string)$request->input('resolution','');try{$ok=$whatsappMessages->setConversationStatus((int)$params['id'],$status,$lineIds,$auth->user()->id,$resolution);$session->flash($ok?'whatsapp.message':'whatsapp.error',$ok?($status==='closed'?'Conversa encerrada.':'Conversa reaberta.'):'Não foi possível alterar a conversa.');}catch(Throwable$e){$session->flash('whatsapp.error',$e->getMessage());}return Response::redirect($basePath.'/whatsapp?scope='.($status==='open'?'open':'closed').'&conversation='.(int)$params['id']);},$viewWhatsApp);

    $simulateWhatsApp=[$requireAuth,new RequirePermission($auth,'whatsapp.lines.manage')];
    $router->get('/whatsapp/simulator',static function()use($view,$whatsappLines,$session,$csrf,$basePath,$browserTitle):Response{return$view->render('whatsapp/simulator',['title'=>'Simular mensagem — '.$browserTitle,'lines'=>array_values(array_filter($whatsappLines->all(),static fn(array$l):bool=>(int)$l['is_active']===1)),'error'=>$session->get('whatsapp.simulator.error'),'csrfField'=>$csrf->field(),'basePath'=>$basePath]);},$simulateWhatsApp);
    $router->post('/whatsapp/simulator',static function(Request$request)use($validator,$whatsappLines,$whatsappMessages,$whatsappMedia,$session,$basePath):Response{$result=$validator->validate($request->inputData(),['line_id'=>'required|string','name'=>'required|string|min:2|max:160','phone'=>'required|string|max:20','message'=>'required|string|min:1|max:4096'],['line_id'=>'linha','name'=>'nome','phone'=>'telefone','message'=>'mensagem']);try{if($result->fails())throw new RuntimeException(implode(' ',array_map(static fn(array$errors):string=>$errors[0],$result->errors())));$lineId=(int)$result->value('line_id');$line=$whatsappLines->find($lineId);if($line===null||(int)$line['is_active']!==1)throw new RuntimeException('Selecione uma linha ativa.');$attachment=$whatsappMedia->storeUploaded($request->file('attachment'));$conversation=$whatsappMessages->simulateInbound($lineId,(string)$result->value('name'),(string)$result->value('phone'),(string)$result->value('message'),$attachment);$session->flash('whatsapp.message',$attachment===null?'Mensagem de teste recebida.':'Mensagem e anexo de teste recebidos.');return Response::redirect($basePath.'/whatsapp?conversation='.$conversation);}catch(Throwable$e){$session->flash('whatsapp.simulator.error',$e->getMessage());return Response::redirect($basePath.'/whatsapp/simulator');}},$simulateWhatsApp);

    $viewFinance=[$requireAuth,new RequirePermission($auth,'finance.view')];
    $router->get('/finance',static fn():Response=>Response::redirect($basePath.'/finance/customers'),$viewFinance);
    $router->post('/finance/sync',static function()use($asaasSynchronizer,$session,$basePath):Response{try{$result=$asaasSynchronizer->syncBatch();$detail=$result['customers']>0?sprintf('%d cliente(s) importado(s).',$result['customers']):sprintf('%d cobrança(s) importada(s).',$result['payments']);$session->flash('finance_settings.message',$result['complete']?'Sincronização concluída. '.$detail:$detail.' Clique novamente para continuar do ponto salvo.');}catch(Throwable$e){$session->flash('finance_settings.error',$e->getMessage());}return Response::redirect($basePath.'/admin/integrations/asaas');},[$requireAuth,new RequirePermission($auth,'finance.manage')]);
    $financeScope=static function()use($unitContext):array{$unit=$unitContext->current();return$unit===null?[]:($unit['id']===null?array_map(static fn(array$item):int=>(int)$item['id'],$unitContext->available()):[(int)$unit['id']]);};
    $router->get('/finance/customers',static function(Request$request)use($view,$finance,$financeScope,$auth,$session,$basePath,$browserTitle):Response{$search=trim((string)($request->queryValue('q','')??''));$scope=(string)($request->queryValue('scope','all')??'all');if(!in_array($scope,['all','legacy','units'],true))$scope='all';if(!$auth->can('finance.legacy_view')&&$scope==='legacy')$scope='units';$order=(string)($request->queryValue('order','name')??'name');if(!in_array($order,['name','recent','open','charges'],true))$order='name';$page=max(1,(int)($request->queryValue('page','1')??'1'));return$view->render('finance/customers/index',['title'=>'Clientes financeiros — '.$browserTitle,'result'=>$finance->customers($financeScope(),$auth->can('finance.legacy_view'),$search,$scope,$order,$page),'search'=>$search,'scope'=>$scope,'order'=>$order,'canViewLegacy'=>$auth->can('finance.legacy_view'),'message'=>$session->get('finance_customers.message'),'error'=>$session->get('finance_customers.error'),'basePath'=>$basePath]);},$viewFinance);
    $router->get('/finance/customers/{id:\d+}',static function(Request$request,array$params)use($view,$finance,$financeScope,$unitContext,$auth,$session,$csrf,$basePath,$browserTitle):Response{$customer=$finance->customer((int)$params['id'],$financeScope(),$auth->can('finance.legacy_view'));if($customer===null)return Response::text("Cliente financeiro não encontrado.\n",404);$available=$unitContext->available();return$view->render('finance/customers/show',['title'=>(string)$customer['name'].' — Financeiro','customer'=>$customer,'payments'=>$finance->customerPayments((int)$customer['id']),'units'=>$available,'candidates'=>$finance->crmCandidates($customer,array_map(static fn(array$u):int=>(int)$u['id'],$available)),'canReconcile'=>$auth->can('finance.manage')&&$auth->can('finance.legacy_view'),'message'=>$session->get('finance_customers.message'),'error'=>$session->get('finance_customers.error'),'csrfField'=>$csrf->field(),'basePath'=>$basePath]);},$viewFinance);
    $router->post('/finance/customers/{id:\d+}/reconcile',static function(Request$request,array$params)use($finance,$unitContext,$session,$basePath):Response{try{$unitId=(int)$request->input('unit_id','0');$allowed=array_map(static fn(array$u):int=>(int)$u['id'],$unitContext->available());if(!in_array($unitId,$allowed,true))throw new RuntimeException('Selecione uma unidade permitida.');$contactId=(int)$request->input('crm_contact_id','0');$finance->reconcileCustomer((int)$params['id'],$unitId,$contactId>0?$contactId:null);$session->flash('finance_customers.message','Cliente conciliado com a unidade.');return Response::redirect($basePath.'/finance/customers');}catch(Throwable$e){$session->flash('finance_customers.error',$e->getMessage());return Response::redirect($basePath.'/finance/customers/'.(int)$params['id']);}},[$requireAuth,new RequirePermission($auth,'finance.manage'),new RequirePermission($auth,'finance.legacy_view')]);
    $manageFinancePayments=[$requireAuth,new RequirePermission($auth,'finance.manage')];
    $router->get('/finance/customers/{id:\d+}/payments/create',static function(Request$request,array$params)use($view,$finance,$financeScope,$auth,$session,$csrf,$asaas,$basePath,$browserTitle):Response{$customer=$finance->customer((int)$params['id'],$financeScope(),$auth->can('finance.legacy_view'));if($customer===null)return Response::text("Cliente financeiro não encontrado.\n",404);if($customer['unit_id']===null){$session->flash('finance_customers.error','Concilie o cliente com uma unidade antes de emitir cobranças.');return Response::redirect($basePath.'/finance/customers/'.(int)$params['id']);}return$view->render('finance/payments/form',['title'=>'Nova cobrança — '.$browserTitle,'customer'=>$customer,'writeEnabled'=>$asaas->paymentsWriteEnabled(),'error'=>$session->get('finance_payments.error'),'csrfField'=>$csrf->field(),'basePath'=>$basePath]);},$manageFinancePayments);
    $router->post('/finance/customers/{id:\d+}/payments',static function(Request$request,array$params)use($finance,$financeScope,$auth,$asaas,$session,$basePath):Response{$id=(int)$params['id'];try{$customer=$finance->customer($id,$financeScope(),$auth->can('finance.legacy_view'));if($customer===null)throw new RuntimeException('Cliente financeiro não encontrado.');if($customer['unit_id']===null)throw new RuntimeException('Concilie o cliente com uma unidade antes de emitir cobranças.');$billing=trim((string)$request->input('billing_type',''));if(!in_array($billing,['PIX','BOLETO'],true))throw new RuntimeException('Selecione PIX ou boleto.');$rawValue=str_replace(['. ','.',','],['','.', '.'],trim((string)$request->input('value','')));$value=(float)$rawValue;if($value<=0)throw new RuntimeException('Informe um valor maior que zero.');$dueDate=trim((string)$request->input('due_date',''));$date=DateTimeImmutable::createFromFormat('!Y-m-d',$dueDate);if(!$date||$date->format('Y-m-d')!==$dueDate||$dueDate<date('Y-m-d'))throw new RuntimeException('Informe um vencimento válido, igual ou posterior a hoje.');$description=trim((string)$request->input('description',''));if($description===''||mb_strlen($description)>500)throw new RuntimeException('Informe a descrição ou o curso, com até 500 caracteres.');$payment=$asaas->createPayment(['customer'=>(string)$customer['asaas_customer_id'],'billingType'=>$billing,'value'=>$value,'dueDate'=>$dueDate,'description'=>$description,'externalReference'=>sprintf('painel:unit:%d:customer:%d',(int)$customer['unit_id'],$id)]);$finance->upsertPayment($payment);$session->flash('finance_customers.message','Cobrança emitida com sucesso.');return Response::redirect($basePath.'/finance/customers/'.$id);}catch(Throwable$e){$session->flash('finance_payments.error',$e->getMessage());return Response::redirect($basePath.'/finance/customers/'.$id.'/payments/create');}},$manageFinancePayments);
    $router->get('/finance/customers/{customerId:\d+}/payments/{paymentId:\d+}/pix',static function(Request$request,array$params)use($view,$finance,$financeScope,$auth,$asaas,$basePath,$browserTitle):Response{$customer=$finance->customer((int)$params['customerId'],$financeScope(),$auth->can('finance.legacy_view'));if($customer===null)return Response::text("Cliente financeiro não encontrado.\n",404);$payment=$finance->customerPayment((int)$customer['id'],(int)$params['paymentId']);if($payment===null||!in_array($payment['billing_type'],['PIX','BOLETO','UNDEFINED'],true))return Response::text("PIX não encontrado.\n",404);$pix=['encodedImage'=>'','payload'=>'','expirationDate'=>''];$error=null;try{$pix=$asaas->pixQrCode((string)$payment['asaas_payment_id']);}catch(Throwable$e){$error=$e->getMessage();}return$view->render('finance/payments/pix',['title'=>'PIX — '.$browserTitle,'customer'=>$customer,'payment'=>$payment,'pix'=>$pix,'error'=>$error,'basePath'=>$basePath]);},$viewFinance);
    $router->get('/finance/customers/{customerId:\d+}/payments/{paymentId:\d+}/edit',static function(Request$request,array$params)use($view,$finance,$financeScope,$auth,$session,$csrf,$basePath,$browserTitle):Response{$customer=$finance->customer((int)$params['customerId'],$financeScope(),$auth->can('finance.legacy_view'));if($customer===null)return Response::text("Cliente financeiro não encontrado.\n",404);$payment=$finance->customerPayment((int)$customer['id'],(int)$params['paymentId']);if($payment===null||!in_array($payment['status'],['PENDING','OVERDUE'],true))return Response::text("Esta cobrança não pode ser alterada.\n",409);return$view->render('finance/payments/edit',['title'=>'Editar cobrança — '.$browserTitle,'customer'=>$customer,'payment'=>$payment,'error'=>$session->get('finance_payments.error'),'csrfField'=>$csrf->field(),'basePath'=>$basePath]);},$manageFinancePayments);
    $router->post('/finance/customers/{customerId:\d+}/payments/{paymentId:\d+}',static function(Request$request,array$params)use($finance,$financeScope,$auth,$asaas,$session,$basePath):Response{$customerId=(int)$params['customerId'];$paymentId=(int)$params['paymentId'];try{$customer=$finance->customer($customerId,$financeScope(),$auth->can('finance.legacy_view'));$payment=$customer===null?null:$finance->customerPayment($customerId,$paymentId);if($customer===null||$payment===null)throw new RuntimeException('Cobrança não encontrada.');if(!in_array($payment['status'],['PENDING','OVERDUE'],true))throw new RuntimeException('Somente cobranças pendentes ou vencidas podem ser alteradas.');$billing=trim((string)$request->input('billing_type',''));if(!in_array($billing,['PIX','BOLETO'],true))throw new RuntimeException('Selecione PIX ou boleto.');$value=(float)str_replace(',','.',trim((string)$request->input('value','')));if($value<5)throw new RuntimeException('O valor mínimo é R$ 5,00.');$dueDate=trim((string)$request->input('due_date',''));$date=DateTimeImmutable::createFromFormat('!Y-m-d',$dueDate);if(!$date||$date->format('Y-m-d')!==$dueDate||$dueDate<date('Y-m-d'))throw new RuntimeException('Informe um vencimento válido.');$description=trim((string)$request->input('description',''));if($description===''||mb_strlen($description)>500)throw new RuntimeException('Informe uma descrição com até 500 caracteres.');$updated=$asaas->updatePayment((string)$payment['asaas_payment_id'],['billingType'=>$billing,'value'=>$value,'dueDate'=>$dueDate,'description'=>$description,'externalReference'=>(string)$payment['external_reference']]);$finance->upsertPayment($updated);$session->flash('finance_customers.message','Cobrança atualizada com sucesso.');return Response::redirect($basePath.'/finance/customers/'.$customerId);}catch(Throwable$e){$session->flash('finance_payments.error',$e->getMessage());return Response::redirect($basePath.'/finance/customers/'.$customerId.'/payments/'.$paymentId.'/edit');}},$manageFinancePayments);
    $router->post('/finance/customers/{customerId:\d+}/payments/{paymentId:\d+}/cancel',static function(Request$request,array$params)use($finance,$financeScope,$auth,$asaas,$session,$basePath):Response{$customerId=(int)$params['customerId'];try{$customer=$finance->customer($customerId,$financeScope(),$auth->can('finance.legacy_view'));$payment=$customer===null?null:$finance->customerPayment($customerId,(int)$params['paymentId']);if($customer===null||$payment===null)throw new RuntimeException('Cobrança não encontrada.');if(!in_array($payment['status'],['PENDING','OVERDUE'],true))throw new RuntimeException('Cobranças recebidas não podem ser canceladas por este fluxo.');$asaas->deletePayment((string)$payment['asaas_payment_id']);$finance->markPaymentDeleted((int)$payment['id']);$session->flash('finance_customers.message','Cobrança cancelada.');}catch(Throwable$e){$session->flash('finance_customers.error',$e->getMessage());}return Response::redirect($basePath.'/finance/customers/'.$customerId);},$manageFinancePayments);
    $manageFinanceSettings=[$requireAuth,new RequirePermission($auth,'finance.settings.manage')];
    $router->get('/admin/integrations/asaas',static function()use($view,$config,$finance,$asaas,$asaasWebhook,$financeIntegrations,$unitContext,$session,$csrf,$basePath,$browserTitle):Response{$unitIds=array_map(static fn(array$unit):int=>(int)$unit['id'],$unitContext->available());return$view->render('finance/settings',['title'=>'Integração Asaas — '.$browserTitle,'settings'=>$financeIntegrations->asaas(),'summary'=>$finance->summary($unitIds,true),'webhookSummary'=>$finance->webhookSummary(),'webhookEvents'=>$finance->webhookEvents(),'connectionReady'=>$asaas->ready(),'webhookReady'=>$asaasWebhook->ready(),'webhookUrl'=>rtrim($config->string('app.url'),'/').'/api/asaas/webhook','encryptionReady'=>$financeIntegrations->encryptionReady(),'message'=>$session->get('finance_settings.message'),'error'=>$session->get('finance_settings.error'),'csrfField'=>$csrf->field(),'basePath'=>$basePath]);},$manageFinanceSettings);
    $router->post('/admin/integrations/asaas',static function(Request$request)use($financeIntegrations,$auth,$session,$basePath):Response{try{$key=trim((string)$request->input('api_key',''));if($key==='')throw new RuntimeException('Informe a chave da API.');$financeIntegrations->saveAsaas($key,$auth->user()->id,$request->input('is_active')==='1');$session->flash('finance_settings.message','Conexão do Asaas salva com segurança.');}catch(Throwable$e){$session->flash('finance_settings.error',$e->getMessage());}return Response::redirect($basePath.'/admin/integrations/asaas');},$manageFinanceSettings);
    $router->post('/admin/integrations/asaas/webhook/test',static function()use($finance,$session,$basePath):Response{$id='local_test_'.bin2hex(random_bytes(8));try{$finance->registerWebhook($id,'LOCAL_WEBHOOK_TEST',null);$finance->finishWebhook($id);$finance->registerWebhook($id,'LOCAL_WEBHOOK_TEST',null);$session->flash('finance_settings.message','Teste interno concluído: processamento e idempotência estão funcionando.');}catch(Throwable$e){$session->flash('finance_settings.error','Falha no teste interno: '.$e->getMessage());}return Response::redirect($basePath.'/admin/integrations/asaas#webhook-diagnostics');},$manageFinanceSettings);

    $manageWhatsAppLines=[$requireAuth,new RequirePermission($auth,'whatsapp.lines.manage')];
    $router->get('/whatsapp/lines',static function()use($view,$whatsappLines,$session,$basePath,$browserTitle):Response{return$view->render('whatsapp/lines/index',['title'=>'Linhas do WhatsApp — '.$browserTitle,'lines'=>$whatsappLines->all(),'message'=>$session->get('whatsapp_lines.message'),'error'=>$session->get('whatsapp_lines.error'),'basePath'=>$basePath]);},$manageWhatsAppLines);
    $whatsappLineForm=static function(?int$id=null)use($view,$config,$whatsappLines,$units,$session,$csrf,$basePath,$browserTitle):Response{$line=$id===null?null:$whatsappLines->find($id);if($id!==null&&$line===null)return Response::text("Linha não encontrada.\n",404);$verifyToken=$config->get('app.whatsapp_verify_token');$appSecret=$config->get('app.whatsapp_app_secret');$accessToken=$config->get('app.whatsapp_access_token');return$view->render('whatsapp/lines/form',['title'=>($id===null?'Nova linha':'Editar linha').' — '.$browserTitle,'line'=>$line,'units'=>array_values(array_filter($units->all(),static fn(array$item):bool=>(int)$item['is_active']===1)),'users'=>$whatsappLines->availableUsers(),'selectedUsers'=>$id===null?[]:$whatsappLines->selectedUserIds($id),'webhookUrl'=>rtrim($config->string('app.url'),'/').'/api/whatsapp/webhook','credentialsReady'=>is_string($verifyToken)&&$verifyToken!==''&&is_string($appSecret)&&$appSecret!==''&&is_string($accessToken)&&$accessToken!=='','error'=>$session->get('whatsapp_lines.error'),'csrfField'=>$csrf->field(),'basePath'=>$basePath]);};
    $router->get('/whatsapp/lines/create',static fn():Response=>$whatsappLineForm(),$manageWhatsAppLines);
    $router->get('/whatsapp/lines/{id:\d+}/edit',static fn(Request$request,array$params):Response=>$whatsappLineForm((int)$params['id']),$manageWhatsAppLines);
    $saveWhatsAppLine=static function(Request$request,?int$id=null)use($validator,$whatsappLines,$session,$basePath,$ids):Response{$result=$validator->validate($request->inputData(),['name'=>'required|string|min:2|max:120','unit_id'=>'required|string','phone'=>'required|string|max:20','waba_id'=>'nullable|string|max:80','phone_number_id'=>'nullable|string|max:80'],['name'=>'nome','unit_id'=>'unidade','phone'=>'número','waba_id'=>'ID da conta WhatsApp Business','phone_number_id'=>'ID do número na Meta']);try{if($result->fails())throw new RuntimeException(implode(' ',array_map(static fn(array$errors):string=>$errors[0],$result->errors())));$digits=preg_replace('/\D/','',(string)$result->value('phone'));if(in_array(strlen((string)$digits),[10,11],true))$digits='55'.$digits;if(!in_array(strlen((string)$digits),[12,13],true)||!str_starts_with((string)$digits,'55'))throw new RuntimeException('Informe um número brasileiro com DDD válido.');$waba=trim((string)($result->value('waba_id')??''));$phoneId=trim((string)($result->value('phone_number_id')??''));if(($waba!==''&&!ctype_digit($waba))||($phoneId!==''&&!ctype_digit($phoneId)))throw new RuntimeException('Os identificadores da Meta devem conter somente números.');$whatsappLines->save($id,(int)$result->value('unit_id'),(string)$result->value('name'),'+'.$digits,$request->input('is_active')==='1',$ids($request->input('users')),$waba?:null,$phoneId?:null);$session->flash('whatsapp_lines.message',$id===null?'Linha cadastrada.':'Linha atualizada.');return Response::redirect($basePath.'/whatsapp/lines');}catch(Throwable$exception){$session->flash('whatsapp_lines.error',$exception->getMessage());return Response::redirect($basePath.($id===null?'/whatsapp/lines/create':"/whatsapp/lines/{$id}/edit"));}};
    $router->post('/whatsapp/lines',static fn(Request$request):Response=>$saveWhatsAppLine($request),$manageWhatsAppLines);
    $router->post('/whatsapp/lines/{id:\d+}',static fn(Request$request,array$params):Response=>$saveWhatsAppLine($request,(int)$params['id']),$manageWhatsAppLines);
    $manageWhatsAppTemplates=[$requireAuth,new RequirePermission($auth,'whatsapp.lines.manage')];
    $router->get('/whatsapp/templates',static function()use($view,$whatsappTemplates,$session,$basePath,$browserTitle):Response{return$view->render('whatsapp/templates/index',['title'=>'Modelos do WhatsApp — '.$browserTitle,'templates'=>$whatsappTemplates->all(),'message'=>$session->get('whatsapp_templates.message'),'error'=>$session->get('whatsapp_templates.error'),'basePath'=>$basePath]);},$manageWhatsAppTemplates);
    $templateForm=static function(?int$id=null)use($view,$whatsappTemplates,$session,$csrf,$basePath,$browserTitle):Response{$templateItem=$id===null?null:$whatsappTemplates->find($id);if($id!==null&&$templateItem===null)return Response::text("Modelo não encontrado.\n",404);return$view->render('whatsapp/templates/form',['title'=>($id===null?'Novo modelo':'Editar modelo').' — '.$browserTitle,'templateItem'=>$templateItem,'error'=>$session->get('whatsapp_templates.error'),'csrfField'=>$csrf->field(),'basePath'=>$basePath]);};
    $router->get('/whatsapp/templates/create',static fn():Response=>$templateForm(),$manageWhatsAppTemplates);
    $router->get('/whatsapp/templates/{id:\d+}/edit',static fn(Request$request,array$params):Response=>$templateForm((int)$params['id']),$manageWhatsAppTemplates);
    $saveTemplate=static function(Request$request,?int$id=null)use($validator,$whatsappTemplates,$session,$basePath):Response{$result=$validator->validate($request->inputData(),['name'=>'required|string|min:2|max:120','meta_name'=>'required|string|min:2|max:512','category'=>'required|string','language'=>'required|string|min:2|max:20','body'=>'required|string|min:2|max:4096','approval_status'=>'required|string'],['name'=>'nome interno','meta_name'=>'nome na Meta','category'=>'categoria','language'=>'idioma','body'=>'texto','approval_status'=>'aprovação']);try{if($result->fails())throw new RuntimeException(implode(' ',array_map(static fn(array$errors):string=>$errors[0],$result->errors())));$whatsappTemplates->save($id,(string)$result->value('name'),(string)$result->value('meta_name'),(string)$result->value('category'),(string)$result->value('language'),(string)$result->value('body'),(string)$result->value('approval_status'),$request->input('is_active')==='1');$session->flash('whatsapp_templates.message',$id===null?'Modelo cadastrado.':'Modelo atualizado.');return Response::redirect($basePath.'/whatsapp/templates');}catch(Throwable$e){$session->flash('whatsapp_templates.error',$e->getMessage());return Response::redirect($basePath.($id===null?'/whatsapp/templates/create':"/whatsapp/templates/{$id}/edit"));}};
    $router->post('/whatsapp/templates',static fn(Request$request):Response=>$saveTemplate($request),$manageWhatsAppTemplates);
    $router->post('/whatsapp/templates/{id:\d+}',static fn(Request$request,array$params):Response=>$saveTemplate($request,(int)$params['id']),$manageWhatsAppTemplates);
    $router->get('/whatsapp/media',static function()use($view,$whatsappMessages,$whatsappCloudApi,$whatsappMedia,$session,$csrf,$basePath,$browserTitle):Response{$cleanup=$whatsappMedia->cleanupOrphans($whatsappMessages->storedMediaPaths());return$view->render('whatsapp/media/index',['title'=>'Mídias do WhatsApp — '.$browserTitle,'items'=>$whatsappMessages->mediaDiagnostics(),'credentialsReady'=>$whatsappCloudApi->canReceiveMedia(),'orphanCandidates'=>$cleanup['candidates'],'message'=>$session->get('whatsapp_media.message'),'error'=>$session->get('whatsapp_media.error'),'csrfField'=>$csrf->field(),'basePath'=>$basePath]);},$manageWhatsAppLines);
    $router->post('/whatsapp/media/sync',static function()use($whatsappMessages,$whatsappCloudApi,$whatsappMedia,$session,$basePath):Response{$result=$whatsappMessages->syncPendingMedia($whatsappCloudApi,$whatsappMedia,20);if(!$result['available'])$session->flash('whatsapp_media.error','As credenciais da Meta ainda não permitem buscar mídias.');else$session->flash('whatsapp_media.message',"Sincronização concluída: {$result['synced']} recuperada(s) e {$result['failed']} falha(s).");return Response::redirect($basePath.'/whatsapp/media');},$manageWhatsAppLines);
    $router->post('/whatsapp/media/{id:\d+}/retry',static function(Request$request,array$params)use($whatsappMessages,$whatsappCloudApi,$whatsappMedia,$session,$basePath):Response{$result=$whatsappMessages->retryMedia((int)$params['id'],$whatsappCloudApi,$whatsappMedia);$session->flash($result['synced']>0?'whatsapp_media.message':'whatsapp_media.error',$result['synced']>0?'Mídia recuperada com sucesso.':($result['available']?'Não foi possível recuperar a mídia. Verifique o diagnóstico.':'Credenciais da Meta indisponíveis.'));return Response::redirect($basePath.'/whatsapp/media');},$manageWhatsAppLines);

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
