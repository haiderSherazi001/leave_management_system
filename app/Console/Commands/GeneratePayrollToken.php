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
    protected $signature = 'payroll:generate-token {email? : Email of the HR/Admin user to generate the token for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a Sanctum API token for the external payroll/accounting integration';

    public function handle(): int
    {
        $email = $this->argument('email');

        if ($email === null) {
            // With multiple companies now possible, "the first active HR
            // user" (the old default) is meaningless — there's no longer a
            // single HR to default to, and guessing one would silently
            // issue a token for an arbitrary company. List every company's
            // active HR so the operator can rerun with a specific email
            // instead. This runs with no session, so User's tenant scope
            // is a no-op here on purpose — a global listing is exactly
            // what's needed to show every candidate across every company.
            $hrUsers = User::where('role', UserRole::Hr)->where('is_active', true)->get(['name', 'email', 'company_id']);

            if ($hrUsers->isEmpty()) {
                $this->error('No active HR user found in any company.');

                return self::FAILURE;
            }

            $this->error('An email is required now that more than one company can exist. Active HR accounts:');

            foreach ($hrUsers as $hr) {
                $this->line("  {$hr->email} — {$hr->name}");
            }

            $this->line('Run again as: php artisan payroll:generate-token {email}');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->where('is_active', true)->first();

        if ($user === null) {
            $this->error("No active user found with the email [{$email}].");

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
