<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\TelegramMessage;
use App\Models\TelegramUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LiveChatTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-abc';

    private function makeBot(): Bot
    {
        $bot = User::factory()->create()->bots()->create([
            'token' => '123456789:AAHfakeTokenForTestingOnly_abcdefghijk',
            'telegram_bot_id' => random_int(1, 999_999_999),
            'username' => 'demo_bot',
            'first_name' => 'Demo',
            'webhook_secret' => self::SECRET,
        ]);
        $bot->replies()->create(['trigger' => '*', 'message' => 'Auto answer']);

        return $bot;
    }

    private function receive(Bot $bot, int $chatId, string $text): void
    {
        $this->postJson("/telegram/webhook/{$bot->id}", ['update_id' => random_int(1, 99999), 'message' => [
            'message_id' => random_int(1, 99999), 'text' => $text,
            'from' => ['id' => $chatId, 'first_name' => 'U'.$chatId], 'chat' => ['id' => $chatId, 'type' => 'private'],
        ]], ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET])->assertOk();
    }

    public function test_auto_reply_can_be_switched_off_for_one_user_only(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        $bot = $this->makeBot();
        $manual = $bot->telegramUsers()->create(['chat_id' => 100]);

        $this->actingAs($bot->user)
            ->postJson(route('chats.auto-reply', [$bot, $manual]), ['auto_reply' => false])
            ->assertOk()
            ->assertJson(['auto_reply' => false]);

        $this->receive($bot, 100, 'hello, human please');
        $this->receive($bot, 200, 'hello bot');

        $this->assertSame(1, TelegramMessage::where('chat_id', 100)->where('direction', 'in')->count());
        $this->assertSame(0, TelegramMessage::where('chat_id', 100)->where('direction', 'out')->count());
        $this->assertSame(1, TelegramMessage::where('chat_id', 200)->where('direction', 'out')->count());
        $this->assertFalse($manual->fresh()->auto_reply);
    }

    public function test_other_company_cannot_toggle_auto_reply(): void
    {
        $bot = $this->makeBot();
        $tgUser = $bot->telegramUsers()->create(['chat_id' => 100]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('chats.auto-reply', [$bot, $tgUser]), ['auto_reply' => false])
            ->assertNotFound();

        $this->assertTrue($tgUser->fresh()->auto_reply);
    }

    public function test_messages_endpoint_returns_only_newer_messages(): void
    {
        $bot = $this->makeBot();
        $tgUser = $bot->telegramUsers()->create(['chat_id' => 300]);
        $old = $tgUser->messages()->create(['bot_id' => $bot->id, 'chat_id' => 300, 'direction' => 'in', 'message' => 'old message']);
        $new = $tgUser->messages()->create(['bot_id' => $bot->id, 'chat_id' => 300, 'direction' => 'in', 'message' => 'brand new message']);

        $response = $this->actingAs($bot->user)
            ->getJson(route('chats.messages', [$bot, $tgUser, 'after' => $old->id]))
            ->assertOk()
            ->assertJson(['last_id' => $new->id, 'new_incoming' => 1, 'auto_reply' => true]);

        $this->assertStringContainsString('brand new message', $response->json('html'));
        $this->assertStringNotContainsString('old message', $response->json('html'));

        $this->actingAs($bot->user)->getJson(route('chats.latest', $bot))->assertJson(['latest_message_id' => $new->id]);
    }

    public function test_ajax_send_returns_json_and_reports_telegram_errors(): void
    {
        $bot = $this->makeBot();
        $tgUser = $bot->telegramUsers()->create(['chat_id' => 400]);

        Http::fake(['api.telegram.org/*' => Http::sequence()
            ->push(['ok' => true, 'result' => ['message_id' => 9]])
            ->push(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'], 403)]);

        $this->actingAs($bot->user)
            ->postJson(route('chats.send', [$bot, $tgUser]), ['message' => 'Hi from a human'])
            ->assertOk()
            ->assertJson(['ok' => true]);
        $this->assertDatabaseHas('telegram_messages', ['chat_id' => 400, 'direction' => 'out', 'message' => 'Hi from a human']);

        $this->actingAs($bot->user)
            ->postJson(route('chats.send', [$bot, $tgUser]), ['message' => 'Are you there?'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Send failed: Forbidden: bot was blocked by the user']);
        $this->assertTrue(TelegramUser::find($tgUser->id)->is_blocked);
    }
}
