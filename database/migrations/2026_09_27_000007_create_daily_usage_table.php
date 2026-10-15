<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('daily_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_segment_id')->constrained()->restrictOnDelete();
            $table->date('usage_date');
            $table->unsignedBigInteger('total_units')->default(0);
            $table->timestamps();
            $table->unique(['customer_id', 'subscription_segment_id', 'usage_date'],  'daily_usage_customer_segment_date_unique');
            $table->index(['merchant_id', 'usage_date']);
            $table->index(['customer_id', 'usage_date']);
        });
    }
    public function down(): void { Schema::dropIfExists('daily_usage'); }
};
