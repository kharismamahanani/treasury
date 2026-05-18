<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VersionControl;
use App\Services\ExcelHelper;

class VersionControlController extends Controller
{
    public function index()
    {
        $versions = VersionControl::orderByDesc('release_date')
            ->orderByDesc('id')
            ->get();

        return response()->json($versions->map(fn($v) => [
            'id'               => $v->id,
            'version'          => $v->version,
            'release_type'     => $v->release_type,
            'release_date'     => $v->release_date?->format('d/m/Y'),
            'release_date_raw' => $v->release_date?->format('Y-m-d'),
            'deployed_by'      => $v->deployed_by,
            'environment'      => $v->environment,
            'git_hash'         => $v->git_hash,
            'changes'          => $v->changes ?? [],
            'release_notes'    => $v->release_notes,
            'is_current'       => $v->is_current,
            'badge_class'      => $v->relase_type_badge,
            'changes_count'    => count($v->changes ?? []),
        ]));
    }

    public function current()
    {
        $v = VersionControl::current();
        if (! $v) return response()->json(null);

        return response()->json([
            'version'      => $v->version,
            'release_date' => $v->release_date?->format('d/m/Y'),
            'release_type' => $v->release_type,
            'git_hash'     => $v->git_hash,
            'environment'  => $v->environment,
        ]);
    }

    /** Admin: input versi baru manual (jika tidak pakai artisan) */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'version'      => 'required|string|max:20',
            'release_type' => 'required|in:major,minor,patch,hotfix',
            'release_date' => 'required|date',
            'release_notes'=> 'nullable|string',
            'changes'      => 'nullable|array',
            'changes.*.component'  => 'required|string|max:100',
            'changes.*.file'       => 'nullable|string|max:200',
            'changes.*.type'       => 'nullable|in:add,update,fix,remove',
            'changes.*.description'=> 'required|string|max:500',
        ]);

        $record = VersionControl::recordDeployment([
            'version'      => $validated['version'],
            'release_type' => $validated['release_type'],
            'release_date' => $validated['release_date'],
            'deployed_by'  => auth()->user()->name,
            'environment'  => config('app.env'),
            'git_hash'     => VersionControl::getGitHash(),
            'changes'      => $validated['changes'] ?? [],
            'release_notes'=> $validated['release_notes'] ?? null,
        ]);

        return response()->json(['success' => true, 'id' => $record->id]);
    }

    public function exportExcel()
    {
        $versions = VersionControl::orderByDesc('release_date')->get();

        $columns = [
            ['key' => 'version',      'label' => 'Versi',          'width' => 12,  'locked' => true],
            ['key' => 'release_type', 'label' => 'Tipe Rilis',     'width' => 14,  'locked' => true],
            ['key' => 'release_date', 'label' => 'Tgl Rilis',      'width' => 14,  'locked' => true],
            ['key' => 'deployed_by',  'label' => 'Deployed Oleh',  'width' => 20,  'locked' => true],
            ['key' => 'environment',  'label' => 'Environment',    'width' => 14,  'locked' => true],
            ['key' => 'git_hash',     'label' => 'Git Hash',       'width' => 14,  'locked' => true],
            ['key' => 'component',    'label' => 'Komponen',       'width' => 22,  'locked' => true],
            ['key' => 'file',         'label' => 'File/Controller','width' => 30,  'locked' => true],
            ['key' => 'change_type',  'label' => 'Jenis Perubahan','width' => 16,  'locked' => true],
            ['key' => 'description',  'label' => 'Keterangan Perubahan', 'width' => 50, 'locked' => true],
            ['key' => 'notes',        'label' => 'Release Notes',  'width' => 40,  'locked' => true],
            ['key' => 'is_current',   'label' => 'Versi Aktif',    'width' => 12,  'locked' => true],
        ];

        $rows = [];
        foreach ($versions as $v) {
            $changes = $v->changes ?? [];
            if (empty($changes)) {
                $rows[] = [
                    'version'      => $v->version,
                    'release_type' => strtoupper($v->release_type),
                    'release_date' => $v->release_date?->format('d/m/Y'),
                    'deployed_by'  => $v->deployed_by ?? '-',
                    'environment'  => $v->environment,
                    'git_hash'     => $v->git_hash ?? '-',
                    'component'    => '-',
                    'file'         => '-',
                    'change_type'  => '-',
                    'description'  => '-',
                    'notes'        => $v->release_notes ?? '',
                    'is_current'   => $v->is_current ? 'YA' : '',
                ];
            } else {
                foreach ($changes as $i => $change) {
                    $rows[] = [
                        'version'      => $i === 0 ? $v->version : '',
                        'release_type' => $i === 0 ? strtoupper($v->release_type) : '',
                        'release_date' => $i === 0 ? $v->release_date?->format('d/m/Y') : '',
                        'deployed_by'  => $i === 0 ? ($v->deployed_by ?? '-') : '',
                        'environment'  => $i === 0 ? $v->environment : '',
                        'git_hash'     => $i === 0 ? ($v->git_hash ?? '-') : '',
                        'component'    => $change['component'] ?? '-',
                        'file'         => $change['file'] ?? '-',
                        'change_type'  => strtoupper($change['type'] ?? 'UPDATE'),
                        'description'  => $change['description'] ?? '-',
                        'notes'        => $i === 0 ? ($v->release_notes ?? '') : '',
                        'is_current'   => $i === 0 && $v->is_current ? 'YA' : '',
                    ];
                }
            }
        }

        return ExcelHelper::download(
            filename:   'version_control_smartkas',
            columns:    $columns,
            rows:       $rows,
            sheetTitle: 'Version Control',
            meta: [
                'Aplikasi'       => 'SmartKas — Universitas Negeri Malang',
                'Total Versi'    => $versions->count() . ' rilis',
                'Versi Aktif'    => VersionControl::current()?->version ?? '-',
                'Export Tanggal' => now()->format('d/m/Y H:i'),
            ]
        );
    }
}
