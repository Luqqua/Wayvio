<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_payment_id')->index();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('usd');
            $table->foreignId('tier_id')->constrained('tiers')->cascadeOnDelete();
            $table->unsignedTinyInteger('period_months')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_records');
    }
};
