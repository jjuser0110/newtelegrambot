<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('token'); // stored encrypted
            $table->unsignedBigInteger('telegram_bot_id')->unique();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('webhook_secret', 64);
            $table->boolean('webhook_set')->default(false);
            $table->unsignedBigInteger('last_update_id')->default(0); // used by polling mode
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bot_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->string('trigger')->default('/start'); // "/start", a keyword, or "*" for fallback
            $table->text('message')->nullable();
            $table->string('image_path')->nullable();
            $table->json('buttons')->nullable(); // [{text, type: url|callback, value}]
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['bot_id', 'trigger']);
        });

        Schema::create('telegram_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('chat_id');
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('language_code', 16)->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['bot_id', 'chat_id']);
        });

        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('telegram_user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('chat_id');
            $table->string('direction', 3); // in | out
            $table->string('type', 20)->default('text'); // text, photo, callback, other
            $table->text('message')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['bot_id', 'chat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
        Schema::dropIfExists('telegram_users');
        Schema::dropIfExists('bot_replies');
        Schema::dropIfExists('bots');
    }
};
