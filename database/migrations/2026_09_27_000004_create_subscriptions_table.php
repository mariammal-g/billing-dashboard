<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->date('current_period_start');
            $table->date('current_period_end');
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->timestamps();
            $table->index(['customer_id', 'status']);
            $table->index(['current_period_start', 'current_period_end']);
        });
    }
    public function down(): void { Schema::dropIfExists('subscriptions'); }
};
