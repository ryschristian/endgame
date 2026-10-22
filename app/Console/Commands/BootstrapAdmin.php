<?php

namespace App\Console\Commands;

use App\Services\AccountService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class BootstrapAdmin extends Command
{
    protected $signature = 'app:bootstrap-admin';

    protected $description = 'Create the first System Admin once, using hidden password prompts';

    public function handle(AccountService $accounts): int
    {
        $name = $this->ask('Name');
        $email = $this->ask('Email');
        $password = $this->secret('Password');
        $confirmation = $this->secret('Confirm password');
        if (! is_string($password) || $password !== $confirmation) {
            $this->error('The password confirmation does not match.');
            return self::FAILURE;
        }
        try {
            $accounts->bootstrap((string) $name, (string) $email, $password);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $errors) {
                $this->error($errors[0]);
            }
            return self::FAILURE;
        }
        $this->info('System Admin created. You can now sign in.');
        return self::SUCCESS;
    }
}
