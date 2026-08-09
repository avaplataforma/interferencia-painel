<?php

declare(strict_types=1);

require_once(__DIR__.'/config.php');

$slug=optional_param('slug','',PARAM_ALPHANUMEXT);
if($slug==='')$slug=optional_param('franquia','',PARAM_ALPHANUMEXT);

// Compatibilidade temporária com o formato antigo: franquia.php?=interferencia.
if($slug===''&&preg_match('/^=([a-zA-Z0-9_-]+)(?:&|$)/',(string)($_SERVER['QUERY_STRING']??''),$match)===1){
    $slug=clean_param((string)$match[1],PARAM_ALPHANUMEXT);
}

if($slug==='')throw new moodle_exception('invalidparameter');

redirect(new moodle_url('/local/mundointer/entrar.php',['franquia'=>$slug]));
