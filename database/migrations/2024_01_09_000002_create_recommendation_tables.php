<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bank_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained('banks')->restrictOnDelete();
            $table->date('periode');
            $table->decimal('skor_layanan', 5, 2)->nullable();
            $table->decimal('skor_keamanan', 5, 2)->nullable();
            $table->decimal('skor_digital', 5, 2)->nullable();
            $table->decimal('jumlah_penerimaan', 20, 2)->nullable();
            $table->enum('buku_bank', ['buku1', 'buku2', 'buku3', 'buku4'])->nullable();
            $table->boolean('is_bumn')->default(false);
            $table->text('catatan')->nullable();
            $table->foreignId('scored_by')->nullable()->nullOnDelete()->constrained('users');
            $table->timestamps();

            $table->unique(['bank_id', 'periode']);
        });

        Schema::create('scoring_weights', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('key', 50)->unique();
            $table->decimal('weight', 5, 2);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->nullOnDelete()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_weights');
        Schema::dropIfExists('bank_scores');
    }
};
