<?php
namespace App\Actions\Usage;
use App\Jobs\AggregateDailyUsageJob;
use App\Models\Customer;
use App\Models\SubscriptionSegment;
use App\Models\UsageEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecordUsageAction
{
    public function execute(array $data, string $idempotencyKey): UsageEvent
    {
        if (!preg_match('/^[0-9a-fA-F-]{16,100}$/', $idempotencyKey)) {
            throw new RuntimeException('Invalid Idempotency-Key format.');
        }

        $occurredAt = Carbon::parse($data['occurred_at']);

        $customer = Customer::query()
            ->whereKey($data['customer_id'])
            ->where('merchant_id', $data['merchant_id'])
            ->firstOrFail();

        $segment = SubscriptionSegment::query()
            ->whereHas('subscription', function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)->where('status', 'active');
            })
            ->whereDate('starts_on', '<=', $occurredAt->toDateString())
            ->whereDate('ends_on', '>=', $occurredAt->toDateString())
            ->orderByDesc('starts_on')
            ->first();

        if (!$segment) {
            throw new RuntimeException('No active subscription segment covers occurred_at.');
        }

        try {
            $event = DB::transaction(function () use ($data, $idempotencyKey, $occurredAt, $customer, $segment) {
                $existing = UsageEvent::where('merchant_id', $customer->merchant_id)
                    ->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) return $existing;

                return UsageEvent::create([
                    'merchant_id' => $customer->merchant_id,
                    'customer_id' => $customer->id,
                    'subscription_id' => $segment->subscription_id,
                    'subscription_segment_id' => $segment->id,
                    'idempotency_key' => $idempotencyKey,
                    'units' => $data['units'],
                    'occurred_at' => $occurredAt,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $event = UsageEvent::where('merchant_id', $customer->merchant_id)
                ->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }

        if ($event->wasRecentlyCreated) {
            AggregateDailyUsageJob::dispatch($event->id)->afterCommit();
        }

        return $event;
    }
}
