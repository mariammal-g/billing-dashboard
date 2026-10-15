<?php
namespace Tests\Unit;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use App\Models\DailyUsage;
use App\Services\Billing\BillingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_overage_at_exact_allowance(): void
    {
        [$subscription,$segment] = $this->subscription(1000, 200);
        DailyUsage::create(['merchant_id'=>1,'customer_id'=>1,'subscription_segment_id'=>$segment->id,'usage_date'=>'2026-09-10','total_units'=>1000]);
        $result = app(BillingCalculator::class)->calculate($subscription);
        $this->assertSame(0, collect($result['items'])->where('type','overage')->sum('amount_cents'));
    }

    public function test_overage_is_charged(): void
    {
        [$subscription,$segment] = $this->subscription(1000, 200);
        DailyUsage::create(['merchant_id'=>1,'customer_id'=>1,'subscription_segment_id'=>$segment->id,'usage_date'=>'2026-09-10','total_units'=>1200]);
        $result = app(BillingCalculator::class)->calculate($subscription);
        $this->assertSame(40000, collect($result['items'])->where('type','overage')->sum('amount_cents'));
    }

    private function subscription(int $included, int $rate): array
    {
        $merchant=Merchant::create(['name'=>'Test']);
        $plan=Plan::create(['merchant_id'=>1,'name'=>'Plan','base_price_cents'=>300000,'billing_cycle'=>'monthly','included_units'=>$included,'overage_rate_cents'=>$rate]);
        $customer=Customer::create(['merchant_id'=>1,'name'=>'Customer','email'=>'test@example.com']);
        $subscription=Subscription::create(['customer_id'=>1,'plan_id'=>1,'current_period_start'=>'2026-09-01','current_period_end'=>'2026-09-30','status'=>'active']);
        $segment=SubscriptionSegment::create(['subscription_id'=>1,'plan_id'=>1,'starts_on'=>'2026-09-01','ends_on'=>'2026-09-30','base_price_cents'=>300000,'included_units'=>$included,'overage_rate_cents'=>$rate]);
        return [$subscription,$segment];
    }
}
