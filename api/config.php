<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function env(string $key, $default = null): string
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    return $value;
}

$DB_HOST = env('DB_HOST', '127.0.0.1');
$DB_PORT = env('DB_PORT', '3306');
$DB_NAME = env('DB_NAME', 'telegram_bot');
$DB_USER = env('DB_USER', 'root');
$DB_PASS = env('DB_PASS', '');
$TELEGRAM_BOT_TOKEN = env('TELEGRAM_BOT_TOKEN', 'YOUR_TELEGRAM_BOT_TOKEN');
$WEBAPP_URL = env('WEBAPP_URL', 'https://example.com/miniapp/index.html');
$ADMIN_TELEGRAM_ID = (int) env('ADMIN_TELEGRAM_ID', '0');
$FREE_QUERY_LIMIT = (int) env('FREE_QUERY_LIMIT', '10');

function get_db(): PDO
{
    static $pdo;
    global $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $DB_HOST, $DB_PORT, $DB_NAME);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
    return $pdo;
}

function json_response($payload, int $status = 200): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function get_request_payload(): array
{
    $body = file_get_contents('php://input');
    if ($body === false || trim($body) === '') {
        return [];
    }

    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        json_response(['success' => false, 'error' => 'Geçersiz JSON payload.', 'error_code' => 'INVALID_JSON'], 400);
    }

    return $data;
}

function get_auth_token(): ?string
{
    if (!empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
        return trim($_SERVER['HTTP_X_AUTH_TOKEN']);
    }
    if (!empty($_COOKIE['auth_token'])) {
        return trim($_COOKIE['auth_token']);
    }
    return null;
}

function get_user_by_token(string $token): ?array
{
    $db = get_db();
    $stmt = $db->prepare(
        'SELECT u.*
         FROM auth_tokens t
         INNER JOIN users u ON u.telegram_id = t.telegram_id
         WHERE t.token = :token AND t.expires_at > NOW()'
    );
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();
    if ($user === false) {
        return null;
    }
    return ensure_membership_status($user);
}

function authenticate_user(): array
{
    $token = get_auth_token();
    if ($token === null) {
        json_response(['success' => false, 'error' => 'Kimlik doğrulama tokeni eksik.', 'error_code' => 'AUTH_REQUIRED'], 401);
    }

    $user = get_user_by_token($token);
    if ($user === null) {
        json_response(['success' => false, 'error' => 'Geçersiz veya süresi dolmuş token.', 'error_code' => 'AUTH_INVALID'], 401);
    }

    return $user;
}

function ensure_membership_status(array $user): array
{
    if ($user['membership_type'] === 'vip' && !empty($user['membership_expire'])) {
        $expire = strtotime($user['membership_expire']);
        if ($expire !== false && $expire < time()) {
            $db = get_db();
            $update = $db->prepare(
                'UPDATE users SET membership_type = :type, membership_expire = NULL WHERE telegram_id = :telegram_id'
            );
            $update->execute([':type' => 'free', ':telegram_id' => $user['telegram_id']]);
            $user['membership_type'] = 'free';
            $user['membership_expire'] = null;
        }
    }
    return $user;
}

function find_user_by_telegram_id(int $telegramId): ?array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM users WHERE telegram_id = :telegram_id');
    $stmt->execute([':telegram_id' => $telegramId]);
    $user = $stmt->fetch();
    return $user === false ? null : $user;
}

function save_or_update_user(array $telegramUser): array
{
    $db = get_db();
    $existing = find_user_by_telegram_id((int) $telegramUser['id']);

    if ($existing === null) {
        $stmt = $db->prepare(
            'INSERT INTO users (telegram_id, username, membership_type, membership_expire, created_at)
             VALUES (:telegram_id, :username, :membership_type, :membership_expire, NOW())'
        );
        $stmt->execute([
            ':telegram_id' => $telegramUser['id'],
            ':username' => $telegramUser['username'] ?? '',
            ':membership_type' => 'free',
            ':membership_expire' => null,
        ]);
        return find_user_by_telegram_id((int) $telegramUser['id']);
    }

    if ($existing['username'] !== ($telegramUser['username'] ?? '')) {
        $stmt = $db->prepare('UPDATE users SET username = :username WHERE telegram_id = :telegram_id');
        $stmt->execute([':username' => $telegramUser['username'] ?? '', ':telegram_id' => $telegramUser['id']]);
        $existing = find_user_by_telegram_id((int) $telegramUser['id']);
    }

    return $existing;
}

function create_auth_token(int $telegramId): string
{
    $db = get_db();
    $token = bin2hex(random_bytes(32));
    $expiry = (new DateTime('+6 hours', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

    $stmt = $db->prepare(
        'INSERT INTO auth_tokens (token, telegram_id, expires_at, created_at)
         VALUES (:token, :telegram_id, :expires_at, NOW())'
    );
    $stmt->execute([
        ':token' => $token,
        ':telegram_id' => $telegramId,
        ':expires_at' => $expiry,
    ]);

    return $token;
}

function attach_auth_cookie(string $token): void
{
    setcookie('auth_token', $token, [
        'expires' => time() + 60 * 60 * 6,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function is_admin_user(array $user): bool
{
    global $ADMIN_TELEGRAM_ID;
    return $user['telegram_id'] === $ADMIN_TELEGRAM_ID;
}

function query_count_today(int $userId): int
{
    $db = get_db();
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM logs WHERE user_id = :user_id AND type = :type AND DATE(created_at) = CURRENT_DATE'
    );
    $stmt->execute([':user_id' => $userId, ':type' => 'query']);
    return (int) $stmt->fetchColumn();
}

function log_request(int $userId, int $telegramId, int $serviceId, array $requestPayload, array $responsePayload, string $type = 'query'): void
{
    $db = get_db();
    $stmt = $db->prepare(
        'INSERT INTO logs (user_id, telegram_id, service_id, request_payload, response_payload, type, created_at)
         VALUES (:user_id, :telegram_id, :service_id, :request_payload, :response_payload, :type, NOW())'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':telegram_id' => $telegramId,
        ':service_id' => $serviceId,
        ':request_payload' => json_encode($requestPayload, JSON_UNESCAPED_UNICODE),
        ':response_payload' => json_encode($responsePayload, JSON_UNESCAPED_UNICODE),
        ':type' => $type,
    ]);
}

function get_current_user_overview(array $user): array
{
    global $FREE_QUERY_LIMIT;
    $limited = $user['membership_type'] === 'free';
    $today = query_count_today((int) $user['id']);
    return [
        'id' => (int) $user['id'],
        'telegram_id' => (int) $user['telegram_id'],
        'username' => $user['username'],
        'membership_type' => $user['membership_type'],
        'membership_expire' => $user['membership_expire'],
        'created_at' => $user['created_at'],
        'is_admin' => is_admin_user($user),
        'daily_query_count' => $today,
        'daily_query_limit' => $limited ? $FREE_QUERY_LIMIT : null,
    ];
}
