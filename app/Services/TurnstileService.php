<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TurnstileService
{
    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (!(bool) config('services.turnstile.enabled')) {
            return true;
        }

        if (empty($token) || empty(config('services.turnstile.secret_key'))) {
            return false;
        }

        try {
            return (bool) Http::asForm()
                ->acceptJson()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array_filter([
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]))
                ->json('success');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
