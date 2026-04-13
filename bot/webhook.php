<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/config.php';

$body = file_get_contents('php://input');
$update = json_decode($body, true);
if (!is_array($update)) {
    exit;
}

$chatId = $update['message']['chat']['id'] ?? null;
$text = trim((string) ($update['message']['text'] ?? ''));
$from = $update['message']['from'] ?? [];
if ($chatId === null || $from === []) {
    exit;
}

if (str_starts_with($text, '/start')) {
    save_or_update_user(['id' => (int) ($from['id'] ?? 0), 'username' => $from['username'] ?? '']);
    $reply = "Hoş geldiniz! Mini App'e erişmek için aşağıdaki düğmeye tıklayın.";
    send_telegram_message((int) $chatId, $reply);
}

function send_telegram_message(int $chatId, string $text): void
{
    global $TELEGRAM_BOT_TOKEN, $WEBAPP_URL;
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode([
            'inline_keyboard' => [[
                [
                    'text' => 'Mini App Aç',
                    'web_app' => ['url' => $WEBAPP_URL],
                ],
            ]],
        ]),
    ];

    $ch = curl_init('https://api.telegram.org/bot' . $TELEGRAM_BOT_TOKEN . '/sendMessage');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_exec($ch);
    curl_close($ch);
}
