<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_local_mundointer_upgrade(int $oldversion): bool
{
    if($oldversion<2026080903){require_once(__DIR__.'/field_helpers.php');local_mundointer_ensure_identity_fields();upgrade_plugin_savepoint(true,2026080903,'local','mundointer');}
    return true;
}
