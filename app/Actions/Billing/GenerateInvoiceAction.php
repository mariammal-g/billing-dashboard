<?php
namespace App\Actions\Billing;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\Billing\BillingCalculator;
use Illuminate\Support\Facades\DB;

class GenerateInvoiceAction
{
    public function __construct(private BillingCalculator $calculator) {}

    public function execute(Subscription $subscription): Invoice
    {
        return DB::transaction(function () use ($subscription) {
            $existing = Invoice::where('subscription_id', $subscription->id)
                ->where('period_start', $subscription->current_period_start)
                ->where('period_end', $subscription->current_period_end)
                ->first();
            if ($existing) return $existing;

            $calculated = $this->calculator->calculate($subscription);
            $invoice = Invoice::create([
                'merchant_id' => $subscription->customer->merchant_id,
                'customer_id' => $subscription->customer_id,
                'subscription_id' => $subscription->id,
                'period_start' => $subscription->current_period_start,
                'period_end' => $subscription->current_period_end,
                'subtotal_cents' => $calculated['total_cents'],
                'total_cents' => $calculated['total_cents'],
                'status' => 'issued',
            ]);

            foreach ($calculated['items'] as $item) {
                $invoice->items()->create([
                    'subscription_segment_id' => $item['segment_id'],
                    'type' => $item['type'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price_cents' => $item['unit_price_cents'],
                    'amount_cents' => $item['amount_cents'],
                ]);
            }
            return $invoice->load('items');
        });
    }
}
