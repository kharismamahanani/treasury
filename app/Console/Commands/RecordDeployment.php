<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VersionControl;

class RecordDeployment extends Command
{
    protected $signature   = 'deploy:record
                                {version : Nomor versi (mis: 1.2.0)}
                                {--type=patch : Tipe rilis: major|minor|patch|hotfix}
                                {--notes= : Catatan rilis}
                                {--env= : Environment (default: APP_ENV)}';

    protected $description = 'Catat versi deployment baru ke database version control';

    public function handle(): int
    {
        $version = $this->argument('version');
        $type    = $this->option('type');
        $notes   = $this->option('notes');
        $env     = $this->option('env') ?: config('app.env');
        $gitHash = VersionControl::getGitHash();

        // Baca CHANGES.md jika ada
        $changesFile = base_path('CHANGES.md');
        $changes     = [];

        if (file_exists($changesFile)) {
            $lines = file($changesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (preg_match('/^[-*]\s+\[(\w+)\]\s+(.+)/', $line, $m)) {
                    $changes[] = [
                        'component'   => $m[1],
                        'description' => $m[2],
                        'type'        => 'update',
                    ];
                }
            }
        }

        $record = VersionControl::recordDeployment([
            'version'      => $version,
            'release_type' => $type,
            'release_date' => now()->toDateString(),
            'deployed_by'  => get_current_user() ?: 'system',
            'environment'  => $env,
            'git_hash'     => $gitHash,
            'changes'      => $changes,
            'release_notes'=> $notes,
        ]);

        $this->info("✅ Versi {$version} berhasil dicatat (ID: {$record->id})");
        if ($gitHash) $this->line("   Git hash: {$gitHash}");
        $this->line("   Environment: {$env}");

        return Command::SUCCESS;
    }
}
