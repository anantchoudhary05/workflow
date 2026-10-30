<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration for the department role model (Manager, Team Lead, Marketing added).
 *
 * Existing values keep their meaning: admin → Administrator, developer → Developer,
 * reviewer → QA / Reviewer, management → Management (read-only). Nobody is promoted
 * automatically; an administrator assigns Manager / Team Lead / Marketing on the Users page.
 * Empty or unknown values fall back to the least-privileged working role (developer).
 */
return new class extends Migration
{
    private const KNOWN = ['admin', 'manager', 'team_lead', 'developer', 'reviewer', 'marketing', 'management'];

    public function up(): void
    {
        DB::table('users')
            ->where(fn ($q) => $q->whereNull('role')->orWhereNotIn('role', self::KNOWN))
            ->update(['role' => 'developer']);
    }

    /** Rolling back maps the new roles to the closest earlier role without granting more access. */
    public function down(): void
    {
        DB::table('users')->whereIn('role', ['manager', 'team_lead'])->update(['role' => 'reviewer']);
        DB::table('users')->where('role', 'marketing')->update(['role' => 'developer']);
    }
};
