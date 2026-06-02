<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_commission_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_user_id')->index();
            $table->unsignedBigInteger('referred_user_id')->index();
            $table->unsignedBigInteger('partner_attribution_id')->nullable()->index();
            $table->unsignedBigInteger('billing_record_id')->nullable()->index();
            $table->string('source_event_id')->nullable()->unique();
            $table->string('source_invoice_id')->nullable()->index();
            $table->string('entry_type', 32)->default('commission')->index();
            $table->integer('gross_amount_cents');
            $table->integer('commission_amount_cents');
            $table->string('currency', 3)->default('usd');
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('available_at')->nullable()->index();
            $table->unsignedBigInteger('payout_batch_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_commission_ledger');
    }
};
