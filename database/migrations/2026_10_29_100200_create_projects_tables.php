<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->text('description')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('original_due_date')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->date('launch_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('priority', 10)->default('medium');
            $table->string('status', 30)->default('lead')->index();
            $table->string('handover_status', 30)->nullable();
            $table->unsignedBigInteger('approved_scope_version_id')->nullable();
            $table->unsignedBigInteger('approved_quotation_id')->nullable();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('client_summary')->nullable();
            $table->string('repository_url')->nullable();
            $table->string('staging_url')->nullable();
            $table->string('production_url')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('closure_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 60)->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('project_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason')->nullable();
            $table->boolean('is_override')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('project_date_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('old_date')->nullable();
            $table->date('new_date')->nullable();
            $table->text('reason');
            $table->string('delay_source', 20)->nullable(); // internal | client | scope_change
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_date_changes');
        Schema::dropIfExists('project_status_histories');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
