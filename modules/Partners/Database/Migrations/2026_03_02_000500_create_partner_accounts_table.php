<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('active')->index();
            $table->unsignedInteger('default_commission_rate_bps')->default(3000);
            $table->string('stripe_connect_account_id')->nullable();
            $table->unsignedInteger('payout_minimum_cents')->default(5000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_accounts');
    }
};
