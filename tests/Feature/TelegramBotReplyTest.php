<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\TelegramMessage;
use App\Models\TelegramUser;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramBotReplyTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '123456789:AAHfakeTokenForTestingOnly_abcdefghijk';

    private function fakeTelegram(): void
    {
        Http::fake([
            'api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 123456789, 'is_bot' => true, 'first_name' => 'Demo', 'username' => 'demo_bot']]),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 99]]),
        ]);
    }

    public function test_superadmin_seeded_and_can_login_with_username(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post('/login', ['username' => 'superadmin', 'password' => 'superadmin99999'])
            ->assertRedirect(route('home'));
        $this->get('/')->assertRedirect(route('admin.companies.index'));
        $this->get(route('admin.companies.index'))->assertOk();
    }

    public function test_wrong_password_rejected(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->post('/login', ['username' => 'superadmin', 'password' => 'nope'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_superadmin_creates_company_and_company_cannot_access_admin(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->post(route('admin.companies.store'), [
            'name' => 'Acme', 'username' => 'acme', 'password' => 'secret123', 'bot_limit' => 2, 'is_active' => 1,
        ])->assertRedirect(route('admin.companies.index'));

        $company = User::where('username', 'acme')->firstOrFail();
        $this->assertSame(2, $company->bot_limit);
        $this->assertSame('company', $company->role);

        $this->actingAs($company)->get(route('admin.companies.index'))->assertForbidden();
        $this->actingAs($company)->get(route('bots.index'))->assertOk();
    }

    public function test_company_connects_bot_and_limit_is_enforced(): void
    {
        $this->fakeTelegram();
        $company = User::factory()->create(['bot_limit' => 1]);

        $this->actingAs($company)->post(route('bots.store'), ['token' => self::TOKEN])->assertRedirect();
        $bot = Bot::firstOrFail();
        $this->assertSame('demo_bot', $bot->username);
        $this->assertSame(self::TOKEN, $bot->token);
        $this->assertCount(1, $bot->replies);

        // second bot exceeds limit of 1
        $this->actingAs($company)->post(route('bots.store'), ['token' => '987654321:AAHanotherFakeTokenForTesting_abcdefg'])
            ->assertSessionHasErrors('token');
        $this->assertSame(1, Bot::count());

        // other company cannot see the bot
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('bots.show', $bot))->assertNotFound();
    }

    private function makeBot(): Bot
    {
        $company = User::factory()->create();

        return $company->bots()->create([
            'token' => self::TOKEN, 'telegram_bot_id' => 123456789, 'username' => 'demo_bot',
            'first_name' => 'Demo', 'webhook_secret' => 'secret-abc',
        ]);
    }

    public function test_webhook_saves_chat_and_message_and_sends_start_reply_with_buttons(): void
    {
        $this->fakeTelegram();
        $bot = $this->makeBot();
        $bot->replies()->create([
            'trigger' => '/start', 'message' => 'Welcome!',
            'buttons' => [['text' => 'Site', 'type' => 'url', 'value' => 'https://example.com']],
        ]);

        $update = ['update_id' => 1, 'message' => [
            'message_id' => 5, 'text' => '/start ref123',
            'from' => ['id' => 555, 'first_name' => 'Ali', 'username' => 'ali', 'language_code' => 'en'],
            'chat' => ['id' => 555, 'type' => 'private', 'first_name' => 'Ali'],
        ]];

        // wrong secret is rejected
        $this->postJson("/telegram/webhook/{$bot->id}", $update, ['X-Telegram-Bot-Api-Secret-Token' => 'bad'])->assertForbidden();

        $this->postJson("/telegram/webhook/{$bot->id}", $update, ['X-Telegram-Bot-Api-Secret-Token' => 'secret-abc'])->assertOk();

        $tgUser = TelegramUser::firstOrFail();
        $this->assertSame(555, (int) $tgUser->chat_id);
        $this->assertSame('ali', $tgUser->username);
        $this->assertSame($bot->id, $tgUser->bot_id);

        $this->assertDatabaseHas('telegram_messages', ['bot_id' => $bot->id, 'chat_id' => 555, 'direction' => 'in', 'message' => '/start ref123']);
        $this->assertDatabaseHas('telegram_messages', ['bot_id' => $bot->id, 'chat_id' => 555, 'direction' => 'out', 'message' => 'Welcome!']);

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/sendMessage')
            && $r['chat_id'] == 555
            && $r['text'] === 'Welcome!'
            && json_decode($r['reply_markup'], true)['inline_keyboard'][0][0]['url'] === 'https://example.com');
    }

    public function test_keyword_and_fallback_and_callback_button(): void
    {
        $this->fakeTelegram();
        $bot = $this->makeBot();
        $bot->replies()->create(['trigger' => 'price', 'message' => 'Price is 10']);
        $bot->replies()->create(['trigger' => '*', 'message' => 'Sorry, I did not understand']);

        $send = fn (array $u) => $this->postJson("/telegram/webhook/{$bot->id}", $u, ['X-Telegram-Bot-Api-Secret-Token' => 'secret-abc'])->assertOk();
        $from = ['id' => 777, 'first_name' => 'Bob'];
        $chat = ['id' => 777, 'type' => 'private'];

        $send(['update_id' => 2, 'message' => ['message_id' => 1, 'text' => 'PRICE', 'from' => $from, 'chat' => $chat]]);
        $send(['update_id' => 3, 'message' => ['message_id' => 2, 'text' => 'hello', 'from' => $from, 'chat' => $chat]]);
        $send(['update_id' => 4, 'callback_query' => ['id' => 'cb1', 'data' => 'price', 'from' => $from, 'message' => ['chat' => $chat]]]);

        $out = TelegramMessage::where('direction', 'out')->orderBy('id')->pluck('message')->all();
        $this->assertSame(['Price is 10', 'Sorry, I did not understand', 'Price is 10'], $out);
        $this->assertSame(1, TelegramUser::count());
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/answerCallbackQuery'));
    }

    public function test_paused_bot_saves_message_without_replying(): void
    {
        $this->fakeTelegram();
        $bot = $this->makeBot();
        $bot->update(['is_active' => false]);
        $bot->replies()->create(['trigger' => '*', 'message' => 'x']);

        $this->postJson("/telegram/webhook/{$bot->id}", ['update_id' => 1, 'message' => [
            'message_id' => 1, 'text' => 'hi', 'from' => ['id' => 1], 'chat' => ['id' => 1, 'type' => 'private'],
        ]], ['X-Telegram-Bot-Api-Secret-Token' => 'secret-abc'])->assertOk();

        $this->assertSame(1, TelegramMessage::where('direction', 'in')->count());
        $this->assertSame(0, TelegramMessage::where('direction', 'out')->count());
    }

    public function test_company_sends_message_back_to_telegram_user(): void
    {
        $this->fakeTelegram();
        $bot = $this->makeBot();
        $tgUser = $bot->telegramUsers()->create(['chat_id' => 4242, 'first_name' => 'Cara']);

        $this->actingAs($bot->user)->get(route('chats.show', [$bot, $tgUser]))->assertOk()->assertSee('4242');

        $this->actingAs($bot->user)->post(route('chats.send', [$bot, $tgUser]), [
            'message' => 'Hi Cara',
            'buttons' => [['text' => 'More', 'type' => 'callback', 'value' => 'price']],
        ])->assertSessionHas('success');

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/sendMessage') && $r['chat_id'] == 4242 && $r['text'] === 'Hi Cara');
        $this->assertDatabaseHas('telegram_messages', ['chat_id' => 4242, 'direction' => 'out', 'message' => 'Hi Cara']);

        // pages render
        $this->actingAs($bot->user)->get(route('chats.index', $bot))->assertOk()->assertSee('Cara');
        $this->actingAs($bot->user)->get(route('bots.show', $bot))->assertOk();
        $this->actingAs($bot->user)->get(route('bots.replies.create', $bot))->assertOk();
        $this->actingAs($bot->user)->get(route('chats.broadcast', $bot))->assertOk();
    }

    public function test_reply_with_image_upload(): void
    {
        $this->fakeTelegram();
        \Illuminate\Support\Facades\Storage::fake('public');
        $bot = $this->makeBot();

        $this->actingAs($bot->user)->post(route('bots.replies.store', $bot), [
            'trigger' => '/start', 'message' => 'Look', 'is_active' => 1,
            'image' => \Illuminate\Http\UploadedFile::fake()->image('promo.jpg'),
        ])->assertRedirect(route('bots.show', $bot));

        $reply = $bot->replies()->firstOrFail();
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($reply->image_path);

        $this->postJson("/telegram/webhook/{$bot->id}", ['update_id' => 1, 'message' => [
            'message_id' => 1, 'text' => '/start', 'from' => ['id' => 9], 'chat' => ['id' => 9, 'type' => 'private'],
        ]], ['X-Telegram-Bot-Api-Secret-Token' => 'secret-abc'])->assertOk();

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/sendPhoto'));
    }
}
