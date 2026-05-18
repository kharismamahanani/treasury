<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('version_controls', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20)->comment('Nomor versi, mis: 1.0.0, 1.1.0');
            $table->string('release_type', 20)->default('patch')
                  ->comment('major | minor | patch | hotfix');
            $table->date('release_date');
            $table->string('deployed_by')->nullable()->comment('User/sistem yang deploy');
            $table->string('environment', 20)->default('production')
                  ->comment('production | staging | development');
            $table->string('git_hash', 40)->nullable()->comment('Git commit hash jika tersedia');
            $table->json('changes')->nullable()
                  ->comment('Array perubahan: [{component, file, type, description}]');
            $table->text('release_notes')->nullable()
                  ->comment('Catatan rilis untuk pengguna');
            $table->boolean('is_current')->default(false)
                  ->comment('Versi yang sedang berjalan');
            $table->timestamps();

            $table->index(['release_date', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('version_controls');
    }
};
