<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\BalanceHistory;
use Illuminate\Support\Carbon;

// Test updateOrCreate logic
$product = Product::first();
$testDate = Carbon::parse('2026-04-30 00:00:00');

echo "Testing updateOrCreate() for duplicate date handling\n";
echo "Product: " . $product->nama_rekening . " (ID: {$product->id})\n\n";

// Clear old test entries
BalanceHistory::where('product_id', $product->id)
    ->where('recorded_at', $testDate)
    ->delete();

echo "1. First update for 2026-04-30 with balance 1000:\n";
BalanceHistory::where('product_id', $product->id)
    ->where('recorded_at', $testDate)
    ->updateOrCreate(
        ['product_id' => $product->id, 'recorded_at' => $testDate],
        [
            'bank_id' => $product->bank_id,
            'currency' => $product->currency,
            'balance' => 1000,
            'yield_rate' => 0,
            'source' => 'test',
            'recorded_by' => 1
        ]
    );

$count = BalanceHistory::where('product_id', $product->id)
    ->where('recorded_at', $testDate)
    ->count();
echo "   Count: $count entry (expected: 1)\n\n";

echo "2. Second update for 2026-04-30 with balance 2000 (should REPLACE, not duplicate):\n";
BalanceHistory::where('product_id', $product->id)
    ->where('recorded_at', $testDate)
    ->updateOrCreate(
        ['product_id' => $product->id, 'recorded_at' => $testDate],
        [
            'bank_id' => $product->bank_id,
            'currency' => $product->currency,
            'balance' => 2000,
            'yield_rate' => 0,
            'source' => 'test',
            'recorded_by' => 1
        ]
    );

$count = BalanceHistory::where('product_id', $product->id)
    ->where('recorded_at', $testDate)
    ->count();
$entry = BalanceHistory::where('product_id', $product->id)
    ->where('recorded_at', $testDate)
    ->first();

echo "   Count: $count entry (expected: 1 — NOT duplicated!)\n";
echo "   Balance value: " . $entry->balance . " (expected: 2000 — replaced!)\n\n";

if ($count == 1 && $entry->balance == 2000) {
    echo "✓ SUCCESS: updateOrCreate() prevents duplicates and replaces data correctly!\n";
} else {
    echo "✗ FAILED: Logic not working as expected\n";
}

// Cleanup
BalanceHistory::where('product_id', $product->id)
    ->where('recorded_at', $testDate)
    ->delete();
