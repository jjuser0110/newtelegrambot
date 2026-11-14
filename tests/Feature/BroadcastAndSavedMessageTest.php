<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\Broadcast;
use App\Models\SavedMessage;
use App\Models\TelegramUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BroadcastAndSavedMessageTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-abc';

    /**
     * Fake Telegram: every call succeeds, except chats listed in $blockedChats (403) and $rateLimitedOnce (429 once).
     *
     * @param  array<int, int>  $blockedChats
     * @param  array<int, int>  $rateLimitedOnce
     */
    private function fakeTelegram(array $blockedChats = [], array $rateLimitedOnce = []): void
    {
        $limited = array_flip($rateLimitedOnce);

        Http::fake(function (HttpRequest $request) use ($blockedChats, &$limited) {
            $chatId = (int) ($request['chat_id'] ?? 0);

            if (in_array($chatId, $blockedChats, true)) {
                return Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'], 403);
            }
            if (isset($limited[$chatId])) {
                unset($limited[$chatId]);

                return Http::response(['ok' => false, 'error_code' => 429, 'description' => 'Too Many Requests: retry after 0', 'parameters' => ['retry_after' => 0]], 429);
            }

            return Http::response(['ok' => true, 'result' => [
                'message_id' => 500 + $chatId,
                'photo' => [['file_id' => 'small'], ['file_id' => 'FILE_ID_BIG']],
            ]]);
        });
    }

    private function makeBot(?User $owner = null): Bot
    {
        $owner ??= User::factory()->create();

        return $owner->bots()->create([
            'token' => '123456789:AAHfakeTokenForTestingOnly_abcdefghijk',
            'telegram_bot_id' => random_int(1, 999_999_999),
            'username' => 'demo_bot',
            'first_name' => 'Demo',
            'webhook_secret' => self::SECRET,
        ]);
    }

    /**
     * @return array<int, TelegramUser>
     */
    private function makeTelegramUsers(Bot $bot, array $chatIds): array
    {
        return array_map(fn (int $chatId) => $bot->telegramUsers()->create(['chat_id' => $chatId, 'first_name' => 'U'.$chatId]), $chatIds);
    }

    private function sentTo(string $method): array
    {
        return Http::recorded()
            ->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/'.$method))
            ->map(fn ($pair) => (int) $pair[0]['chat_id'])
            ->values()
            ->all();
    }

    public function test_superadmin_can_view_every_company_bot_but_cannot_connect_bots(): void
    {
        $this->fakeTelegram();
        $admin = User::factory()->superadmin()->create();
        $bot = $this->makeBot();
        [$tgUser] = $this->makeTelegramUsers($bot, [11]);

        $this->actingAs($admin)->get(route('bots.index'))->assertOk()->assertSee($bot->user->name);
        $this->actingAs($admin)->get(route('bots.show', $bot))->assertOk();
        $this->actingAs($admin)->get(route('chats.show', [$bot, $tgUser]))->assertOk();
        $this->actingAs($admin)->get(route('broadcasts.index', $bot))->assertOk();
        $this->actingAs($admin)->get(route('bots.saved-messages.index', $bot))->assertOk();

        $this->actingAs($admin)->post(route('bots.store'), ['token' => '123456789:AAHfakeTokenForTestingOnly_abcdefghijk'])->assertForbidden();
    }

    public function test_company_cannot_see_another_company_broadcast(): void
    {
        $bot = $this->makeBot();
        $broadcast = Broadcast::factory()->for($bot)->create();
        $otherCompany = User::factory()->create();

        $this->actingAs($otherCompany)->get(route('broadcasts.show', [$bot, $broadcast]))->assertNotFound();
    }

    public function test_received_premium_message_can_be_saved_and_copied_or_forwarded(): void
    {
        $this->fakeTelegram();
        $bot = $this->makeBot();

        $this->postJson("/telegram/webhook/{$bot->id}", ['update_id' => 1, 'message' => [
            'message_id' => 77, 'text' => 'Big sale 🔥',
            'entities' => [['type' => 'custom_emoji', 'offset' => 9, 'length' => 2, 'custom_emoji_id' => '5368324170671202286']],
            'from' => ['id' => 900, 'first_name' => 'Boss'], 'chat' => ['id' => 900, 'type' => 'private'],
        ]], ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET])->assertOk();

        $incoming = $bot->messages()->where('direction', 'in')->firstOrFail();

        $this->actingAs($bot->user)->post(route('bots.saved-messages.store', $bot), [
            'telegram_message_id' => $incoming->id, 'label' => 'Promo',
        ])->assertSessionHas('success');

        $saved = SavedMessage::firstOrFail();
        $this->assertSame(900, (int) $saved->from_chat_id);
        $this->assertSame(77, (int) $saved->message_id);
        $this->assertTrue($saved->has_custom_emoji);

        [$customer] = $this->makeTelegramUsers($bot, [321]);

        $this->actingAs($bot->user)->post(route('chats.send', [$bot, $customer]), [
            'saved_message_id' => $saved->id, 'send_mode' => 'copy',
        ])->assertSessionHas('success');
        $this->actingAs($bot->user)->post(route('chats.send', [$bot, $customer]), [
            'saved_message_id' => $saved->id, 'send_mode' => 'forward',
        ])->assertSessionHas('success');

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/copyMessage')
            && $r['chat_id'] == 321 && $r['from_chat_id'] == 900 && $r['message_id'] == 77);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/forwardMessage')
            && $r['chat_id'] == 321 && $r['from_chat_id'] == 900 && $r['message_id'] == 77);
    }

    public function test_saved_message_from_another_bot_is_rejected(): void
    {
        $bot = $this->makeBot();
        $otherBot = $this->makeBot();
        $foreign = $otherBot->savedMessages()->create(['label' => 'x', 'from_chat_id' => 1, 'message_id' => 1]);
        [$customer] = $this->makeTelegramUsers($bot, [5]);

        $this->actingAs($bot->user)->post(route('chats.send', [$bot, $customer]), ['saved_message_id' => $foreign->id])
            ->assertSessionHasErrors('saved_message_id');
    }

    public function test_auto_reply_can_copy_a_saved_message(): void
    {
        $this->fakeTelegram();
        $bot = $this->makeBot();
        $saved = $bot->savedMessages()->create(['label' => 'Welcome', 'from_chat_id' => 900, 'message_id' => 77]);
        $bot->replies()->create(['trigger' => '/start', 'saved_message_id' => $saved->id, 'send_mode' => 'copy']);

        $this->postJson("/telegram/webhook/{$bot->id}", ['update_id' => 1, 'message' => [
            'message_id' => 1, 'text' => '/start', 'from' => ['id' => 44], 'chat' => ['id' => 44, 'type' => 'private'],
        ]], ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET])->assertOk();

        $this->assertSame([44], $this->sentTo('copyMessage'));
    }

    public function test_broadcast_sends_in_batches_and_records_each_recipient(): void
    {
        $this->fakeTelegram(blockedChats: [102]);
        $bot = $this->makeBot();
        $this->makeTelegramUsers($bot, [101, 102, 103]);
        $bot->telegramUsers()->create(['chat_id' => 199, 'is_blocked' => true]);

        $this->actingAs($bot->user)->post(route('broadcasts.store', $bot), [
            'message' => 'Hello all', 'batch_size' => 2, 'batch_delay_minutes' => 10,
        ])->assertRedirect();

        $broadcast = Broadcast::firstOrFail();
        $this->assertSame(Broadcast::STATUS_RUNNING, $broadcast->status);
        $this->assertSame(3, $broadcast->total);
        $this->assertSame(1, $broadcast->sent_count);
        $this->assertSame(1, $broadcast->failed_count);
        $this->assertSame([101, 102], $this->sentTo('sendMessage'));
        $this->assertTrue(TelegramUser::where('chat_id', 102)->value('is_blocked'));
        $this->assertDatabaseHas('broadcast_recipients', ['chat_id' => 101, 'status' => 'sent', 'telegram_message_id' => 601]);
        $this->assertDatabaseHas('broadcast_recipients', ['chat_id' => 102, 'status' => 'failed']);

        // During the pause nothing more is sent.
        $this->artisan('broadcasts:dispatch-due')->assertSuccessful();
        $this->assertSame([101, 102], $this->sentTo('sendMessage'));

        $this->travel(11)->minutes();
        $this->artisan('broadcasts:dispatch-due')->assertSuccessful();

        $broadcast->refresh();
        $this->assertSame(Broadcast::STATUS_COMPLETED, $broadcast->status);
        $this->assertSame(2, $broadcast->sent_count);
        $this->assertSame([101, 102, 103], $this->sentTo('sendMessage'));
        $this->assertNotNull($broadcast->recipients()->where('chat_id', 103)->value('sent_at'));

        $this->actingAs($bot->user)->getJson(route('broadcasts.progress', [$bot, $broadcast]))
            ->assertOk()
            ->assertJson(['status' => 'completed', 'total' => 3, 'sent' => 2, 'failed' => 1, 'percent' => 100, 'finished' => true]);
        $this->actingAs($bot->user)->get(route('broadcasts.show', [$bot, $broadcast, 'status' => 'failed']))
            ->assertOk()->assertSee('102')->assertSee('blocked by the user');
    }

    public function test_scheduled_broadcast_waits_until_its_time(): void
    {
        $this->fakeTelegram();
        $bot = $this->makeBot();
        $this->makeTelegramUsers($bot, [201]);

        $this->actingAs($bot->user)->post(route('broadcasts.store', $bot), [
            'message' => 'Later', 'scheduled_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'batch_size' => 1000, 'batch_delay_minutes' => 0,
        ])->assertRedirect();

        $this->artisan('broadcasts:dispatch-due');
        $this->assertSame(Broadcast::STATUS_SCHEDULED, Broadcast::firstOrFail()->status);
        Http::assertNothingSent();

        $this->travel(61)->minutes();
        $this->artisan('broadcasts:dispatch-due');

        $this->assertSame(Broadcast::STATUS_COMPLETED, Broadcast::firstOrFail()->status);
        $this->assertSame([201], $this->sentTo('sendMessage'));
    }

    public function test_rate_limited_message_is_retried(): void
    {
        $this->fakeTelegram(rateLimitedOnce: [301]);
        $bot = $this->makeBot();
        $this->makeTelegramUsers($bot, [301]);

        $this->actingAs($bot->user)->post(route('broadcasts.store', $bot), [
            'message' => 'Hi', 'batch_size' => 1000, 'batch_delay_minutes' => 0,
        ]);

        $broadcast = Broadcast::firstOrFail();
        $this->assertSame(1, $broadcast->sent_count);
        $this->assertSame(0, $broadcast->failed_count);
    }

    public function test_broadcast_image_is_uploaded_once_then_reused(): void
    {
        $this->fakeTelegram();
        Storage::fake('public');
        $bot = $this->makeBot();
        $this->makeTelegramUsers($bot, [401, 402, 403]);

        $this->actingAs($bot->user)->post(route('broadcasts.store', $bot), [
            'message' => 'Pic', 'image' => UploadedFile::fake()->image('promo.jpg'),
            'batch_size' => 1000, 'batch_delay_minutes' => 0,
        ]);

        $photoValues = Http::recorded()
            ->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/sendPhoto'))
            ->map(fn ($pair) => $pair[0]->isMultipart() ? 'upload' : $pair[0]['photo'])
            ->values()->all();

        $this->assertSame(['upload', 'FILE_ID_BIG', 'FILE_ID_BIG'], $photoValues);
    }

    public function test_cancelled_broadcast_stops_sending(): void
    {
        $this->fakeTelegram();
        $bot = $this->makeBot();
        $this->makeTelegramUsers($bot, [501, 502]);

        $this->actingAs($bot->user)->post(route('broadcasts.store', $bot), [
            'message' => 'Hi', 'batch_size' => 1, 'batch_delay_minutes' => 5,
        ]);
        $broadcast = Broadcast::firstOrFail();

        $this->actingAs($bot->user)->post(route('broadcasts.cancel', [$bot, $broadcast]))->assertSessionHas('success');
        $this->travel(10)->minutes();
        $this->artisan('broadcasts:dispatch-due');

        $this->assertSame(Broadcast::STATUS_CANCELLED, $broadcast->fresh()->status);
        $this->assertSame([501], $this->sentTo('sendMessage'));
    }
}
