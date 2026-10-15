<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_segment_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->unsignedBigInteger('units');
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'idempotency_key']);
            $table->index(['customer_id', 'occurred_at']);
            $table->index(['subscription_segment_id', 'occurred_at']);
            $table->index(['merchant_id', 'occurred_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('usage_events'); }
};
