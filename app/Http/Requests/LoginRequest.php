<?php

namespace App\Http\Requests;

use App\Rules\PasswordByteLimit;
use App\Support\AccountRules;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => AccountRules::normalizeEmail($this->input('email'))]);
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:254'], 'password' => ['required', 'string', new PasswordByteLimit], 'remember' => ['sometimes', 'boolean']];
    }
}
