<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Count records to delete
$toDelete = DB::table('balance_histories')
    ->where('source', 'manual')
    ->whereDate('recorded_at', '>=', '2026-05-01')
    ->count();

echo "Found $toDelete manual balance_history entries created on/after May 1\n";

if ($toDelete > 0) {
    // DELETE them
    $deleted = DB::table('balance_histories')
        ->where('source', 'manual')
        ->whereDate('recorded_at', '>=', '2026-05-01')
        ->delete();
    echo "✓ Deleted $deleted records\n\n";
    
    // Also delete duplicates from the official imports (same date, same product)
    // This needs a more careful query
    echo "Checking for duplicate entries from same monthly import...\n";
    $duplicates = DB::select("
        SELECT product_id, recorded_at, COUNT(*) as cnt
        FROM balance_histories
        WHERE recorded_at IN ('2026-03-31 00:00:00'::timestamp, '2026-04-30 00:00:00'::timestamp)
        GROUP BY product_id, recorded_at
        HAVING COUNT(*) > 1
    ");
    
    if (count($duplicates) > 0) {
        echo "Found duplicate entries:\n";
        foreach ($duplicates as $d) {
            echo "  product_id={$d->product_id}, date={$d->recorded_at}, count={$d->cnt}\n";
        }
        
        // For each duplicate, keep only 1
        foreach ($duplicates as $d) {
            $idsToDelete = DB::table('balance_histories')
                ->where('product_id', $d->product_id)
                ->where('recorded_at', $d->recorded_at)
                ->orderByDesc('id')
                ->skip(1)  // Skip first one, delete the rest
                ->pluck('id')
                ->toArray();
                
            if (count($idsToDelete) > 0) {
                DB::table('balance_histories')
                    ->whereIn('id', $idsToDelete)
                    ->delete();
                echo "  Deleted " . count($idsToDelete) . " duplicate(s) for product {$d->product_id}\n";
            }
        }
    }
}

echo "\nDone! Now testing the filter again...\n";

// Test product 60 again
$histories = DB::select("
  SELECT recorded_at, balance FROM balance_histories
  WHERE product_id = 60
  ORDER BY recorded_at DESC
");

echo "\nProduct 60 balance_histories after cleanup:\n";
foreach ($histories as $h) {
    echo "  {$h->recorded_at} → " . number_format($h->balance) . "\n";
}
