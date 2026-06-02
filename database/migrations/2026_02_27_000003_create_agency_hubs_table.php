<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_hubs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agency_user_id');
            $table->unsignedBigInteger('managed_user_id')->unique();
            $table->string('display_name', 160);
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->foreign('agency_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('managed_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['agency_user_id', 'managed_user_id'], 'agency_hubs_owner_managed_unique');
            $table->index(['agency_user_id', 'status'], 'agency_hubs_owner_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_hubs');
    }
};

