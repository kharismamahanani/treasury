<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BalanceHistory;
use Illuminate\Support\Facades\DB;

// Direct query to check who created these May entries
$results = DB::select("
  SELECT product_id, recorded_at, balance, recorded_by, source
  FROM balance_histories
  WHERE DATE(recorded_at) >= '2026-05-01'
  ORDER BY product_id, recorded_at DESC
  LIMIT 20
");

echo "May balance_history entries (last 20):\n";
foreach ($results as $r) {
    echo "  pid={$r->product_id}, recorded_at={$r->recorded_at}, balance={$r->balance}, by={$r->recorded_by}, src={$r->source}\n";
}

echo "\n\nCheck if there are pending/stuck queue jobs...\n";
$jobs = DB::table('jobs')->get();
echo "Queue jobs: " . count($jobs) . "\n";
foreach ($jobs as $j) {
    echo "  {$j->queue}: {$j->payload}\n";
}
