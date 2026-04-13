<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$payload = get_request_payload();
$serviceId = (int) ($payload['service_id'] ?? 0);
$input = trim((string) ($payload['input'] ?? ''));

if ($serviceId <= 0 || $input === '') {
    json_response(['success' => false, 'error' => 'service_id ve input zorunludur.', 'error_code' => 'INVALID_REQUEST'], 400);
}

$user = authenticate_user();
$db = get_db();
$stmt = $db->prepare('SELECT * FROM services WHERE id = :id AND status = :status');
$stmt->execute([':id' => $serviceId, ':status' => 'active']);
$service = $stmt->fetch();
if ($service === false) {
    json_response(['success' => false, 'error' => 'Servis bulunamadı veya devre dışı.', 'error_code' => 'SERVICE_NOT_FOUND'], 404);
}

$service['is_vip'] = (bool) $service['is_vip'];
if ($service['is_vip'] && $user['membership_type'] !== 'vip') {
    json_response(['success' => false, 'error' => 'Bu servis yalnızca VIP üyeler için geçerlidir.', 'error_code' => 'VIP_ONLY'], 403);
}

if ($user['membership_type'] === 'free') {
    global $FREE_QUERY_LIMIT;
    $daily = query_count_today((int) $user['id']);
    if ($daily >= $FREE_QUERY_LIMIT) {
        json_response(['success' => false, 'error' => 'Günlük sorgu limitine ulaştınız. VIP üyelik ile limitsiz kullanım sağlayabilirsiniz.', 'error_code' => 'DAILY_LIMIT'], 429);
    }
}

function build_service_url(string $apiUrl, string $input): string
{
    if (str_contains($apiUrl, '{input}')) {
        return str_replace('{input}', rawurlencode($input), $apiUrl);
    }
    if (str_contains($apiUrl, '{{input}}')) {
        return str_replace('{{input}}', rawurlencode($input), $apiUrl);
    }

    $separator = str_contains($apiUrl, '?') ? '&' : '?';
    return $apiUrl . $separator . 'input=' . rawurlencode($input);
}

function fetch_service_response(string $url): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TelegramMiniApp/1.0');

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false || $error !== '') {
        return ['success' => false, 'error' => 'Dış servis çağrısı gerçekleştirilemedi: ' . $error, 'http_code' => $httpCode];
    }

    $decoded = json_decode($response, true);
    return ['success' => true, 'payload' => $decoded !== null ? $decoded : ['raw' => $response], 'http_code' => $httpCode];
}

$serviceUrl = build_service_url($service['api_url'], $input);
$serviceResponse = fetch_service_response($serviceUrl);

$responsePayload = [
    'service_id' => $serviceId,
    'service_name' => $service['name'],
    'request_url' => $serviceUrl,
    'input' => $input,
    'external' => $serviceResponse,
];

log_request((int) $user['id'], (int) $user['telegram_id'], (int) $service['id'], ['input' => $input, 'service_url' => $serviceUrl], $responsePayload, 'query');

if (!$serviceResponse['success']) {
    json_response(['success' => false, 'error' => $serviceResponse['error'], 'error_code' => 'SERVICE_CALL_FAILED'], 502);
}

json_response([
    'success' => true,
    'data' => [
        'service' => ['id' => (int) $service['id'], 'name' => $service['name'], 'description' => $service['description'], 'price' => (float) $service['price'], 'is_vip' => (bool) $service['is_vip']],
        'result' => $serviceResponse['payload'],
        'user' => get_current_user_overview($user),
    ],
]);
