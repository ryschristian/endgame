<?php

namespace App\Support;

use App\Rules\PasswordByteLimit;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AccountRules
{
    public static function normalizeEmail(mixed $email): mixed
    {
        return is_string($email) ? Str::lower(trim($email)) : $email;
    }

    public static function password(): array
    {
        return ['required', 'string', 'confirmed', Password::min(12)->letters()->numbers(), new PasswordByteLimit];
    }
}
