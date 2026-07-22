<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->index(['approver_id', 'status']);
            $table->index(['hr_approver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex(['approver_id', 'status']);
            $table->dropIndex(['hr_approver_id', 'status']);
        });
    }
};
