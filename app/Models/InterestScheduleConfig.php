<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class InterestScheduleConfig extends Model
{
    protected $fillable = [
        'product_id', 'frequency', 'day_convention', 'denominator',
        'auto_generate_months', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'denominator'          => 'integer',
        'auto_generate_months' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Returns array of ['period_start', 'period_end', 'payment_date'] tuples
     * within the given $from/$to window, based on this config's frequency.
     */
    public function generatePaymentDates(Carbon $from, Carbon $to): array
    {
        $product = $this->product;
        if (! $product || ! $product->placement_date) {
            return [];
        }

        $placementDate = $product->placement_date->copy();

        // Build the full sequence of payment dates from placement forward
        $paymentDates = [];

        if ($this->frequency === 'maturity_only') {
            if ($product->maturity_date) {
                $paymentDates[] = $product->maturity_date->copy();
            }
        } else {
            $step = match ($this->frequency) {
                'quarterly' => 3,
                'semester'  => 6,
                default     => 1, // monthly
            };

            $current = $placementDate->copy()->addMonths($step);
            $limit   = $to->copy()->addMonths(1); // small buffer

            while ($current->lte($limit)) {
                $paymentDates[] = $this->applyDayConvention($current->copy(), $placementDate->day);
                $current->addMonths($step);
            }
        }

        // Now build tuples and filter by $from/$to
        $result   = [];
        $prevDate = $placementDate->copy();

        foreach ($paymentDates as $pd) {
            $periodStart = $prevDate->copy()->addDay();
            $periodEnd   = $pd->copy();
            $payDate     = $pd->copy();

            if ($payDate->gte($from) && $payDate->lte($to)) {
                $result[] = [
                    'period_start' => $periodStart->toDateString(),
                    'period_end'   => $periodEnd->toDateString(),
                    'payment_date' => $payDate->toDateString(),
                ];
            }

            $prevDate = $pd->copy();
        }

        return $result;
    }

    private function applyDayConvention(Carbon $date, int $originalDay): Carbon
    {
        if ($this->day_convention !== 'end_of_month') {
            return $date;
        }

        $daysInMonth = $date->daysInMonth;
        if ($originalDay > $daysInMonth) {
            return $date->endOfMonth()->startOfDay();
        }

        return $date->setDay(min($originalDay, $daysInMonth));
    }
}
