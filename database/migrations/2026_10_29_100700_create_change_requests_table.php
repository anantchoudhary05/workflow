<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('number', 20);
            $table->string('title');
            $table->text('description');
            $table->text('reason')->nullable();
            $table->string('source', 40)->nullable();
            $table->date('received_at');
            $table->string('requested_by')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 10)->default('medium');
            $table->string('classification', 20)->nullable(); // bug | in_scope | new_scope
            $table->text('classification_rationale')->nullable();
            $table->text('scope_impact')->nullable();
            $table->decimal('extra_hours', 8, 2)->nullable();
            $table->decimal('cost_impact', 12, 2)->nullable();
            $table->smallInteger('timeline_impact_days')->nullable();
            $table->string('status', 30)->default('submitted')->index();
            $table->foreignId('internal_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('internal_reviewed_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('approval_evidence', 500)->nullable();
            $table->text('decision_notes')->nullable();
            $table->text('override_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['project_id', 'number']);
        });

        Schema::create('change_request_milestone', function (Blueprint $table) {
            $table->foreignId('change_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('milestone_id')->constrained()->cascadeOnDelete();
            $table->primary(['change_request_id', 'milestone_id']);
        });

        Schema::create('change_request_requirement', function (Blueprint $table) {
            $table->foreignId('change_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->primary(['change_request_id', 'requirement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_request_requirement');
        Schema::dropIfExists('change_request_milestone');
        Schema::dropIfExists('change_requests');
    }
};
