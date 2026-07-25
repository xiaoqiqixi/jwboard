<?php

namespace App\Services;

class TelegramLoginService
{
    public function verify(array $payload): array
    {
        $token = config('v2board.telegram_bot_token');
        if (!(int) config('v2board.telegram_bot_enable', 0) || empty($token)) {
            abort(404);
        }

        if (!self::isValid($payload, $token, time())) abort(403, 'Telegram login data is invalid or expired');

        if (empty($payload['id']) || !ctype_digit((string) $payload['id'])) {
            abort(403, 'Telegram user id is invalid');
        }
        return $payload;
    }

    public static function isValid(array $payload, string $token, int $now): bool
    {
        $hash = $payload['hash'] ?? '';
        $authDate = (int) ($payload['auth_date'] ?? 0);
        if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/', $hash) || !$authDate || $authDate > $now + 60 || $now - $authDate > 86400) {
            return false;
        }

        unset($payload['hash']);
        ksort($payload);
        $checkString = implode("\n", array_map(function ($key) use ($payload) {
            return $key . '=' . $payload[$key];
        }, array_keys($payload)));
        $expected = hash_hmac('sha256', $checkString, hash('sha256', $token, true));
        return hash_equals($expected, $hash);
    }
}
