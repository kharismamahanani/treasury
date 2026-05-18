<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('interest_schedule_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->enum('frequency', ['monthly', 'quarterly', 'semester', 'maturity_only'])->default('monthly');
            $table->enum('day_convention', ['actual', 'end_of_month'])->default('actual');
            $table->integer('denominator')->default(365);
            $table->integer('auto_generate_months')->default(12);
            $table->foreignId('created_by')->nullable()->nullOnDelete()->constrained('users');
            $table->foreignId('updated_by')->nullable()->nullOnDelete()->constrained('users');
            $table->timestamps();
        });

        Schema::create('interest_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->date('payment_date');
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('days_in_period');
            $table->decimal('balance_at_period', 20, 2);
            $table->decimal('effective_rate', 8, 4);
            $table->decimal('interest_expected', 20, 2);
            $table->decimal('interest_actual', 20, 2)->nullable();
            $table->decimal('interest_gap', 20, 2)->nullable();
            $table->enum('input_method', ['manual', 'import', 'system'])->nullable();
            $table->text('note')->nullable();
            $table->foreignId('verified_by')->nullable()->nullOnDelete()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('yield_claim_id')->nullable()->nullOnDelete()->constrained('yield_claims');
            $table->enum('status', ['scheduled', 'pending_input', 'inputted', 'verified', 'claimed'])->default('scheduled');
            $table->foreignId('created_by')->nullable()->nullOnDelete()->constrained('users');
            $table->foreignId('updated_by')->nullable()->nullOnDelete()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'payment_date']);
            $table->index('status');
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interest_schedules');
        Schema::dropIfExists('interest_schedule_configs');
    }
};
