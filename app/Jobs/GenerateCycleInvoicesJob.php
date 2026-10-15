<?php
namespace App\Jobs;
use App\Actions\Billing\GenerateInvoiceAction;
use App\Models\Subscription;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateCycleInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;
    public function handle(GenerateInvoiceAction $action): void
    {
        Subscription::query()->where('status','active')->chunkById(500, function ($subscriptions) use ($action) {
            foreach ($subscriptions as $subscription) $action->execute($subscription);
        });
    }
}
