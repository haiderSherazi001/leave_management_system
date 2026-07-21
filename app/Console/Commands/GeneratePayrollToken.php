<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class GeneratePayrollToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:generate-token {email? : Email of the HR/Admin user to generate the token for; defaults to the first active HR user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a Sanctum API token for the external payroll/accounting integration';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = $email !== null
            ? User::where('email', $email)->where('is_active', true)->first()
            : User::where('role', UserRole::Hr)->where('is_active', true)->first();

        if ($user === null) {
            $this->error($email !== null
                ? "No active user found with the email [{$email}]."
                : 'No active HR user found. Pass an email explicitly: php artisan payroll:generate-token {email}');

            return self::FAILURE;
        }

        if (! $user->isHr()) {
            $this->error("{$user->email} is not an HR/Admin user. Only HR/Admin users may hold a payroll integration token.");

            return self::FAILURE;
        }

        $token = $user->createToken('payroll-integration-token');

        $this->info("Payroll integration token generated for {$user->name} ({$user->email}).");
        $this->line('Copy this token now — it will not be shown again:');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
