<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * A message that already exists in some Telegram chat (e.g. a customer's Premium-decorated message)
         * which the bot can copy or forward to other users with copyMessage / forwardMessage.
         */
        Schema::create('saved_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->bigInteger('from_chat_id');
            $table->unsignedBigInteger('message_id');
            $table->text('preview')->nullable();
            $table->boolean('has_custom_emoji')->default(false);
            $table->timestamps();
            $table->unique(['bot_id', 'from_chat_id', 'message_id']);
        });

        Schema::table('bot_replies', function (Blueprint $table) {
            $table->foreignId('saved_message_id')->nullable()->after('buttons')->constrained()->nullOnDelete();
            $table->string('send_mode', 10)->default('copy')->after('saved_message_id'); // copy | forward
        });

        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('image_path')->nullable();
            $table->string('photo_file_id')->nullable(); // reused after the first upload
            $table->json('buttons')->nullable();
            $table->foreignId('saved_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('send_mode', 10)->default('copy'); // copy | forward (saved message only)
            $table->string('status', 20)->default('scheduled'); // scheduled, running, completed, cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('next_batch_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('batch_size')->default(1000);
            $table->unsignedInteger('batch_delay_minutes')->default(10);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();
            $table->index(['status', 'next_batch_at']);
        });

        Schema::create('broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('telegram_user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('chat_id');
            $table->string('status', 10)->default('pending'); // pending, sent, failed
            $table->string('error')->nullable();
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['broadcast_id', 'telegram_user_id']);
            $table->index(['broadcast_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipients');
        Schema::dropIfExists('broadcasts');
        Schema::table('bot_replies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('saved_message_id');
            $table->dropColumn('send_mode');
        });
        Schema::dropIfExists('saved_messages');
    }
};
