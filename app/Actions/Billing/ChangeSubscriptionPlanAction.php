<?php
namespace App\Actions\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use App\Services\PlanCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChangeSubscriptionPlanAction
{
    public function __construct(private PlanCache $plans) {}

    public function execute(Subscription $subscription, int $newPlanId, Carbon $effectiveOn): Subscription
    {
        if ($subscription->status !== 'active') {
            throw new RuntimeException('Subscription is not active.');
        }

        if ($effectiveOn->lt(Carbon::parse($subscription->current_period_start)) ||
            $effectiveOn->gt(Carbon::parse($subscription->current_period_end))) {
            throw new RuntimeException('Effective date must be inside current billing period.');
        }

        $newPlan = $this->plans->get($newPlanId);

        if ($newPlan->merchant_id !== $subscription->customer->merchant_id) {
            throw new RuntimeException('Plan belongs to another merchant.');
        }

        return DB::transaction(function () use ($subscription, $newPlan, $effectiveOn) {
            $current = $subscription->segments()
                ->whereDate('starts_on', '<=', $effectiveOn)
                ->whereDate('ends_on', '>=', $effectiveOn)
                ->lockForUpdate()
                ->firstOrFail();

            if ($effectiveOn->toDateString() === $current->starts_on->toDateString()) {
                $current->update([
                    'plan_id' => $newPlan->id,
                    'base_price_cents' => $newPlan->base_price_cents,
                    'included_units' => $newPlan->included_units,
                    'overage_rate_cents' => $newPlan->overage_rate_cents,
                ]);
            } else {
                $current->update(['ends_on' => $effectiveOn->copy()->subDay()->toDateString()]);

                SubscriptionSegment::create([
                    'subscription_id' => $subscription->id,
                    'plan_id' => $newPlan->id,
                    'starts_on' => $effectiveOn->toDateString(),
                    'ends_on' => $subscription->current_period_end,
                    'base_price_cents' => $newPlan->base_price_cents,
                    'included_units' => $newPlan->included_units,
                    'overage_rate_cents' => $newPlan->overage_rate_cents,
                ]);
            }

            $subscription->update(['plan_id' => $newPlan->id]);

            return $subscription->fresh();
        });
    }
}
