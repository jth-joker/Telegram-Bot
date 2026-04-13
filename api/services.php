<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$user = authenticate_user();
$db = get_db();
$stmt = $db->prepare('SELECT id, name, description, price, is_vip, status FROM services WHERE status = :status ORDER BY is_vip DESC, name ASC');
$stmt->execute([':status' => 'active']);
$services = [];

while ($row = $stmt->fetch()) {
    $row['is_vip'] = (bool) $row['is_vip'];
    $row['available'] = $row['is_vip'] === false || $user['membership_type'] === 'vip';
    $services[] = $row;
}

json_response([
    'success' => true,
    'data' => [
        'user' => get_current_user_overview($user),
        'services' => $services,
    ],
]);
