<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 20);
            $table->string('priority', 10)->default('medium');
            $table->string('status', 30)->default('draft')->index();
            $table->text('acceptance_criteria')->nullable();
            $table->string('requested_by')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 60)->nullable();
            $table->string('module', 120)->nullable();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['project_id', 'code']);
        });

        // Immutable scope baselines: a frozen snapshot of the approved requirement set.
        Schema::create('scope_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->text('summary');
            $table->json('snapshot');
            $table->string('approval_source', 255);
            $table->string('approved_by_name')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at');
            $table->timestamps();
            $table->unique(['project_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scope_versions');
        Schema::dropIfExists('requirements');
    }
};
