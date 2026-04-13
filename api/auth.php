<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telegram.php';

$payload = get_request_payload();
$initData = trim((string) ($payload['initData'] ?? ''));

if ($initData === '') {
    json_response(['success' => false, 'error' => 'initData değeri bulunamadı.', 'error_code' => 'INITDATA_MISSING'], 400);
}

if (!validate_telegram_init_data($initData, $TELEGRAM_BOT_TOKEN)) {
    json_response(['success' => false, 'error' => 'Telegram initData doğrulaması başarısız.', 'error_code' => 'INITDATA_INVALID'], 403);
}

$fields = get_init_data_fields($initData);
$telegramUser = [
    'id' => (int) ($fields['id'] ?? 0),
    'username' => $fields['username'] ?? '',
];

if ($telegramUser['id'] <= 0) {
    json_response(['success' => false, 'error' => 'Telegram kullanıcı bilgisi geçersiz.', 'error_code' => 'USER_INVALID'], 400);
}

$user = save_or_update_user($telegramUser);
$token = create_auth_token((int) $user['telegram_id']);
attach_auth_cookie($token);

json_response([
    'success' => true,
    'data' => [
        'auth_token' => $token,
        'user' => get_current_user_overview($user),
    ],
]);
