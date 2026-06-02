<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_custom_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('page_id')->nullable();
            $table->string('domain')->unique();
            $table->string('verification_token')->unique();
            $table->string('status')->default('pending'); // pending|verified|failed
            $table->string('ssl_status')->default('pending'); // pending|provisioning|active|failed
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'page_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_custom_domains');
    }
};
