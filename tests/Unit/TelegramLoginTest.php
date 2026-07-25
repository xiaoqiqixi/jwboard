<?php

namespace Tests\Unit;

use App\Services\TelegramLoginService;
use PHPUnit\Framework\TestCase;

class TelegramLoginTest extends TestCase
{
    public function testValidatesSignedAndFreshTelegramPayload()
    {
        $now = 1700000000;
        $payload = [
            'id' => '123456789',
            'first_name' => 'Test',
            'username' => 'telegram_user',
            'auth_date' => (string) $now
        ];
        ksort($payload);
        $checkString = implode("\n", array_map(function ($key) use ($payload) {
            return $key . '=' . $payload[$key];
        }, array_keys($payload)));
        $payload['hash'] = hash_hmac('sha256', $checkString, hash('sha256', 'bot-token', true));

        $this->assertTrue(TelegramLoginService::isValid($payload, 'bot-token', $now));
        $this->assertFalse(TelegramLoginService::isValid($payload, 'wrong-token', $now));
        $this->assertFalse(TelegramLoginService::isValid($payload, 'bot-token', $now + 86401));
    }
}
