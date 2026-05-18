<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BalanceHistory;
use App\Http\Controllers\LaporanController;
use Illuminate\Http\Request;

// Test the filter logic with different dates
echo "Testing filter logic:\n\n";

$controller = app(LaporanController::class);

// Test 1: Filter for 30 April
$req1 = new Request(['tanggal' => '2026-04-30', 'currency' => 'IDR']);
$resp1 = json_decode($controller->produkSaldo($req1)->getContent(), true);
echo "Filter 2026-04-30: " . count($resp1) . " products\n";
if (!empty($resp1)) {
    $sample = reset($resp1);
    echo "  Sample: {$sample['nama_rekening']} = " . number_format($sample['balance']) . "\n";
}

// Test 2: Filter for 31 March
$req2 = new Request(['tanggal' => '2026-03-31', 'currency' => 'IDR']);
$resp2 = json_decode($controller->produkSaldo($req2)->getContent(), true);
echo "\nFilter 2026-03-31: " . count($resp2) . " products\n";
if (!empty($resp2)) {
    $sample = reset($resp2);
    echo "  Sample: {$sample['nama_rekening']} = " . number_format($sample['balance']) . "\n";
}

// Test 3: Filter for 31 May (should pick April 30, not May 3)
$req3 = new Request(['tanggal' => '2026-05-31', 'currency' => 'IDR']);
$resp3 = json_decode($controller->produkSaldo($req3)->getContent(), true);
echo "\nFilter 2026-05-31: " . count($resp3) . " products\n";
if (!empty($resp3)) {
    $sample = reset($resp3);
    echo "  Sample: {$sample['nama_rekening']} = " . number_format($sample['balance']) . "\n";
}

// Test 4: Current (no filter)
$req4 = new Request(['currency' => 'IDR']);
$resp4 = json_decode($controller->produkSaldo($req4)->getContent(), true);
echo "\nCurrent (no filter): " . count($resp4) . " products\n";
if (!empty($resp4)) {
    $sample = reset($resp4);
    echo "  Sample: {$sample['nama_rekening']} = " . number_format($sample['balance']) . "\n";
}

echo "\n✓ If balance values are consistent across dates 31-Mar, 30-Apr, 31-May, fix is working!\n";
