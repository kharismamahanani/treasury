<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BalanceHistory;
use App\Models\Product;

// Check product 60 specifically (the one with balance = 55,539,768,710)
$product = Product::find(60);
echo "Product 60: {$product->nama_rekening}\n";
echo "Current balance: " . number_format($product->balance) . "\n\n";

$histories = BalanceHistory::where('product_id', 60)
    ->orderBy('recorded_at', 'desc')
    ->get();

echo "Balance histories for product 60 (" . count($histories) . " total):\n";
foreach ($histories as $h) {
    echo "  {$h->recorded_at} → balance: " . number_format($h->balance) . "\n";
}
