<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('analytics_events_extended', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('base_event_id')->nullable(); // optional link to core visits row
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('page_id')->nullable();
            $table->unsignedBigInteger('link_id')->nullable();
            $table->string('device_type', 20)->nullable();
            $table->string('browser', 50)->nullable();
            $table->string('operating_system', 50)->nullable();
            $table->string('country', 5)->nullable();
            $table->string('referrer', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'page_id', 'link_id']);
            $table->index('base_event_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events_extended');
    }
};
