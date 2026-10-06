<?php

defined('BASEPATH') or exit('No direct script access allowed');
return [
    'code' => 'MARKITO',
    'name' => 'Markito',
    'role' => 'master',
    'master_code' => 'MARKITO',
    'master_url' => 'http://localhost/markito-local',
    'child_urls' => [
        'SEEN' => 'http://localhost/seen-local',
    ],
    'api_key' => 'synchub-local-v2',
    'api_secret' => '9f22be4031ba4f3895c493f448e49a55d30cc58da13afe23496b4e056d9b9da8',
];
