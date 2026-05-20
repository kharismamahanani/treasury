<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Audit log ringan untuk setiap perubahan data oleh bendahara.
     *
     * Satu baris per field yang berubah saat UPDATE.
     * Satu baris (field_changed = null, nilai_baru = JSON) untuk CREATE.
     * Satu baris (field_changed = null, nilai_lama = JSON) untuk DELETE.
     *
     * Tabel ini hanya APPEND — tidak ada UPDATE atau DELETE.
     * Tidak ada updated_at karena entri audit tidak boleh diubah.
     *
     * Catatan desain:
     *   - version_controls TIDAK dipakai karena itu deployment changelog (git_hash,
     *     environment, release_type) — bukan audit log data bisnis.
     *   - IP diambil dari request()->ip() (bukan X-Forwarded-For langsung),
     *     yang sudah dihandle TrustedProxy middleware Laravel jika dibutuhkan.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('User yang melakukan aksi. NULL jika dari sistem/artisan.');

            $table->string('model_type', 80)
                  ->comment('Nama class model (class_basename): Product, YieldClaim, dst.');

            $table->unsignedBigInteger('model_id')
                  ->comment('Primary key dari record yang berubah.');

            $table->enum('action', ['create', 'update', 'delete']);

            // NULL untuk action create/delete (nilai ada di nilai_lama/nilai_baru sebagai JSON).
            // Terisi untuk action update: nama kolom DB yang berubah.
            $table->string('field_changed', 80)->nullable();

            $table->text('nilai_lama')->nullable()
                  ->comment('Nilai sebelum perubahan. JSON untuk create/delete.');

            $table->text('nilai_baru')->nullable()
                  ->comment('Nilai setelah perubahan. JSON untuk create/delete.');

            $table->string('ip_address', 45)->nullable()
                  ->comment('Dari request()->ip() — sudah melewati TrustedProxy Laravel.');

            $table->string('user_agent', 300)->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Indeks untuk filter di halaman audit log
            $table->index(['model_type', 'model_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
