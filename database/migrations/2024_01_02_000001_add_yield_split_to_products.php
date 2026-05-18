<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pemisahan imbal hasil menjadi dua kolom:
     *   yield_rate_offered → rate yang dijanjikan bank saat penempatan (penawaran)
     *   yield_rate_actual  → rate yang benar-benar dibayarkan bank (realisasi)
     *
     * yield_rate lama dipertahankan sebagai alias ke offered untuk backward compat.
     * yield_threshold → batas minimum selisih (dalam Rp/USD) yang memicu penagihan otomatis.
     * yield_threshold_bps → batas minimum selisih dalam basis poin (0.01%).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Rename kolom lama menjadi offered
            $table->decimal('yield_rate_offered', 8, 4)->default(0)
                  ->after('balance')
                  ->comment('Tingkat imbal hasil penawaran/kontrak (% p.a.)');

            $table->decimal('yield_rate_actual', 8, 4)->nullable()
                  ->after('yield_rate_offered')
                  ->comment('Tingkat imbal hasil aktual/realisasi dari bank (% p.a.). NULL = belum ada realisasi.');

            // Threshold penagihan per produk
            $table->decimal('yield_threshold_nominal', 20, 2)->nullable()
                  ->after('yield_rate_actual')
                  ->comment('Batas minimum selisih nominal (Rp/USD) untuk memicu penagihan otomatis. NULL = ikut threshold bank.');

            $table->decimal('yield_threshold_bps', 8, 2)->nullable()
                  ->after('yield_threshold_nominal')
                  ->comment('Batas minimum selisih basis poin untuk memicu penagihan otomatis. NULL = ikut threshold bank.');

            // Periode realisasi — penting untuk menghitung bunga harian akurat
            $table->date('yield_actual_period_start')->nullable()
                  ->after('yield_threshold_bps')
                  ->comment('Tanggal awal periode realisasi imbal hasil');

            $table->date('yield_actual_period_end')->nullable()
                  ->after('yield_actual_period_start')
                  ->comment('Tanggal akhir periode realisasi imbal hasil');

            $table->text('yield_actual_note')->nullable()
                  ->after('yield_actual_period_end')
                  ->comment('Catatan realisasi: bukti, referensi rekening koran, dll.');
        });

        // Threshold penagihan di level bank (default jika produk tidak dikonfigurasi)
        Schema::table('banks', function (Blueprint $table) {
            $table->decimal('default_threshold_nominal', 20, 2)->nullable()
                  ->after('notes')
                  ->comment('Default threshold selisih nominal (Rp/USD) untuk semua produk bank ini');

            $table->decimal('default_threshold_bps', 8, 2)->nullable()
                  ->after('default_threshold_nominal')
                  ->comment('Default threshold selisih basis poin untuk semua produk bank ini');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'yield_rate_offered', 'yield_rate_actual',
                'yield_threshold_nominal', 'yield_threshold_bps',
                'yield_actual_period_start', 'yield_actual_period_end',
                'yield_actual_note',
            ]);
        });

        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn(['default_threshold_nominal', 'default_threshold_bps']);
        });
    }
};
