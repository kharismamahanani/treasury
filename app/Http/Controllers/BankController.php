<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bank;
use App\Services\ExcelHelper;

class BankController extends Controller
{
    public function index()
    {
        $banks = Bank::withCount(['products as active_products_count' => function ($q) {
                        $q->where('is_active', true);
                    }])
                    ->withSum(['products as total_saldo_idr' => function ($q) {
                        $q->where('is_active', true)->where('currency', 'IDR');
                    }], 'balance')
                    ->withSum(['products as total_saldo_usd' => function ($q) {
                        $q->where('is_active', true)->where('currency', 'USD');
                    }], 'balance')
                    ->orderBy('name')
                    ->get();

        return response()->json($banks);
    }

    /** Ambil satu bank lengkap dengan produk aktifnya (untuk form edit) */
    public function show(Bank $bank)
    {
        $bank->loadCount(['products as active_products_count' => fn($q) => $q->where('is_active', true)]);
        return response()->json($bank);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:100',
            'code'      => 'required|string|max:20|unique:banks,code',
            'type'      => 'required|in:BUMN,Swasta,Asing,Daerah',
            'branch'    => 'nullable|string|max:100',
            'pic_name'  => 'nullable|string|max:100',
            'pic_phone' => 'nullable|string|max:20',
            'notes'     => 'nullable|string',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $bank = Bank::create($validated);

        return response()->json(['success' => true, 'bank' => $bank]);
    }

    public function update(Request $request, Bank $bank)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:100',
            'code'      => 'required|string|max:20|unique:banks,code,' . $bank->id,
            'type'      => 'required|in:BUMN,Swasta,Asing,Daerah',
            'branch'    => 'nullable|string|max:100',
            'pic_name'  => 'nullable|string|max:100',
            'pic_phone' => 'nullable|string|max:20',
            'notes'     => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $bank->update($validated);
        return response()->json(['success' => true]);
    }

    public function destroy(Bank $bank)
    {
        if ($bank->activeProducts()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Bank memiliki produk aktif. Nonaktifkan semua produk terlebih dahulu.',
            ], 422);
        }
        $bank->delete();
        return response()->json(['success' => true]);
    }

    public function exportExcel()
    {
        $banks = Bank::withCount(['products as active_products_count' => fn($q) => $q->where('is_active', true)])
            ->orderBy('name')->get();

        $columns = [
            ['key' => 'name',   'label' => 'Nama Bank',    'width' => 28, 'locked' => true],
            ['key' => 'code',   'label' => 'Kode',         'width' => 12, 'locked' => true],
            ['key' => 'type',   'label' => 'Tipe',         'width' => 14, 'locked' => true],
            ['key' => 'branch', 'label' => 'Cabang',       'width' => 24, 'locked' => true],
            ['key' => 'pic',    'label' => 'PIC Bank',     'width' => 24, 'locked' => true],
            ['key' => 'phone',  'label' => 'Telp PIC',     'width' => 18, 'locked' => true],
            ['key' => 'produk', 'label' => 'Produk Aktif', 'width' => 14, 'locked' => true],
            ['key' => 'status', 'label' => 'Status',       'width' => 12, 'locked' => true],
            ['key' => 'notes',  'label' => 'Catatan',      'width' => 40, 'locked' => true],
        ];

        $rows = $banks->map(fn($b) => [
            'name'   => $b->name,
            'code'   => $b->code,
            'type'   => $b->type,
            'branch' => $b->branch ?? '-',
            'pic'    => $b->pic_name ?? '-',
            'phone'  => $b->pic_phone ?? '-',
            'produk' => $b->active_products_count ?? 0,
            'status' => $b->is_active ? 'Aktif' : 'Nonaktif',
            'notes'  => $b->notes ?? '',
        ])->toArray();

        \App\Models\ExportLog::record('banks_excel', [], $banks->count());
        return ExcelHelper::download(
            filename:   'master_bank',
            columns:    $columns,
            rows:       $rows,
            sheetTitle: 'Master Bank',
            meta: ['Total Bank' => $banks->count(), 'Export' => now()->format('d/m/Y H:i')]
        );
    }
}
