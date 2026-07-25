<?php

namespace App\Http\Controllers\Passport;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthService;
use App\Services\RegistrationService;
use App\Services\TelegramLoginService;
use App\Services\TelegramService;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TelegramController extends Controller
{
    public function config()
    {
        if (!(int) config('v2board.telegram_bot_enable', 0)) abort(404);
        $token = config('v2board.telegram_bot_token');
        if (empty($token)) abort(404);
        $username = Cache::remember('telegram_login_bot_username_' . sha1($token), 3600, function () {
            return (new TelegramService())->getMe()->result->username ?? null;
        });
        if (!$username) abort(500, 'Telegram bot username is unavailable');
        return response(['data' => ['username' => $username]]);
    }

    public function auth(Request $request)
    {
        $fields = $request->only(['id', 'first_name', 'last_name', 'username', 'photo_url', 'auth_date', 'hash']);
        $telegram = (new TelegramLoginService())->verify($fields);
        $telegramId = (int) $telegram['id'];
        $user = User::where('telegram_id', $telegramId)->first();
        if ($user) {
            if ($user->banned) abort(403, __('Your account has been suspended'));
            return response(['data' => (new AuthService($user))->generateAuthData($request)]);
        }

        if ((int) config('v2board.stop_register', 0)) {
            abort(500, __('Registration has closed'));
        }
        $this->assertIpRegistrationLimit($request);

        $user = DB::transaction(function () use ($request, $telegramId) {
            $existing = User::where('telegram_id', $telegramId)->lockForUpdate()->first();
            if ($existing) return $existing;

            $user = new User();
            $user->telegram_id = $telegramId;
            // Required by the existing schema; Telegram-only accounts never use this address to authenticate.
            $user->email = 'telegram-' . $telegramId . '@telegram.invalid';
            $user->password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            $user->uuid = Helper::guid(true);
            $user->token = Helper::guid();
            (new RegistrationService())->applyInvite($user, $request->input('invite_code'));
            (new RegistrationService())->applyTryOut($user);
            $user->last_login_at = time();
            $user->save();
            return $user;
        });

        $this->incrementIpRegistrationLimit($request);
        return response(['data' => (new AuthService($user))->generateAuthData($request)]);
    }

    private function assertIpRegistrationLimit(Request $request): void
    {
        if (!(int) config('v2board.register_limit_by_ip_enable', 0)) return;
        $count = (int) Cache::get(CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip()), 0);
        if ($count >= (int) config('v2board.register_limit_count', 3)) {
            abort(500, __('Register frequently, please try again after :minute minute', [
                'minute' => config('v2board.register_limit_expire', 60)
            ]));
        }
    }

    private function incrementIpRegistrationLimit(Request $request): void
    {
        if (!(int) config('v2board.register_limit_by_ip_enable', 0)) return;
        $key = CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip());
        Cache::put($key, (int) Cache::get($key, 0) + 1, (int) config('v2board.register_limit_expire', 60) * 60);
    }
}
