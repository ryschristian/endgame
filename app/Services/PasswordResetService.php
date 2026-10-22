<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\AccountMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class PasswordResetService
{
    public function __construct(private AccountService $accounts) {}

    public function sendLink(string $email): void
    {
        $recipient = null;
        $notificationToken = null;
        DB::transaction(function () use ($email, &$recipient, &$notificationToken) {
            $this->accounts->lockAdministration();
            Password::sendResetLink(['email' => $email, 'is_active' => true], function (User $user, #[\SensitiveParameter] string $token) use (&$recipient, &$notificationToken) {
                $recipient = $user;
                $notificationToken = $token;
            });
        }, 3);

        if ($recipient && $notificationToken) {
            try {
                AccountMail::ensureConfigured();
                $recipient->sendPasswordResetNotification($notificationToken);
            } catch (Throwable) {
                // Keep transport details, credentials, and authentication links out of logs.
                Log::warning('A password reset notification could not be delivered. Check SMTP configuration.');
            }
        }
    }

    public function reset(#[\SensitiveParameter] array $values): void
    {
        DB::transaction(function () use ($values) {
            $this->accounts->lockAdministration();
            $status = Password::reset([...$values, 'is_active' => true], function (User $user, #[\SensitiveParameter] string $password) {
                $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $this->accounts->revokeSessions($user);
                $user->password = $password;
                $user->save();
                AuditEvent::create(['actor_id' => $user->id, 'target_user_id' => $user->id, 'action' => 'account.password_reset']);
            });
            if ($status !== Password::PASSWORD_RESET) {
                throw ValidationException::withMessages(['email' => 'This password reset link is invalid or expired. Request a new link.']);
            }
        }, 3);
    }
}
