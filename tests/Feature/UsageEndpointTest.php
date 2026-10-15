<?php
namespace Tests\Feature;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UsageEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_is_idempotent(): void
    {
        Queue::fake();
        [$merchant,$customer] = $this->setupSubscription();
        $payload=['merchant_id'=>$merchant->id,'customer_id'=>$customer->id,'units'=>10,'occurred_at'=>'2026-09-27 10:00:00'];
        $this->withHeader('Idempotency-Key','aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa')->postJson('/api/usage',$payload)->assertCreated();
        $this->withHeader('Idempotency-Key','aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa')->postJson('/api/usage',$payload)->assertCreated();
        $this->assertDatabaseCount('usage_events',1);
    }

    private function setupSubscription(): array
    {
        $m=Merchant::create(['name'=>'Test']);
        $p=Plan::create(['merchant_id'=>$m->id,'name'=>'Growth','base_price_cents'=>1000,'billing_cycle'=>'monthly','included_units'=>100,'overage_rate_cents'=>1]);
        $c=Customer::create(['merchant_id'=>$m->id,'name'=>'Customer','email'=>'c@example.com']);
        $s=Subscription::create(['customer_id'=>$c->id,'plan_id'=>$p->id,'current_period_start'=>'2026-09-01','current_period_end'=>'2026-09-30','status'=>'active']);
        SubscriptionSegment::create(['subscription_id'=>$s->id,'plan_id'=>$p->id,'starts_on'=>'2026-09-01','ends_on'=>'2026-09-30','base_price_cents'=>1000,'included_units'=>100,'overage_rate_cents'=>1]);
        return [$m,$c];
    }
}
