<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('bank_id')->constrained('banks')->restrictOnDelete();
            $table->enum('currency', ['IDR', 'USD']);
            $table->decimal('balance', 20, 2);
            $table->decimal('yield_rate', 8, 4)->default(0);
            $table->string('source')->default('manual')->comment('manual | import | system');
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->index(['product_id', 'recorded_at']);
            $table->index(['bank_id', 'currency', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_histories');
    }
};
