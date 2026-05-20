<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Menambah field pendukung fitur Agenda Kerja:
     *
     *   banks.rate_review_days  → interval (hari) maksimum sebelum suku bunga
     *                             produk bank ini perlu di-review ulang.
     *                             NULL = tidak dipantau.
     *
     * CATATAN — instruksi_aro TIDAK ditambahkan karena products sudah memiliki
     * rollover_instruction enum('ARO','non-ARO','pencairan') yang secara semantik
     * identik. Menambah kolom baru akan memecah sumber kebenaran data.
     * Kueri agenda menggunakan: WHERE rollover_instruction IS NULL.
     */
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            if (! Schema::hasColumn('banks', 'rate_review_days')) {
                $table->unsignedSmallInteger('rate_review_days')
                      ->nullable()
                      ->after('default_threshold_bps')
                      ->comment('Interval maksimum (hari) sebelum suku bunga produk bank ini perlu direview. NULL = tidak dipantau.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            if (Schema::hasColumn('banks', 'rate_review_days')) {
                $table->dropColumn('rate_review_days');
            }
        });
    }
};
