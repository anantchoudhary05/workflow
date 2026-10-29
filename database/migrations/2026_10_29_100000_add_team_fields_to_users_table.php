<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('developer')->after('email')->index();
            $table->string('job_title', 120)->nullable()->after('role');
            $table->string('skills', 500)->nullable()->after('job_title');
            $table->decimal('weekly_capacity_hours', 5, 2)->nullable()->after('skills');
            $table->boolean('is_active')->default(true)->after('weekly_capacity_hours')->index();
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['role', 'job_title', 'skills', 'weekly_capacity_hours', 'is_active', 'last_login_at']);
        });
    }
};
