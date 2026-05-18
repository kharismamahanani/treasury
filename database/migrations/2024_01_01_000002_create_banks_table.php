<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->enum('type', ['BUMN', 'Swasta', 'Asing', 'Daerah'])->default('Swasta');
            $table->string('branch')->nullable()->comment('Kantor cabang / unit kerja sama');
            $table->string('pic_name')->nullable()->comment('Nama PIC di bank');
            $table->string('pic_phone')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
