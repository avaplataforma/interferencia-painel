<?php

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'robot_sessions' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 180,
    ],
    'sso_sessions' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 180,
    ],
];
