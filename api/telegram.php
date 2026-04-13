<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function parse_init_data(string $initData): array
{
    $data = [];
    parse_str($initData, $data);
    return $data;
}

function validate_telegram_init_data(string $initData, string $botToken): bool
{
    $data = parse_init_data($initData);
    if (empty($data['hash'])) {
        return false;
    }

    $hash = $data['hash'];
    unset($data['hash']);
    ksort($data, SORT_STRING);

    $checkString = '';
    foreach ($data as $key => $value) {
        $checkString .= $key . '=' . $value . "\n";
    }
    $checkString = rtrim($checkString, "\n");

    $secretKey = hash('sha256', $botToken, true);
    $calculatedHash = hash_hmac('sha256', $checkString, $secretKey);

    if (!hash_equals($calculatedHash, $hash)) {
        return false;
    }

    if (isset($data['auth_date']) && (time() - (int) $data['auth_date'] > 86400)) {
        return false;
    }

    return true;
}

function get_init_data_fields(string $initData): array
{
    return parse_init_data($initData);
}
