<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$user = authenticate_user();
if (!is_admin_user($user)) {
    json_response(['success' => false, 'error' => 'Bu işlem için yönetici yetkisi gereklidir.', 'error_code' => 'ADMIN_REQUIRED'], 403);
}

$payload = get_request_payload();
action:
$action = trim((string) ($payload['action'] ?? ''));

if ($action === '') {
    json_response(['success' => false, 'error' => 'action parametresi gerekli.', 'error_code' => 'ACTION_REQUIRED'], 400);
}

$db = get_db();

switch ($action) {
    case 'list_users':
        $stmt = $db->query('SELECT id, telegram_id, username, membership_type, membership_expire, created_at FROM users ORDER BY created_at DESC');
        $users = $stmt->fetchAll();
        json_response(['success' => true, 'data' => ['users' => $users]]);
        break;

    case 'update_user':
        $userId = (int) ($payload['user_id'] ?? 0);
        $membershipType = trim((string) ($payload['membership_type'] ?? ''));
        if ($userId <= 0 || !in_array($membershipType, ['free', 'vip'], true)) {
            json_response(['success' => false, 'error' => 'Geçersiz kullanıcı veya üyelik tipi.', 'error_code' => 'INVALID_INPUT'], 400);
        }
        $stmt = $db->prepare('UPDATE users SET membership_type = :type WHERE id = :id');
        $stmt->execute([':type' => $membershipType, ':id' => $userId]);
        json_response(['success' => true, 'message' => 'Kullanıcı üyelik tipi güncellendi.']);
        break;

    case 'extend_vip':
        $userId = (int) ($payload['user_id'] ?? 0);
        $days = max(1, (int) ($payload['days'] ?? 0));
        if ($userId <= 0) {
            json_response(['success' => false, 'error' => 'Geçersiz kullanıcı ID.', 'error_code' => 'INVALID_INPUT'], 400);
        }
        $userRow = $db->prepare('SELECT membership_expire FROM users WHERE id = :id');
        $userRow->execute([':id' => $userId]);
        $userInfo = $userRow->fetch();
        if ($userInfo === false) {
            json_response(['success' => false, 'error' => 'Kullanıcı bulunamadı.', 'error_code' => 'USER_NOT_FOUND'], 404);
        }
        $current = $userInfo['membership_expire'] ? new DateTime($userInfo['membership_expire'], new DateTimeZone('UTC')) : new DateTime('now', new DateTimeZone('UTC'));
        if ($current < new DateTime('now', new DateTimeZone('UTC'))) {
            $current = new DateTime('now', new DateTimeZone('UTC'));
        }
        $current->modify('+' . $days . ' days');
        $stmt = $db->prepare('UPDATE users SET membership_type = :type, membership_expire = :expire WHERE id = :id');
        $stmt->execute([':type' => 'vip', ':expire' => $current->format('Y-m-d H:i:s'), ':id' => $userId]);
        json_response(['success' => true, 'message' => 'VIP süresi başarıyla uzatıldı.', 'data' => ['membership_expire' => $current->format('Y-m-d H:i:s')]]);
        break;

    case 'services_list':
        $stmt = $db->query('SELECT id, name, description, api_url, price, is_vip, status FROM services ORDER BY is_vip DESC, name ASC');
        $services = $stmt->fetchAll();
        json_response(['success' => true, 'data' => ['services' => $services]]);
        break;

    case 'save_service':
        $serviceId = (int) ($payload['id'] ?? 0);
        $name = trim((string) ($payload['name'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $apiUrl = trim((string) ($payload['api_url'] ?? ''));
        $price = number_format((float) ($payload['price'] ?? 0), 2, '.', '');
        $isVip = !empty($payload['is_vip']) ? 1 : 0;
        $status = in_array($payload['status'] ?? 'active', ['active', 'inactive'], true) ? $payload['status'] : 'active';

        if ($name === '' || $apiUrl === '') {
            json_response(['success' => false, 'error' => 'Servis adı ve API URL gereklidir.', 'error_code' => 'INVALID_INPUT'], 400);
        }

        if ($serviceId > 0) {
            $stmt = $db->prepare(
                'UPDATE services SET name = :name, description = :description, api_url = :api_url, price = :price, is_vip = :is_vip, status = :status WHERE id = :id'
            );
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':api_url' => $apiUrl,
                ':price' => $price,
                ':is_vip' => $isVip,
                ':status' => $status,
                ':id' => $serviceId,
            ]);
            json_response(['success' => true, 'message' => 'Servis güncellendi.']);
        }

        $stmt = $db->prepare(
            'INSERT INTO services (name, description, api_url, price, is_vip, status, created_at)
             VALUES (:name, :description, :api_url, :price, :is_vip, :status, NOW())'
        );
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':api_url' => $apiUrl,
            ':price' => $price,
            ':is_vip' => $isVip,
            ':status' => $status,
        ]);
        json_response(['success' => true, 'message' => 'Yeni servis eklendi.']);
        break;

    case 'delete_service':
        $serviceId = (int) ($payload['id'] ?? 0);
        if ($serviceId <= 0) {
            json_response(['success' => false, 'error' => 'Geçersiz servis ID.', 'error_code' => 'INVALID_INPUT'], 400);
        }
        $stmt = $db->prepare('DELETE FROM services WHERE id = :id');
        $stmt->execute([':id' => $serviceId]);
        json_response(['success' => true, 'message' => 'Servis silindi.']);
        break;

    case 'logs':
        $limit = min(200, max(1, (int) ($payload['limit'] ?? 50)));
        $stmt = $db->prepare('SELECT id, user_id, telegram_id, service_id, request_payload, response_payload, type, created_at FROM logs ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll();
        json_response(['success' => true, 'data' => ['logs' => $logs]]);
        break;

    default:
        json_response(['success' => false, 'error' => 'Bilinmeyen action.', 'error_code' => 'UNKNOWN_ACTION'], 400);
}
