<?php

// namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
// use Illuminate\Database\Seeder;

// class DashboardDemoSeeder extends Seeder
// {
//     /**
//      * Run the database seeds.
//      */
//     public function run(): void
//     {
//         //
//     }
// }


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use Illuminate\Support\Facades\DB;

class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $merchant = Merchant::firstOrCreate(
                ['name' => 'Acme Corp-1111']
            );

            $plan = Plan::firstOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'name' => 'Growth',
                ],
                [
                    'base_price_cents' => 99900,
                    'billing_cycle' => 'monthly',
                    'included_units' => 250000,
                    'overage_rate_cents' => 25,
                ]
            );

            $customers = [
                ['Beta Retail Pvt Ltd', 'beta@example.com'],
                ['Craft Foods Co.', 'craft@example.com'],
                ['Delta Mart', 'delta@example.com'],
                ['Nova Traders', 'nova@example.com'],
                ['QuickMart', 'quickmart@example.com'],
                ['Green Basket', 'green@example.com'],
                ['Urban Store', 'urban@example.com'],
                ['Prime Supplies', 'prime@example.com'],
                ['Sunrise Market', 'sunrise@example.com'],
                ['Metro Wholesale', 'metro@example.com'],
            ];

            $periodStart = now()->startOfMonth()->toDateString();
            $periodEnd = now()->endOfMonth()->toDateString();

            foreach ($customers as [$name, $email]) {
                $customer = Customer::firstOrCreate(
                    [
                        'merchant_id' => $merchant->id,
                        'email' => $email,
                    ],
                    ['name' => $name]
                );

                $subscription = Subscription::firstOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'plan_id' => $plan->id,
                    ],
                    [
                        'current_period_start' => $periodStart,
                        'current_period_end' => $periodEnd,
                        'status' => 'active',
                    ]
                );

                $subscription->update([
                    'current_period_start' => $periodStart,
                    'current_period_end' => $periodEnd,
                    'status' => 'active',
                ]);

                SubscriptionSegment::firstOrCreate(
                    [
                        'subscription_id' => $subscription->id,
                        'starts_on' => $periodStart,
                    ],
                    [
                        'plan_id' => $plan->id,
                        'ends_on' => $periodEnd,
                        'base_price_cents' => $plan->base_price_cents,
                        'included_units' => $plan->included_units,
                        'overage_rate_cents' => $plan->overage_rate_cents,
                    ]
                );
            }
        });

        $this->command?->info('Dashboard demo records created.');
    }
}
