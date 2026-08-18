<?php

require_once(__DIR__.'/../../config.php');

$slug=required_param('franquia',PARAM_ALPHANUMEXT);
$brand=\local_mundointer\local\brand_resolver::bySlug((array)(\local_mundointer\local\brand_resolver::catalog()['brands']??[]),$slug);
if($brand===null)throw new moodle_exception('invalidparameter');
\local_mundointer\local\brand_resolver::remember($slug);
$SESSION->wantsurl = (new moodle_url('/local/mundointer/portal.php'))->out(false);
redirect(new moodle_url('/login/index.php'));
