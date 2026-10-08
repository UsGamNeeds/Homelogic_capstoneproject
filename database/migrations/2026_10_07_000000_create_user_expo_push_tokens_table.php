<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Expo push tokens for the native mobile app. Kept apart from
     * user_push_subscriptions because a web-push subscription is an
     * endpoint + encryption keys, while an Expo token is a single opaque string
     * sent to Expo's push service.
     */
    public function up(): void
    {
        Schema::create('user_expo_push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('token')->unique();
            $table->string('platform', 16)->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_expo_push_tokens');
    }
};
