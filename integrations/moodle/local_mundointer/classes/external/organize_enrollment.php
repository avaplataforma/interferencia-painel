<?php

namespace local_mundointer\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

final class organize_enrollment extends external_api
{
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'userid'=>new external_value(PARAM_INT,'Aluno já matriculado no curso.'),
            'courseid'=>new external_value(PARAM_INT,'Curso que receberá o grupo.'),
            'organizationcode'=>new external_value(PARAM_ALPHANUMEXT,'Código permanente da franquia.'),
            'organizationname'=>new external_value(PARAM_TEXT,'Nome da franquia.'),
            'polecode'=>new external_value(PARAM_ALPHANUMEXT,'Código permanente do polo.'),
            'polename'=>new external_value(PARAM_TEXT,'Nome do polo.'),
            'periodcode'=>new external_value(PARAM_ALPHANUMEXT,'Período acadêmico.'),
            'cohortcode'=>new external_value(PARAM_ALPHANUMEXT,'Código idempotente da coorte.'),
            'cohortname'=>new external_value(PARAM_TEXT,'Nome da coorte.'),
            'groupcode'=>new external_value(PARAM_ALPHANUMEXT,'Código idempotente do grupo.'),
            'groupname'=>new external_value(PARAM_TEXT,'Nome do grupo.'),
        ]);
    }

    public static function execute(int $userid,int $courseid,string $organizationcode,string $organizationname,string $polecode,string $polename,string $periodcode,string $cohortcode,string $cohortname,string $groupcode,string $groupname): array
    {
        global $CFG,$DB;
        $parameters=self::validate_parameters(self::execute_parameters(),compact('userid','courseid','organizationcode','organizationname','polecode','polename','periodcode','cohortcode','cohortname','groupcode','groupname'));
        $context=\context_system::instance();
        self::validate_context($context);
        require_capability('local/mundointer:manage',$context);
        $DB->get_record('user',['id'=>$parameters['userid'],'deleted'=>0],'id',MUST_EXIST);
        $DB->get_record('course',['id'=>$parameters['courseid']],'id',MUST_EXIST);

        require_once($CFG->dirroot.'/cohort/lib.php');
        $cohort=$DB->get_record('cohort',['idnumber'=>$parameters['cohortcode']]);
        $cohortcreated=false;
        if(!$cohort){
            $record=(object)[
                'contextid'=>$context->id,
                'name'=>$parameters['cohortname'],
                'idnumber'=>$parameters['cohortcode'],
                'description'=>'Organização acadêmica gerenciada pelo Mundo Inter.',
                'descriptionformat'=>FORMAT_PLAIN,
                'visible'=>1,
                'component'=>'local_mundointer',
            ];
            $record->id=cohort_add_cohort($record);
            $cohort=$record;
            $cohortcreated=true;
        }
        if(!cohort_is_member((int)$cohort->id,$parameters['userid']))cohort_add_member((int)$cohort->id,$parameters['userid']);

        require_once($CFG->dirroot.'/group/lib.php');
        $group=$DB->get_record('groups',['courseid'=>$parameters['courseid'],'idnumber'=>$parameters['groupcode']]);
        $groupcreated=false;
        if(!$group){
            $record=(object)[
                'courseid'=>$parameters['courseid'],
                'name'=>$parameters['groupname'],
                'idnumber'=>$parameters['groupcode'],
                'description'=>'Franquia '.$parameters['organizationname'].' · Polo '.$parameters['polename'].' · '.$parameters['periodcode'],
                'descriptionformat'=>FORMAT_PLAIN,
            ];
            $record->id=groups_create_group($record);
            $group=$record;
            $groupcreated=true;
        }
        if(!groups_is_member((int)$group->id,$parameters['userid']))groups_add_member((int)$group->id,$parameters['userid']);

        return[
            'status'=>'ok',
            'cohortid'=>(int)$cohort->id,
            'cohortname'=>(string)$cohort->name,
            'cohortcreated'=>$cohortcreated,
            'groupid'=>(int)$group->id,
            'groupname'=>(string)$group->name,
            'groupcreated'=>$groupcreated,
            'periodcode'=>$parameters['periodcode'],
        ];
    }

    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure([
            'status'=>new external_value(PARAM_ALPHA,'Estado da operação.'),
            'cohortid'=>new external_value(PARAM_INT,'ID da coorte reutilizada ou criada.'),
            'cohortname'=>new external_value(PARAM_TEXT,'Nome da coorte.'),
            'cohortcreated'=>new external_value(PARAM_BOOL,'Indica se a coorte foi criada agora.'),
            'groupid'=>new external_value(PARAM_INT,'ID do grupo reutilizado ou criado.'),
            'groupname'=>new external_value(PARAM_TEXT,'Nome do grupo.'),
            'groupcreated'=>new external_value(PARAM_BOOL,'Indica se o grupo foi criado agora.'),
            'periodcode'=>new external_value(PARAM_ALPHANUMEXT,'Período acadêmico aplicado.'),
        ]);
    }
}
