<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('products', 'nomor_bilyet')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('nomor_bilyet', 50)->nullable()
                  ->after('account_number')
                  ->comment('Nomor bilyet deposito dari fisik sertifikat.');

            // Unique per bank — satu bilyet tidak bisa dua kali di bank yang sama.
            // Soft-deleted rows tidak perlu diindex ulang, tetapi compound unique
            // di PostgreSQL tidak bisa filter WHERE deleted_at IS NULL secara native,
            // jadi constraint ini partial index dihandle di level aplikasi (Rule::unique).
            $table->index(['bank_id', 'nomor_bilyet'], 'products_bank_bilyet_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_bank_bilyet_idx');
            $table->dropColumn('nomor_bilyet');
        });
    }
};
