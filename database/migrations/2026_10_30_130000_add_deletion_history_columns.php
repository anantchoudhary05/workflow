<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin soft delete with history: who deleted a record and why, for projects, clients, tasks and users.
 * Projects, clients and tasks already have deleted_at; users get it here. Projects and clients also
 * get a nullable created_by so the page can show who created them (set for new records from now on).
 */
return new class extends Migration
{
    private const TABLES = ['projects', 'clients', 'tasks', 'users'];

    private const CREATOR_TABLES = ['projects', 'clients'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (! Schema::hasColumn($name, 'deleted_at')) {
                    $table->softDeletes();
                }
                if (! Schema::hasColumn($name, 'deleted_by')) {
                    $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn($name, 'deletion_reason')) {
                    $table->text('deletion_reason')->nullable();
                }
                if (in_array($name, self::CREATOR_TABLES, true) && ! Schema::hasColumn($name, 'created_by')) {
                    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        // Users lose their soft-delete column below, so keep deleted users unable to sign in.
        if (Schema::hasColumn('users', 'deleted_at')) {
            DB::table('users')->whereNotNull('deleted_at')->update(['is_active' => false]);
        }

        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (in_array($name, self::CREATOR_TABLES, true) && Schema::hasColumn($name, 'created_by')) {
                    $table->dropConstrainedForeignId('created_by');
                }
                if (Schema::hasColumn($name, 'deleted_by')) {
                    $table->dropConstrainedForeignId('deleted_by');
                }
                if (Schema::hasColumn($name, 'deletion_reason')) {
                    $table->dropColumn('deletion_reason');
                }
                // Only users got deleted_at from this migration; the other tables had it from the start.
                if ($name === 'users' && Schema::hasColumn($name, 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};
