<?php
namespace App\Actions\Dashboard;
use App\Models\DailyUsage;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use App\Models\Plan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class GetMerchantDashboardAction
{
    public function execute(Merchant $merchant): array
    {
        $start = now()->startOfMonth()->toDateString();
        $end = now()->toDateString();
        $previousStart = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $previousEnd = now()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $top = DailyUsage::query()
            ->join('customers', 'customers.id', '=', 'daily_usage.customer_id')
            ->where('daily_usage.merchant_id', $merchant->id)
           // ->whereBetween('usage_date', [$start, $end])
            ->groupBy('customer_id', 'customers.name')
            ->orderByDesc(DB::raw('SUM(total_units)'))
            ->limit(5)
            ->get(['customer_id', 'customers.name', DB::raw('SUM(total_units) as total_usage')]);

        $current = DailyUsage::query()
            ->where('merchant_id', $merchant->id)
            ->whereBetween('usage_date', [$start, $end])
            ->groupBy('customer_id')
            ->pluck(DB::raw('SUM(total_units)'), 'customer_id');

        $previous = DailyUsage::query()
            ->where('merchant_id', $merchant->id)
            ->whereBetween('usage_date', [$previousStart, $previousEnd])
            ->groupBy('customer_id')
            ->pluck(DB::raw('SUM(total_units)'), 'customer_id');

        $risk = [];
        foreach ($previous as $customerId => $previousUsage) {
            if ($previousUsage <= 0) continue;
            $currentUsage = (int) ($current[$customerId] ?? 0);
            $drop = (($previousUsage - $currentUsage) / $previousUsage) * 100;
            if ($drop > 50) {
                $risk[] = ['customer_id' => (int)$customerId, 'previous_usage' => (int)$previousUsage, 'current_usage' => $currentUsage, 'drop_percent' => round($drop, 2)];
            }
        }

        $subscriptions = Subscription::query()
            ->whereHas('customer', fn($q) => $q->where('merchant_id', $merchant->id))
            ->where('status', 'active')
            ->with('customer')
            ->get();

        $projected = 0;
        foreach ($subscriptions as $subscription) {
            $segment = $subscription->segments()->whereDate('starts_on','<=',$end)->whereDate('ends_on','>=',$end)->first();
            if (!$segment) continue;
            $used = (int) DailyUsage::where('customer_id',$subscription->customer_id)->where('subscription_segment_id',$segment->id)->whereBetween('usage_date',[$segment->starts_on,$end])->sum('total_units');
            $daysElapsed = max(1, Carbon::parse($subscription->current_period_start)->diffInDays(now()) + 1);
            $cycleDays = Carbon::parse($subscription->current_period_start)->diffInDays(Carbon::parse($subscription->current_period_end)) + 1;
            $projectedUsage = (int) ceil($used * ($cycleDays / $daysElapsed));
            $projectedOverageUnits = max(0, $projectedUsage - $segment->included_units);
            $projected += $projectedOverageUnits * $segment->overage_rate_cents;
        }

        return [
            'current_cycle' => ['start' => $subscriptions->first()?->current_period_start?->toDateString(), 'end' => $subscriptions->first()?->current_period_end?->toDateString()],
            'top_customers' => $top,
            'projected_overage_revenue_cents' => $projected,
            'churn_risk' => $risk,
        ];
    }
}
