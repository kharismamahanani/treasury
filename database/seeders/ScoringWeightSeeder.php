<?php

namespace Database\Seeders;

use App\Models\ScoringWeight;
use Illuminate\Database\Seeder;

class ScoringWeightSeeder extends Seeder
{
    public function run(): void
    {
        if (ScoringWeight::count() > 0) {
            return;
        }

        $weights = [
            ['key' => 'rate',        'name' => 'Tingkat Bunga (Rate)',         'weight' => 35],
            ['key' => 'layanan',     'name' => 'Kualitas Layanan',             'weight' => 20],
            ['key' => 'keamanan',    'name' => 'Keamanan & Reputasi',          'weight' => 20],
            ['key' => 'penerimaan',  'name' => 'Volume Penerimaan',            'weight' => 10],
            ['key' => 'buku',        'name' => 'Kategori Buku Bank (BI)',      'weight' => 5],
            ['key' => 'bumn',        'name' => 'Status BUMN',                  'weight' => 5],
            ['key' => 'eksposur',    'name' => 'Risiko Konsentrasi (Inverse)', 'weight' => 5],
        ];

        foreach ($weights as $w) {
            ScoringWeight::create($w);
        }
    }
}
