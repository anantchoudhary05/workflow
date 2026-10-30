<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marketing → dev team handoff, and the Team Lead's acknowledgement. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('handed_off_at')->nullable();
            $table->foreignId('handed_off_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('handoff_notes')->nullable();
            $table->timestamp('handoff_acknowledged_at')->nullable();
            $table->foreignId('handoff_acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handed_off_by');
            $table->dropConstrainedForeignId('handoff_acknowledged_by');
            $table->dropColumn(['handed_off_at', 'handoff_notes', 'handoff_acknowledged_at']);
        });
    }
};
