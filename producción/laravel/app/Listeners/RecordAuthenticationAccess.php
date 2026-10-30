<?php

namespace App\Listeners;

use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;

class RecordAuthenticationAccess
{
    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->save();

        AccessLog::create([
            'user_id' => $event->user->id,
            'attempted_email' => $event->user->email,
            'result' => 'success',
            'ip_address' => request()->ip(),
        ]);
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;
        $user = $event->user instanceof User ? $event->user : null;

        AccessLog::create([
            'user_id' => $user?->id,
            'attempted_email' => is_string($email) ? mb_substr($email, 0, 255) : null,
            'result' => 'failed',
            'ip_address' => request()->ip(),
        ]);
    }

    public function handleLockout(Lockout $event): void
    {
        $email = $event->request->input('email');

        AccessLog::create([
            'attempted_email' => is_string($email) ? mb_substr($email, 0, 255) : null,
            'result' => 'locked',
            'ip_address' => $event->request->ip(),
        ]);
    }
}
