<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Tabel rate_notifications — log surat pemberitahuan perubahan suku bunga dari bank.
     *
     * Setiap baris = satu surat resmi bank yang diterima bendahara.
     * Saat dikonfirmasi, sistem otomatis memperbarui yield_rate_offered pada semua
     * produk aktif bank tersebut yang memenuhi syarat (maturity_date > berlaku_mulai).
     * Jejak audit per produk dicatat di tabel balance_histories (source='rate_notification').
     *
     * KEPUTUSAN DESAIN:
     *   - rate_lama/rate_baru menggunakan decimal(8,4) — konsisten dengan
     *     products.yield_rate_offered, bukan decimal(5,2) dari spesifikasi awal.
     *     Alasan: rate seperti 6.2500% memerlukan 4 desimal.
     *   - Logging TIDAK menggunakan version_controls karena tabel itu adalah
     *     deployment changelog (berisi git_hash, environment, release_type) —
     *     bukan audit log data bisnis. Jejak audit ada di balance_histories.
     */
    public function up(): void
    {
        Schema::create('rate_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bank_id')
                  ->constrained('banks')
                  ->restrictOnDelete()
                  ->comment('Bank yang mengirimkan surat pemberitahuan');

            $table->decimal('rate_lama', 8, 4)
                  ->nullable()
                  ->comment('Rate sebelumnya (informasi dari surat / terakhir tercatat)');

            $table->decimal('rate_baru', 8, 4)
                  ->comment('Rate baru yang berlaku sesuai surat');

            $table->date('berlaku_mulai')
                  ->comment('Tanggal mulai berlakunya rate baru');

            $table->string('nomor_surat', 100)
                  ->comment('Nomor referensi surat bank');

            $table->date('tanggal_surat')
                  ->comment('Tanggal pada kop surat bank');

            $table->integer('products_updated')
                  ->default(0)
                  ->comment('Jumlah produk yang diperbarui saat notifikasi ini diterapkan');

            $table->timestamp('applied_at')
                  ->nullable()
                  ->comment('Waktu saat bendahara mengkonfirmasi penerapan rate baru');

            $table->foreignId('input_by')
                  ->constrained('users')
                  ->restrictOnDelete()
                  ->comment('User yang menginput notifikasi ini');

            $table->timestamps();

            // Indeks untuk query per bank (agenda, hint di form produk)
            $table->index(['bank_id', 'berlaku_mulai']);
            $table->index('berlaku_mulai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_notifications');
    }
};
