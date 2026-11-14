<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->boolean('is_starred')->default(false)->after('auto_reply');
            $table->unsignedBigInteger('last_read_message_id')->default(0)->after('is_starred');
            $table->index(['bot_id', 'is_starred']);
            $table->index(['bot_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropIndex(['bot_id', 'is_starred']);
            $table->dropIndex(['bot_id', 'last_message_at']);
            $table->dropColumn(['is_starred', 'last_read_message_id']);
        });
    }
};
