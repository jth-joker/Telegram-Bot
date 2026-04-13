<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$user = authenticate_user();
json_response([
    'success' => true,
    'data' => get_current_user_overview($user),
]);
