<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('total_cents');
            $table->enum('status', ['draft', 'issued', 'paid', 'void'])->default('draft');
            $table->timestamps();
            $table->unique(['subscription_id', 'period_start', 'period_end']);
            $table->index(['merchant_id', 'period_start', 'period_end']);
        });
    }
    public function down(): void { Schema::dropIfExists('invoices'); }
};
