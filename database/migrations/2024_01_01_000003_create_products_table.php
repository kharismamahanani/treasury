<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained('banks')->restrictOnDelete();
            $table->enum('type', ['kas', 'deposito', 'giro', 'tabungan']);
            $table->string('account_number')->nullable()->comment('Nomor rekening / nomor seri deposito');
            $table->enum('currency', ['IDR', 'USD'])->default('IDR');
            $table->decimal('balance', 20, 2)->default(0)->comment('Saldo terkini');
            $table->decimal('yield_rate', 8, 4)->default(0)->comment('Tingkat bunga/return % per tahun');
            $table->integer('tenor_days')->nullable()->comment('Tenor dalam hari, khusus deposito');
            $table->date('placement_date')->nullable()->comment('Tanggal penempatan/pembukaan');
            $table->date('maturity_date')->nullable()->comment('Tanggal jatuh tempo deposito');
            $table->enum('rollover_instruction', ['ARO', 'non-ARO', 'pencairan'])->nullable()->comment('Instruksi saat jatuh tempo');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['bank_id', 'type', 'currency']);
            $table->index(['maturity_date', 'type']);
            $table->index('currency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
