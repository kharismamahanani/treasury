<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Check if there's an import log or activity table
$tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'smartkas'");
$tableNames = array_column($tables, 'table_name');
echo "Available tables:\n";
foreach ($tableNames as $t) {
    if (str_contains($t, 'import') || str_contains($t, 'log') || str_contains($t, 'activity')) {
        echo "  * $t\n";
    }
}

// Check who updated the products table recently
$updates = DB::select("SELECT created_at, updated_at, COUNT(*) as cnt FROM products GROUP BY DATE(updated_at) ORDER BY updated_at DESC LIMIT 5");
echo "\nProduct updates by date:\n";
foreach ($updates as $u) {
    echo "  {$u->updated_at}: {$u->cnt} records\n";
}
