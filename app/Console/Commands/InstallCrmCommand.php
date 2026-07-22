<?php

namespace App\Console\Commands;

use App\Actions\InstallInitialSuperAdmin;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator as LaravelValidator;
use LogicException;
use Throwable;

#[Signature('crm369:install
    {--name= : Display name of the initial super administrator}
    {--email= : Email address configured as SUPER_ADMIN_EMAIL}')]
#[Description('Create the only initial CRM369 super administrator on an empty database')]
class InstallCrmCommand extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private readonly InstallInitialSuperAdmin $installInitialSuperAdmin,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = trim((string) $this->option('name'));
        $email = Str::lower(trim((string) $this->option('email')));
        $configuredEmail = Str::lower(trim((string) config('admin.super_admin_email')));

        $accountValidator = Validator::make([
            'name' => $name,
            'email' => $email,
        ], [
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
        ]);

        if ($accountValidator->fails()) {
            $this->renderValidationErrors($accountValidator);

            return self::FAILURE;
        }

        if ($configuredEmail === '' || $configuredEmail !== $email) {
            $this->error('The email must match the configured SUPER_ADMIN_EMAIL value.');

            return self::FAILURE;
        }

        if (User::query()->exists()) {
            $this->error('CRM369 already has a user account; the initial installer will not modify it.');

            return self::FAILURE;
        }

        $password = $this->promptForPassword();

        if ($password === null) {
            return self::FAILURE;
        }

        try {
            $user = $this->installInitialSuperAdmin->execute($name, $email, $password);
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('The super administrator could not be created. Check the application log.');

            return self::FAILURE;
        }

        $this->info("Initial super administrator [{$user->email}] created successfully.");

        return self::SUCCESS;
    }

    private function promptForPassword(): ?string
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $password = $this->secret('Super-admin password');
            $passwordConfirmation = $this->secret('Confirm super-admin password');
            $passwordValidator = Validator::make([
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ], [
                'password' => $this->passwordRules(),
            ]);

            if ($passwordValidator->passes()) {
                return (string) $password;
            }

            $this->renderValidationErrors($passwordValidator);
        }

        $this->error('The password was not accepted after three attempts.');

        return null;
    }

    private function renderValidationErrors(LaravelValidator $validator): void
    {
        foreach ($validator->errors()->all() as $message) {
            $this->error($message);
        }
    }
}
