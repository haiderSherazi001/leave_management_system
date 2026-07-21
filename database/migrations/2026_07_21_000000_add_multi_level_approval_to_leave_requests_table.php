<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreignId('hr_approver_id')->nullable()->after('approver_id')->constrained('users')->nullOnDelete();
        });

        DB::table('leave_requests')->where('status', 'pending')->update(['status' => 'pending_manager']);
    }

    public function down(): void
    {
        DB::table('leave_requests')->where('status', 'pending_manager')->update(['status' => 'pending']);
        DB::table('leave_requests')->where('status', 'pending_hr')->update(['status' => 'pending']);

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hr_approver_id');
        });
    }
};
