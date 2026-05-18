<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('saldo_awal_bulan', 20, 2)->nullable()->after('balance')
                  ->comment('Saldo sebelum update bulanan (saldo bulan lalu), otomatis disimpan saat commit saldo bulanan');
            $table->decimal('bunga_aktual_nominal', 20, 2)->nullable()->after('yield_rate_actual')
                  ->comment('Bunga aktual nominal (Rp/USD) yang diterima dari bank, diisi manual saat update saldo bulanan');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['saldo_awal_bulan', 'bunga_aktual_nominal']);
        });
    }
};
