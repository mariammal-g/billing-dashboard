<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('subscription_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            // Immutable pricing snapshot used for billing.
            $table->unsignedBigInteger('base_price_cents');
            $table->unsignedBigInteger('included_units');
            $table->unsignedBigInteger('overage_rate_cents');
            $table->timestamps();
            $table->index(['subscription_id', 'starts_on', 'ends_on']);
            $table->index(['plan_id', 'starts_on']);
        });
    }
    public function down(): void { Schema::dropIfExists('subscription_segments'); }
};
