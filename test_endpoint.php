<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Simulate the produkSaldo endpoint call
$request = new \Illuminate\Http\Request([
    'tanggal' => '2026-04-30',
    'currency' => 'IDR',
]);

$controller = app(\App\Http\Controllers\LaporanController::class);

// Get response
$response = $controller->produkSaldo($request);

// Decode JSON response
if (is_string($response->getContent())) {
    $data = json_decode($response->getContent(), true);
    echo "Response for tanggal=2026-04-30:\n";
    echo "Count: " . count($data) . " products\n";
    if (!empty($data)) {
        echo "\nFirst product:\n";
        $first = reset($data);
        foreach ($first as $k => $v) {
            echo "  $k: " . (is_numeric($v) ? number_format($v) : $v) . "\n";
        }
    }
}
