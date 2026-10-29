<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->string('status', 20)->default('planned')->index();
            $table->string('priority', 10)->default('medium');
            $table->unsignedTinyInteger('weight')->default(1);
            $table->text('acceptance_criteria')->nullable();
            $table->boolean('criteria_met')->default(false);
            $table->text('deliverables')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('override_reason')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('milestone_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_id')->constrained('milestones')->cascadeOnDelete();
            $table->unique(['milestone_id', 'depends_on_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestone_dependencies');
        Schema::dropIfExists('milestones');
    }
};
