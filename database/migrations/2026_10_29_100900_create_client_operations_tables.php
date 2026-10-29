<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('type', 30);
            $table->dateTime('starts_at')->index();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('external_participants', 500)->nullable();
            $table->text('agenda')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('follow_up_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['meeting_id', 'user_id']);
        });

        Schema::create('meeting_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->text('decision');
            $table->string('decided_by')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('direction', 10)->default('outbound');
            $table->string('subject');
            $table->text('summary')->nullable();
            $table->dateTime('occurred_at')->index();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('outcome', 500)->nullable();
            $table->string('next_action', 500)->nullable();
            $table->timestamps();
        });

        // "Client waiting room": anything blocked on the client, plus follow-up scheduling.
        Schema::create('client_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->string('title');
            $table->nullableMorphs('related');
            $table->date('requested_at');
            $table->date('last_follow_up_at')->nullable();
            $table->date('next_follow_up_at')->nullable()->index();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('waiting')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('previews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('url', 500)->nullable();
            $table->string('version_label', 60);
            $table->date('shared_at')->nullable();
            $table->foreignId('shared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('review_due_at')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->text('feedback_summary')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
        });

        // Generic decision record: what was approved/rejected, which version, by whom, evidence.
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('approvable');
            $table->string('outcome', 30); // approved | approved_with_conditions | rejected | changes_requested
            $table->string('version_label', 60)->nullable();
            $table->string('decided_by_name');
            $table->string('source_reference', 500);
            $table->text('notes')->nullable();
            $table->dateTime('decided_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('previews');
        Schema::dropIfExists('client_actions');
        Schema::dropIfExists('communications');
        Schema::dropIfExists('meeting_decisions');
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
    }
};
