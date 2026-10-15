<?php
namespace App\Http\Controllers;
use App\Actions\Dashboard\GetMerchantDashboardAction;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class MerchantDashboardController extends Controller
{
    public function show(Merchant $merchant, GetMerchantDashboardAction $action): JsonResponse
    {
        return response()->json($action->execute($merchant));
    }

     public function index(): JsonResponse
    {
        $merchant = DB::table('merchants')
            ->orderBy('id')
            ->first();

        if (!$merchant) {
            return response()->json([
                'message' => 'No merchant found. Seed a merchant first.',
            ], 404);
        }

        $now = Carbon::now();
        $cycleStart = $now->copy()->startOfMonth()->toDateString();
        $cycleEnd = $now->copy()->endOfMonth()->toDateString();
        $previousStart = $now->copy()->subMonthNoOverflow()
            ->startOfMonth()->toDateString();
        $previousEnd = $now->copy()->subMonthNoOverflow()
            ->endOfMonth()->toDateString();

        $activeSubscription = DB::table('subscriptions')
            ->join('customers', 'customers.id', '=', 'subscriptions.customer_id')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('customers.merchant_id', $merchant->id)
            ->where('subscriptions.status', 'active')
            ->select(
                'plans.id as plan_id',
                'plans.name as plan_name',
                'plans.billing_cycle',
                'plans.included_units'
            )
            ->first();

        $includedUnits = (int) ($activeSubscription->included_units ?? 0);

        // Total usage for the merchant's current calendar month.
        $currentUsage = (int) DB::table('usage_events')
            ->where('merchant_id', $merchant->id)
            ->whereBetween('occurred_at', [
                $cycleStart . ' 00:00:00',
                $cycleEnd . ' 23:59:59',
            ])
            ->sum('units');

        // Calculate projected overage from current usage and the
        // included units / rate of the active plan.
        $overageRateCents = 0;

        if ($activeSubscription) {
            $overageRateCents = (int) DB::table('plans')
                ->where('id', $activeSubscription->plan_id)
                ->value('overage_rate_cents');
        }

        $projectedOverageCents = max(
            0,
            $currentUsage - $includedUnits
        ) * $overageRateCents;
        
$topCustomers = DB::table('daily_usage')
    ->join('customers', 'customers.id', '=', 'daily_usage.customer_id')
    ->where('daily_usage.merchant_id', $merchant->id)
    ->whereBetween('daily_usage.usage_date', [$cycleStart, $cycleEnd])
    ->select(
        'customers.id',
        'customers.name',
        DB::raw('SUM(daily_usage.total_units) as total_usage')
    )
    ->groupBy('customers.id', 'customers.name')
    ->orderByDesc('total_usage')
    ->limit(5)
    ->get()
    ->map(function ($customer) use ($includedUnits) {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'usage' => (int) $customer->total_usage,
            'allowance' => $includedUnits,
        ];
    })
    ->values();


        // Current and previous calendar-month usage per customer.
        $currentByCustomer = DB::table('daily_usage')
            ->where('merchant_id', $merchant->id)
            ->whereBetween('usage_date', [$cycleStart, $cycleEnd])
            ->select(
                'customer_id',
                DB::raw('SUM(total_units) as total')
            )
            ->groupBy('customer_id');

        $previousByCustomer = DB::table('daily_usage')
            ->where('merchant_id', $merchant->id)
            ->whereBetween('usage_date', [$previousStart, $previousEnd])
            ->select(
                'customer_id',
                DB::raw('SUM(total_units) as total')
            )
            ->groupBy('customer_id');

        $churnRisk = DB::table('customers')
            ->joinSub($previousByCustomer, 'previous_usage', function ($join) {
                $join->on('previous_usage.customer_id', '=', 'customers.id');
            })
            ->leftJoinSub($currentByCustomer, 'current_usage', function ($join) {
                $join->on('current_usage.customer_id', '=', 'customers.id');
            })
            ->where('customers.merchant_id', $merchant->id)
            ->where('previous_usage.total', '>', 0)
            ->whereRaw(
                'COALESCE(current_usage.total, 0) < previous_usage.total * 0.5'
            )
            ->select(
                'customers.id',
                'customers.name',
                DB::raw(
                    'ROUND((previous_usage.total - COALESCE(current_usage.total, 0)) * 100.0 / previous_usage.total) as drop_percent'
                )
            )
            ->orderByDesc('drop_percent')
            ->limit(10)
            ->get()
            ->map(fn ($customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'drop_percent' => (int) $customer->drop_percent,
            ])
            ->values();

        // Return daily usage for the last 30 calendar days.
        $trendStart = $now->copy()->subDays(29)->toDateString();

        $dailyUsage = DB::table('daily_usage')
            ->where('merchant_id', $merchant->id)
            ->whereBetween('usage_date', [$trendStart, $now->toDateString()])
            ->select(
                'usage_date as date',
                DB::raw('SUM(total_units) as units')
            )
            ->groupBy('usage_date')
            ->orderBy('usage_date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'units' => (int) $row->units,
            ])
            ->values();

        // Use actual configured values where the schema exposes them.
        $cacheDriver = config('cache.default');
        $queueDriver = config('queue.default');

        $rateLimit = 'Check your configured API rate limiter';

        return response()->json([
            'data' => [
                'merchant_name' => $merchant->name,
                'current_cycle_usage' => $currentUsage,
                'included_units' => $includedUnits,
                'projected_overage_revenue_cents' => $projectedOverageCents,
                'active_plan' => $activeSubscription ? [
                    'name' => $activeSubscription->plan_name,
                    'billing_cycle' => $activeSubscription->billing_cycle,
                ] : null,
                'top_customers' => $topCustomers,
                'churn_risk' => $churnRisk,
                'daily_usage' => $dailyUsage,
                'system_status' => [
                    'cache' => $cacheDriver,
                    'cache_ttl' => 'See cache configuration',
                    'aggregation' => $queueDriver,
                    'batch_size' => null,
                    'rate_limit' => $rateLimit,
                ],
            ],
        ]);
    }
}
