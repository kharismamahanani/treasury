<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Bank;
use App\Models\BalanceHistory;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard.index');
    }

    public function summary(Request $request)
    {
        $currency = $request->get('currency', 'IDR');

        // Total per type per currency
        $byType = Product::active()
            ->select('type', 'currency', DB::raw('SUM(balance) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('type', 'currency')
            ->get()
            ->groupBy('currency');

        // Total per bank
        $byBank = Product::active()
            ->with('bank:id,name,code')
            ->select('bank_id', 'currency', DB::raw('SUM(balance) as total'))
            ->groupBy('bank_id', 'currency')
            ->get()
            ->groupBy('currency');

        // Grand total
        $grandTotal = Product::active()
            ->select('currency', DB::raw('SUM(balance) as total'))
            ->groupBy('currency')
            ->pluck('total', 'currency');

        // Best yield per type per currency
        $bestYield = [];
        foreach (['IDR', 'USD'] as $cur) {
            foreach (['deposito', 'giro', 'tabungan', 'kas'] as $type) {
                $best = Product::active()
                    ->with('bank:id,name,code')
                    ->where('type', $type)
                    ->where('currency', $cur)
                    ->where('yield_rate', '>', 0)
                    ->orderByDesc('yield_rate')
                    ->first();
                $bestYield[$cur][$type] = $best;
            }
        }

        // Maturity alerts
        $maturities30 = Product::active()->maturingWithin(30)->count();
        $maturities90 = Product::active()->maturingWithin(90)->count();
        $maturities7  = Product::active()->maturingWithin(7)->count();

        // Bank count
        $totalBanks = Bank::active()->count();
        $totalProducts = Product::active()->count();

        return response()->json([
            'byType'        => $byType,
            'byBank'        => $byBank,
            'grandTotal'    => $grandTotal,
            'bestYield'     => $bestYield,
            'maturities7'   => $maturities7,
            'maturities30'  => $maturities30,
            'maturities90'  => $maturities90,
            'totalBanks'    => $totalBanks,
            'totalProducts' => $totalProducts,
        ]);
    }

    public function trend(Request $request)
    {
        $currency = $request->get('currency', 'IDR');
        $days     = (int) $request->get('days', 60);

        // Aggregate daily balance sums from history
        $trend = BalanceHistory::where('currency', $currency)
            ->where('recorded_at', '>=', now()->subDays($days))
            ->select(
                DB::raw("DATE(recorded_at) as date"),
                DB::raw('SUM(balance) as total')
            )
            ->groupBy(DB::raw("DATE(recorded_at)"))
            ->orderBy('date')
            ->get();

        return response()->json($trend);
    }
}
