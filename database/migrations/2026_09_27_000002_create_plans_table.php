<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('base_price_cents');
            $table->enum('billing_cycle', ['monthly', 'yearly']);
            $table->unsignedBigInteger('included_units');
            $table->unsignedBigInteger('overage_rate_cents');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['merchant_id', 'active']);
        });
    }
    public function down(): void { Schema::dropIfExists('plans'); }
};
