<?php

namespace App\Console\Commands;

use App\Models\InterestSchedule;
use App\Models\Product;
use Illuminate\Console\Command;

class GenerateInterestSchedules extends Command
{
    protected $signature = 'schedules:generate {--months=3} {--product=}';
    protected $description = 'Generate interest schedule entries for active products with yield rates';

    public function handle(): int
    {
        $productId = $this->option('product');

        if ($productId) {
            $product = Product::find($productId);
            if (! $product) {
                $this->error("Produk ID {$productId} tidak ditemukan.");
                return 1;
            }

            $count = InterestSchedule::generateForProduct($product);
            $this->info("✅ {$count} jadwal dibuat untuk produk: {$product->nama_rekening} ({$product->account_number})");
            return 0;
        }

        $products  = Product::active()->where('yield_rate_offered', '>', 0)->get();
        $total     = 0;
        $processed = 0;

        foreach ($products as $product) {
            $count = InterestSchedule::generateForProduct($product);
            $total += $count;
            $processed++;

            if ($count > 0) {
                $this->line("  + {$count} jadwal → {$product->nama_rekening}");
            }
        }

        $this->info("✅ {$total} jadwal dibuat untuk {$processed} produk.");
        return 0;
    }
}
