<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_segment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // base, overage
            $table->string('description');
            $table->unsignedBigInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_cents')->default(0);
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();
            $table->index(['invoice_id', 'type']);
        });
    }
    public function down(): void { Schema::dropIfExists('invoice_items'); }
};
