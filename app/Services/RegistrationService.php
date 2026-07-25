<?php

namespace App\Services;

use App\Models\InviteCode;
use App\Models\Plan;
use App\Models\User;

class RegistrationService
{
    public function applyInvite(User $user, ?string $code): void
    {
        if (empty($code)) {
            if ((int) config('v2board.invite_force', 0)) {
                abort(500, __('You must use the invitation code to register'));
            }
            return;
        }

        $inviteCode = InviteCode::where('code', $code)->where('status', 0)->first();
        if (!$inviteCode) {
            if ((int) config('v2board.invite_force', 0)) {
                abort(500, __('Invalid invitation code'));
            }
            return;
        }

        $user->invite_user_id = $inviteCode->user_id ?: null;
        if (!(int) config('v2board.invite_never_expire', 0)) {
            $inviteCode->status = 1;
            $inviteCode->save();
        }
    }

    public function applyTryOut(User $user): void
    {
        if (!(int) config('v2board.try_out_plan_id', 0)) return;

        $plan = Plan::find(config('v2board.try_out_plan_id'));
        if (!$plan) return;

        $user->transfer_enable = $plan->transfer_enable * 1073741824;
        $user->plan_id = $plan->id;
        $user->group_id = $plan->group_id;
        $user->expired_at = time() + (config('v2board.try_out_hour', 1) * 3600);
        $user->speed_limit = $plan->speed_limit;
    }
}
