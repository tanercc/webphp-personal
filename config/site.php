<?php
defined('BASEPATH') or exit('No direct script access allowed');
return [
    'name' => 'Personal',
    'profile' => 'demo',
    'allowed_profiles' => ['demo', 'starter'],
    'database_file' => dirname(__DIR__) . '/storage/personal.sqlite',
    'session_name' => 'PERSONALCMS',
];
