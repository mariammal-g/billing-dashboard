<?php
namespace App\Jobs;
use App\Models\DailyUsage;
use App\Models\UsageEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
// use Illuminate\Bus\Dispatchable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AggregateDailyUsageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public int $usageEventId) {}

    public function handle(): void
    {
        $event = UsageEvent::findOrFail($this->usageEventId);
        DailyUsage::upsert([
            [
                'merchant_id' => $event->merchant_id,
                'customer_id' => $event->customer_id,
                'subscription_segment_id' => $event->subscription_segment_id,
                'usage_date' => $event->occurred_at->toDateString(),
                'total_units' => $event->units,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ], ['customer_id','subscription_segment_id','usage_date'], ['total_units' => DB::raw('daily_usage.total_units + VALUES(total_units)'),'updated_at']);
    }
}
