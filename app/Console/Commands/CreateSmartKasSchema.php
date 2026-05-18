<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateSmartKasSchema extends Command
{
    protected $signature   = 'smartkas:schema {--schema=smartkas : Nama schema yang dibuat}';
    protected $description = 'Buat schema PostgreSQL "smartkas" dan set sebagai search_path';

    public function handle(): int
    {
        $schema = $this->option('schema');

        try {
            // Buat schema jika belum ada
            DB::statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
            $this->info("✓ Schema '{$schema}' berhasil dibuat (atau sudah ada).");

            // Set search_path untuk sesi ini
            DB::statement("SET search_path TO {$schema}, public");
            $this->info("✓ search_path diset ke: {$schema}, public");

            $this->newLine();
            $this->line("Selanjutnya jalankan:");
            $this->line("  php artisan migrate");
            $this->line("  php artisan db:seed");

        } catch (\Throwable $e) {
            $this->error("Gagal: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
