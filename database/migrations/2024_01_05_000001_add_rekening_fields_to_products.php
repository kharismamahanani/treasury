<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('nama_rekening')->nullable()
                  ->after('account_number')
                  ->comment('Nama rekening sesuai dokumen, mis: PEN GRO 1 UM, RPK DEP 1 UM');

            $table->enum('kategori_rekening', [
                'penerimaan',
                'rpk_deposito',
                'rpk_giro_tabungan',
                'dana_kelolaan',
                'dana_abadi_giro',
                'dana_abadi_deposito',
            ])->nullable()
              ->after('nama_rekening')
              ->comment('Kategori rekening untuk grouping laporan');

            $table->index('kategori_rekening');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['kategori_rekening']);
            $table->dropColumn(['nama_rekening', 'kategori_rekening']);
        });
    }
};
