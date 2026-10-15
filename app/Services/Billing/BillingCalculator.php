<?php
namespace App\Services\Billing;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use App\Models\DailyUsage;
use Illuminate\Support\Carbon;

class BillingCalculator
{
    public function calculate(Subscription $subscription): array
    {
        $items = [];
        $total = 0;

        $segments = $subscription->segments()
            ->whereDate('ends_on', '>=', $subscription->current_period_start)
            ->whereDate('starts_on', '<=', $subscription->current_period_end)
            ->orderBy('starts_on')
            ->get();

        foreach ($segments as $segment) {
            $start = Carbon::parse($segment->starts_on)->max(Carbon::parse($subscription->current_period_start));
            $end = Carbon::parse($segment->ends_on)->min(Carbon::parse($subscription->current_period_end));
            if ($end->lt($start)) continue;

            $cycleDays = Carbon::parse($subscription->current_period_start)->diffInDays(Carbon::parse($subscription->current_period_end)) + 1;
            $segmentDays = $start->diffInDays($end) + 1;

            $base = intdiv($segment->base_price_cents * $segmentDays, $cycleDays);
            $included = intdiv($segment->included_units * $segmentDays, $cycleDays);

            $usage = (int) DailyUsage::where('customer_id', $subscription->customer_id)
                ->where('subscription_segment_id', $segment->id)
                ->whereBetween('usage_date', [$start->toDateString(), $end->toDateString()])
                ->sum('total_units');

            $overageUnits = max(0, $usage - $included);
            $overage = $overageUnits * $segment->overage_rate_cents;

            $items[] = [
                'segment_id' => $segment->id,
                'type' => 'base',
                'description' => "{$segment->starts_on} to {$segment->ends_on} base subscription",
                'quantity' => $segmentDays,
                'unit_price_cents' => $segment->base_price_cents,
                'amount_cents' => $base,
            ];

            if ($overageUnits > 0) {
                $items[] = [
                    'segment_id' => $segment->id,
                    'type' => 'overage',
                    'description' => "{$overageUnits} units over prorated allowance",
                    'quantity' => $overageUnits,
                    'unit_price_cents' => $segment->overage_rate_cents,
                    'amount_cents' => $overage,
                ];
            }

            $total += $base + $overage;
        }

        return ['items' => $items, 'total_cents' => $total];
    }
}
