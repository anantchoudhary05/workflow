<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per quotation version. Internal estimate fields live on the same rows
        // but are only rendered for users with the view-financials ability.
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('scope_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supersedes_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->string('title');
            $table->string('status', 20)->default('draft')->index();
            $table->char('currency', 3)->default('INR');
            $table->date('valid_until')->nullable();
            $table->string('payment_terms', 500)->nullable();
            $table->text('scope_summary')->nullable();
            $table->text('exclusions')->nullable();
            $table->text('assumptions')->nullable();
            $table->string('delivery_period', 120)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('optional_total', 12, 2)->default(0);
            $table->decimal('internal_cost_total', 12, 2)->default(0);
            $table->decimal('estimated_hours', 8, 2)->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_source', 255)->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['number', 'version']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40)->nullable();
            $table->string('description', 500);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 30)->default('item');
            $table->decimal('hours', 8, 2)->default(0);
            $table->decimal('cost_rate', 10, 2)->default(0);
            $table->decimal('internal_cost', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->boolean('is_optional')->default(false);
            $table->string('notes', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('approved_scope_version_id')->references('id')->on('scope_versions')->nullOnDelete();
            $table->foreign('approved_quotation_id')->references('id')->on('quotations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['approved_scope_version_id']);
            $table->dropForeign(['approved_quotation_id']);
        });
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
