<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tipe alokasi kas pada setiap produk
        // liquidity = kas operasional, investment = dana idle
        Schema::table('products', function (Blueprint $table) {
            $table->enum('kas_allocation', ['liquidity', 'investment'])
                  ->default('investment')
                  ->after('type')
                  ->comment('liquidity=kas operasional, investment=dana idle yang bisa ditempatkan');
        });

        // SK Alokasi Penempatan Dana (diterbitkan Rektor)
        Schema::create('sk_alokasi', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_sk')->unique()->comment('Nomor SK Rektor, mis: SK/123/UN10/2024');
            $table->string('judul')->comment('Judul SK');
            $table->date('tanggal_sk')->comment('Tanggal diterbitkan SK');
            $table->date('berlaku_mulai')->comment('Tanggal mulai berlaku');
            $table->date('berlaku_sampai')->nullable()->comment('Tanggal berakhir, null = tidak ada batas');
            $table->decimal('toleransi_persen', 5, 2)->default(5.00)
                  ->comment('Toleransi deviasi realisasi vs SK dalam persen');
            $table->boolean('is_active')->default(false);
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Detail alokasi per bank dalam satu SK
        Schema::create('sk_alokasi_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sk_alokasi_id')->constrained('sk_alokasi')->cascadeOnDelete();
            $table->foreignId('bank_id')->constrained('banks')->restrictOnDelete();
            $table->decimal('persen_alokasi', 8, 4)
                  ->comment('Persentase alokasi dana ke bank ini (0-100)');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['sk_alokasi_id', 'bank_id']);
        });

        // Snapshot idle cash bulanan (input manual bendahara)
        Schema::create('idle_cash_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('periode')->comment('Tanggal snapshot, biasanya akhir bulan');
            $table->decimal('total_idle_idr', 20, 2)->default(0)
                  ->comment('Total dana idle dalam IDR yang siap dialokasikan');
            $table->decimal('total_idle_usd', 20, 2)->default(0)
                  ->comment('Total dana idle dalam USD');
            $table->decimal('total_liquidity_idr', 20, 2)->default(0)
                  ->comment('Total kas operasional/likuiditas IDR (informasi)');
            $table->text('catatan')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('periode');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn($t) => $t->dropColumn('kas_allocation'));
        Schema::dropIfExists('idle_cash_snapshots');
        Schema::dropIfExists('sk_alokasi_detail');
        Schema::dropIfExists('sk_alokasi');
    }
};
