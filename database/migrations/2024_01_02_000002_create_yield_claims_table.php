<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel penagihan (klaim selisih imbal hasil) ke mitra bank.
     *
     * Alur status:
     *   draft     → otomatis dibuat sistem saat selisih ≥ threshold
     *   sent      → sudah dikirim ke bank (dicetak/diekspor)
     *   responded → bank sudah merespons (setuju / negosiasi)
     *   settled   → selisih sudah dibayarkan bank
     *   void      → dibatalkan (mis. salah hitung, sudah diselesaikan cara lain)
     */
    public function up(): void
    {
        Schema::create('yield_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number')->unique()->comment('Nomor dokumen penagihan, mis: TAG-2024-001');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('bank_id')->constrained('banks')->restrictOnDelete();

            // Periode yang diklaim
            $table->date('period_start')->comment('Awal periode imbal hasil yang diklaim');
            $table->date('period_end')->comment('Akhir periode imbal hasil yang diklaim');
            $table->integer('days')->comment('Jumlah hari periode (period_end - period_start + 1)');

            // Data yield pada saat klaim dibuat
            $table->decimal('balance_at_claim', 20, 2)->comment('Saldo pada saat periode klaim');
            $table->enum('currency', ['IDR', 'USD']);
            $table->decimal('yield_rate_offered', 8, 4)->comment('Rate penawaran/kontrak (%)');
            $table->decimal('yield_rate_actual', 8, 4)->comment('Rate aktual yang dibayarkan (%)');

            // Selisih — dihitung sistem, disimpan untuk audit
            $table->decimal('gap_bps', 8, 2)->comment('Selisih dalam basis poin (offered - actual) × 100');
            $table->decimal('interest_offered', 20, 2)->comment('Bunga seharusnya: saldo × rate_offered × hari / 365');
            $table->decimal('interest_actual', 20, 2)->comment('Bunga aktual yang diterima: saldo × rate_actual × hari / 365');
            $table->decimal('claim_amount', 20, 2)->comment('Jumlah tagihan = interest_offered - interest_actual');

            // Status & workflow
            $table->enum('status', ['draft', 'sent', 'responded', 'settled', 'void'])->default('draft');
            $table->date('sent_date')->nullable()->comment('Tanggal dokumen dikirim ke bank');
            $table->date('response_date')->nullable()->comment('Tanggal respons dari bank');
            $table->date('settlement_date')->nullable()->comment('Tanggal pelunasan klaim oleh bank');
            $table->decimal('settled_amount', 20, 2)->nullable()->comment('Jumlah yang benar-benar dibayar bank (bisa berbeda karena negosiasi)');
            $table->text('bank_response_note')->nullable()->comment('Catatan respons/negosiasi dari bank');
            $table->text('internal_note')->nullable()->comment('Catatan internal');

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['bank_id', 'status']);
            $table->index(['product_id', 'period_start']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yield_claims');
    }
};
