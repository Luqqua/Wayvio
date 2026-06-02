<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_attributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referred_user_id')->unique();
            $table->unsignedBigInteger('partner_user_id')->index();
            $table->unsignedBigInteger('invite_code_id')->nullable()->index();
            $table->string('source', 32)->default('code');
            $table->unsignedInteger('commission_rate_bps');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('attributed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_attributions');
    }
};
