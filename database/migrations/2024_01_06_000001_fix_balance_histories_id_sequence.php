<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Tabel yang perlu dipastikan id-nya punya DEFAULT nextval
    const TABLES = [
        'migrations', 'banks', 'products', 'users', 'balance_histories',
        'yield_claims', 'version_controls', 'sk_alokasi', 'sk_alokasi_detail',
        'personal_access_tokens', 'idle_cash_snapshots',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TABLES as $table) {
            $seqRow = DB::selectOne("SELECT pg_get_serial_sequence(?, 'id') as seq", [$table]);
            if (! $seqRow?->seq) {
                continue;
            }
            $seq = $seqRow->seq;

            // Pastikan kolom id punya DEFAULT nextval
            $hasDefault = DB::selectOne(
                "SELECT atthasdef FROM pg_attribute WHERE attrelid = ?::regclass AND attname = 'id'",
                [$table]
            );
            if (! $hasDefault?->atthasdef) {
                DB::statement("ALTER TABLE \"$table\" ALTER COLUMN id SET DEFAULT nextval('$seq')");
            }

            // Sync nilai sequence ke MAX(id) supaya tidak konflik
            DB::statement("SELECT setval('$seq', COALESCE((SELECT MAX(id) FROM \"$table\"), 0) + 1, false)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE \"$table\" ALTER COLUMN id DROP DEFAULT");
        }
    }
};
