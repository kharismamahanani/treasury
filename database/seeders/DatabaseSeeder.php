<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Bank;
use App\Models\Product;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Users ────────────────────────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name'     => 'Administrator',
                'email'    => 'admin@ptnbh.ac.id',
                'password' => Hash::make('Admin@12345'),
                'role'     => 'admin',
            ]
        );

        User::firstOrCreate(
            ['username' => 'bendahara'],
            [
                'name'     => 'Bendahara Umum',
                'email'    => 'bendahara@ptnbh.ac.id',
                'password' => Hash::make('Bendahara@2024'),
                'role'     => 'editor',
            ]
        );

        User::firstOrCreate(
            ['username' => 'pimpinan'],
            [
                'name'     => 'Pimpinan',
                'email'    => 'pimpinan@ptnbh.ac.id',
                'password' => Hash::make('Pimpinan@2024'),
                'role'     => 'viewer',
            ]
        );

        // ── Banks ─────────────────────────────────────────────────────────────
        $banks = [
            ['name' => 'Bank Mandiri',  'code' => 'BMRI', 'type' => 'BUMN'],
            ['name' => 'BNI',           'code' => 'BNI',  'type' => 'BUMN'],
            ['name' => 'BRI',           'code' => 'BRI',  'type' => 'BUMN'],
            ['name' => 'BTN',           'code' => 'BTN',  'type' => 'BUMN'],
            ['name' => 'BCA',           'code' => 'BCA',  'type' => 'Swasta'],
            ['name' => 'Bank Jatim',    'code' => 'BJT',  'type' => 'Daerah'],
        ];

        $bankModels = [];
        foreach ($banks as $b) {
            $bankModels[$b['code']] = Bank::firstOrCreate(['code' => $b['code']], $b);
        }

        // ── Sample Products ───────────────────────────────────────────────────
        $sampleProducts = [
            // Kas
            ['bank_id' => $bankModels['BMRI']->id, 'type' => 'kas',      'account_number' => '1400012345678', 'currency' => 'IDR', 'balance' => 3500000000,  'yield_rate' => 0,    'tenor_days' => null, 'placement_date' => null, 'maturity_date' => null],
            ['bank_id' => $bankModels['BNI']->id,  'type' => 'kas',      'account_number' => '0987654321',   'currency' => 'IDR', 'balance' => 1200000000,  'yield_rate' => 0,    'tenor_days' => null, 'placement_date' => null, 'maturity_date' => null],

            // Deposito IDR
            ['bank_id' => $bankModels['BMRI']->id, 'type' => 'deposito', 'account_number' => 'DEP-2024-001', 'currency' => 'IDR', 'balance' => 10000000000, 'yield_rate' => 5.75, 'tenor_days' => 90,  'placement_date' => '2024-01-05', 'maturity_date' => now()->addDays(18)->toDateString()],
            ['bank_id' => $bankModels['BNI']->id,  'type' => 'deposito', 'account_number' => 'DEP-2024-002', 'currency' => 'IDR', 'balance' => 8000000000,  'yield_rate' => 6.00, 'tenor_days' => 180, 'placement_date' => '2024-01-10', 'maturity_date' => now()->addDays(62)->toDateString()],
            ['bank_id' => $bankModels['BCA']->id,  'type' => 'deposito', 'account_number' => 'DEP-2024-003', 'currency' => 'IDR', 'balance' => 5000000000,  'yield_rate' => 5.50, 'tenor_days' => 365, 'placement_date' => '2024-01-15', 'maturity_date' => now()->addDays(120)->toDateString()],
            ['bank_id' => $bankModels['BRI']->id,  'type' => 'deposito', 'account_number' => 'DEP-2024-004', 'currency' => 'IDR', 'balance' => 15000000000, 'yield_rate' => 6.25, 'tenor_days' => 90,  'placement_date' => '2024-03-01', 'maturity_date' => now()->addDays(5)->toDateString()],

            // Deposito USD
            ['bank_id' => $bankModels['BMRI']->id, 'type' => 'deposito', 'account_number' => 'DEP-USD-001',  'currency' => 'USD', 'balance' => 500000,      'yield_rate' => 4.50, 'tenor_days' => 90,  'placement_date' => '2024-02-01', 'maturity_date' => now()->addDays(45)->toDateString()],
            ['bank_id' => $bankModels['BNI']->id,  'type' => 'deposito', 'account_number' => 'DEP-USD-002',  'currency' => 'USD', 'balance' => 300000,      'yield_rate' => 4.75, 'tenor_days' => 180, 'placement_date' => '2024-01-20', 'maturity_date' => now()->addDays(88)->toDateString()],

            // Giro
            ['bank_id' => $bankModels['BMRI']->id, 'type' => 'giro',     'account_number' => '1234567890',   'currency' => 'IDR', 'balance' => 2500000000,  'yield_rate' => 2.50, 'tenor_days' => null, 'placement_date' => null, 'maturity_date' => null],
            ['bank_id' => $bankModels['BCA']->id,  'type' => 'giro',     'account_number' => '0987651234',   'currency' => 'IDR', 'balance' => 1800000000,  'yield_rate' => 2.00, 'tenor_days' => null, 'placement_date' => null, 'maturity_date' => null],
            ['bank_id' => $bankModels['BJT']->id,  'type' => 'giro',     'account_number' => '0011223344',   'currency' => 'IDR', 'balance' => 750000000,   'yield_rate' => 2.75, 'tenor_days' => null, 'placement_date' => null, 'maturity_date' => null],

            // Tabungan
            ['bank_id' => $bankModels['BRI']->id,  'type' => 'tabungan', 'account_number' => '110011001100', 'currency' => 'IDR', 'balance' => 4200000000,  'yield_rate' => 3.00, 'tenor_days' => null, 'placement_date' => null, 'maturity_date' => null],
            ['bank_id' => $bankModels['BTN']->id,  'type' => 'tabungan', 'account_number' => '220022002200', 'currency' => 'IDR', 'balance' => 3100000000,  'yield_rate' => 3.50, 'tenor_days' => null, 'placement_date' => null, 'maturity_date' => null],
        ];

        foreach ($sampleProducts as $p) {
            $product = Product::firstOrCreate(
                ['account_number' => $p['account_number'], 'bank_id' => $p['bank_id']],
                array_merge($p, ['created_by' => $admin->id, 'updated_by' => $admin->id])
            );

            // Record initial balance history
            if ($product->wasRecentlyCreated) {
                $product->balanceHistories()->create([
                    'bank_id'     => $product->bank_id,
                    'currency'    => $product->currency,
                    'balance'     => $product->balance,
                    'yield_rate'  => $product->yield_rate,
                    'source'      => 'system',
                    'note'        => 'Initial seed data',
                    'recorded_by' => $admin->id,
                    'recorded_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }

        $this->command->info('✅ Seeder selesai. Login: admin / Admin@12345');
        // ── Version Control (Initial) ─────────────────────────────────────────────
        if (\App\Models\VersionControl::count() === 0) {
            \App\Models\VersionControl::create([
                'version'      => '1.0.0',
                'release_type' => 'major',
                'release_date' => now()->toDateString(),
                'deployed_by'  => 'system',
                'environment'  => config('app.env', 'production'),
                'git_hash'     => \App\Models\VersionControl::getGitHash(),
                'is_current'   => true,
                'release_notes'=> 'Rilis perdana SmartKas — Sistem Manajemen Kas & Investasi PTNBH',
                'changes'      => [
                    ['component'=>'System','file'=>'All','type'=>'add','description'=>'Initial release SmartKas v1.0.0'],
                    ['component'=>'Auth','file'=>'LoginController','type'=>'add','description'=>'Sistem autentikasi role-based (admin/editor/viewer)'],
                    ['component'=>'Dashboard','file'=>'DashboardController','type'=>'add','description'=>'Overview KPI saldo, chart distribusi, tren historis'],
                    ['component'=>'Produk','file'=>'ProductController','type'=>'add','description'=>'CRUD produk keuangan (kas, deposito, giro, tabungan)'],
                    ['component'=>'Imbal Hasil','file'=>'YieldClaimController','type'=>'add','description'=>'Tracking yield penawaran vs aktual, penagihan selisih'],
                    ['component'=>'SK Alokasi','file'=>'SkAlokasiController','type'=>'add','description'=>'SK alokasi dana investasi per bank dengan evaluasi kepatuhan'],
                    ['component'=>'Saldo Bulanan','file'=>'SaldoBulananController','type'=>'add','description'=>'Update saldo bulanan dua tahap dengan preview & konfirmasi'],
                ],
            ]);
        }

    }
}
// Note: append this block inside run() method manually, or it runs standalone
