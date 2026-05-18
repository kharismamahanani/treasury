<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use App\Models\BalanceHistory;
use Illuminate\Support\Carbon;

$dates = ['2026-04-28', '2026-04-29', '2026-04-30'];
foreach ($dates as $date) {
    echo "\n--- $date ---\n";
    $rows = BalanceHistory::whereDate('recorded_at', $date)
        ->orderBy('recorded_at')
        ->limit(30)
        ->get();
    echo "count=" . count($rows) . "\n";
    foreach ($rows as $r) {
        echo sprintf("pid=%s recorded_at=%s bal=%s src=%s\n", $r->product_id, $r->recorded_at, number_format($r->balance,2,'.',','), $r->source);
    }
}

$summary = BalanceHistory::selectRaw("DATE(recorded_at) as dt, count(*) as cnt, sum(balance) as total")
    ->whereBetween('recorded_at', ['2026-04-28 00:00:00','2026-04-29 23:59:59'])
    ->groupBy('dt')
    ->orderBy('dt')
    ->get();

echo "\nSUMMARY:\n";
foreach ($summary as $row) {
    echo "{$row->dt} cnt={$row->cnt} total=" . number_format($row->total,2,'.',',') . "\n";
}
