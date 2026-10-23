<?php

namespace App\Support;

use RuntimeException;

class AccountMail
{
    public static function ensureConfigured(): void
    {
        $transport = config('mail.mailers.'.config('mail.default').'.transport');
        if ($transport === 'smtp' || ($transport === 'array' && app()->environment('testing'))) {
            return;
        }
        // Authentication links must never fall through to a log mail transport.
        throw new RuntimeException('Configure SMTP to deliver account notifications.');
    }
}
