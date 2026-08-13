<?php

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_mundointer_ping' => [
        'classname' => 'local_mundointer\\external\\ping',
        'description' => 'Retorna o estado e a versão do conector Mundo Inter.',
        'type' => 'read',
        'ajax' => false,
        'capabilities' => 'local/mundointer:manage',
    ],
    'local_mundointer_sync_brands' => [
        'classname' => 'local_mundointer\\external\\sync_brands',
        'description' => 'Recebe do ADM Central as identidades visuais e os polos autorizados.',
        'type' => 'write',
        'ajax' => false,
        'capabilities' => 'local/mundointer:manage',
    ],
    'local_mundointer_diagnose_poles' => [
        'classname' => 'local_mundointer\\external\\diagnose_poles',
        'description' => 'Retorna somente contagens agregadas do campo Polo Presencial para conferência no ADM Central.',
        'type' => 'read',
        'ajax' => false,
        'capabilities' => 'local/mundointer:manage',
    ],
    'local_mundointer_organize_enrollment' => [
        'classname' => 'local_mundointer\\external\\organize_enrollment',
        'description' => 'Cria ou reutiliza a coorte da franquia e o grupo do polo no curso, incluindo o aluno sem duplicidades.',
        'type' => 'write',
        'ajax' => false,
        'capabilities' => 'local/mundointer:manage',
    ],
    'local_mundointer_sync_trail_sections' => [
        'classname' => 'local_mundointer\\external\\sync_trail_sections',
        'description' => 'Organiza cada Curso individual da Trilha em uma seção separada do curso no Moodle.',
        'type' => 'write',
        'ajax' => false,
        'capabilities' => 'local/mundointer:manage',
    ],
    'local_mundointer_academic_snapshot' => [
        'classname' => 'local_mundointer\\external\\academic_snapshot',
        'description' => 'Retorna progresso, nota, último acesso e certificado em um formato acadêmico comum.',
        'type' => 'read',
        'ajax' => false,
        'capabilities' => 'local/mundointer:manage',
    ],
];

$services = [
    'Mundo Inter Connector' => [
        'functions' => [
            'local_mundointer_ping',
            'local_mundointer_sync_brands',
            'local_mundointer_diagnose_poles',
            'local_mundointer_organize_enrollment',
            'local_mundointer_sync_trail_sections',
            'local_mundointer_academic_snapshot',
            'core_webservice_get_site_info',
            'core_course_get_courses',
            'core_course_get_categories',
            'core_course_create_categories',
            'core_course_create_courses',
            'core_course_update_courses',
            'core_enrol_get_enrolled_users',
            'core_user_get_users_by_field',
            'core_user_create_users',
            'core_user_update_users',
            'enrol_manual_enrol_users',
            'core_completion_get_course_completion_status',
        ],
        'restrictedusers' => 1,
        'enabled' => 1,
        'downloadfiles' => 0,
        'uploadfiles' => 0,
    ],
];
