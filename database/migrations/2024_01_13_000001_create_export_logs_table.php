<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('export_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->string('export_type', 80)
                  ->comment('Mis: products_excel, yield_pdf, penagihan_excel');

            $table->json('filters_used')->nullable()
                  ->comment('Parameter filter yang digunakan saat ekspor.');

            $table->unsignedInteger('row_count')->nullable();

            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('export_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_logs');
    }
};
