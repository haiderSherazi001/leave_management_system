<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills the whole existing single-tenant install into one "Company #1"
 * so multi-tenancy can be introduced without losing any existing data.
 * company_id is added nullable first, backfilled, then locked to NOT NULL +
 * FK in the same migration, alongside the unique constraints that must
 * become per-company now that more than one company can exist.
 */
return new class extends Migration
{
    private const TENANT_TABLES = [
        'users',
        'departments',
        'leave_types',
        'leave_balances',
        'leave_requests',
        'attendances',
        'holidays',
        'work_schedule',
        'office_location',
    ];

    public function up(): void
    {
        foreach (self::TENANT_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('id');
            });
        }

        $companyId = DB::table('companies')->insertGetId([
            'name' => 'My Company',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (self::TENANT_TABLES as $tableName) {
            DB::table($tableName)->update(['company_id' => $companyId]);
        }

        foreach (self::TENANT_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable(false)->change();
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            });
        }

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropUnique(['name']);
            $table->unique(['company_id', 'name']);
        });

        Schema::table('leave_types', function (Blueprint $table): void {
            $table->dropUnique(['name']);
            $table->dropUnique(['code']);
            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'code']);
        });

        Schema::table('holidays', function (Blueprint $table): void {
            $table->dropUnique(['date']);
            $table->unique(['company_id', 'date']);
        });

        Schema::table('work_schedule', function (Blueprint $table): void {
            $table->unique('company_id');
        });

        Schema::table('office_location', function (Blueprint $table): void {
            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'name']);
            $table->unique('name');
        });

        Schema::table('leave_types', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'name']);
            $table->dropUnique(['company_id', 'code']);
            $table->unique('name');
            $table->unique('code');
        });

        Schema::table('holidays', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'date']);
            $table->unique('date');
        });

        Schema::table('work_schedule', function (Blueprint $table): void {
            $table->dropUnique(['company_id']);
        });

        Schema::table('office_location', function (Blueprint $table): void {
            $table->dropUnique(['company_id']);
        });

        foreach (self::TENANT_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }
    }
};
