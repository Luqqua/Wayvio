<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_payout_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_user_id')->index();
            $table->string('stripe_transfer_id')->nullable()->index();
            $table->string('currency', 3)->default('usd');
            $table->integer('net_amount_cents');
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_payout_batches');
    }
};
