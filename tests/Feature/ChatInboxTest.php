<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\TelegramUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatInboxTest extends TestCase
{
    use RefreshDatabase;

    private function makeBot(): Bot
    {
        return User::factory()->create()->bots()->create([
            'token' => '123456789:AAHfakeTokenForTestingOnly_abcdefghijk',
            'telegram_bot_id' => random_int(1, 999_999_999),
            'username' => 'demo_bot',
            'first_name' => 'Demo',
            'webhook_secret' => 'secret',
        ]);
    }

    private function chat(Bot $bot, string $name, int $daysAgo, bool $starred = false): TelegramUser
    {
        return $bot->telegramUsers()->create([
            'chat_id' => random_int(1, 999_999),
            'first_name' => $name,
            'is_starred' => $starred,
            'last_message_at' => now()->subDays($daysAgo),
        ]);
    }

    public function test_tabs_split_starred_recent_and_old_chats(): void
    {
        $bot = $this->makeBot();
        $this->chat($bot, 'RecentRita', 1);
        $this->chat($bot, 'OldOscar', 20);
        $this->chat($bot, 'StarSam', 30, starred: true);

        $this->actingAs($bot->user);

        $this->get(route('chats.index', [$bot, 'tab' => 'starred']))
            ->assertOk()->assertSee('StarSam')->assertDontSee('RecentRita')->assertDontSee('OldOscar');

        $this->get(route('chats.index', $bot))
            ->assertOk()->assertSee('RecentRita')->assertDontSee('OldOscar')->assertDontSee('StarSam');

        $this->get(route('chats.index', [$bot, 'tab' => 'old']))
            ->assertOk()->assertSee('OldOscar')->assertSee('StarSam')->assertDontSee('RecentRita');

        // "Old" threshold can be changed: with 30 days, Oscar (20 days ago) is recent again.
        $this->get(route('chats.index', [$bot, 'tab' => 'recent', 'days' => 30]))
            ->assertOk()->assertSee('OldOscar')->assertSee('RecentRita');

        $this->get(route('chats.index', [$bot, 'tab' => 'all']))
            ->assertOk()->assertSee('RecentRita')->assertSee('OldOscar')->assertSee('StarSam');
    }

    public function test_chat_can_be_starred_and_unstarred(): void
    {
        $bot = $this->makeBot();
        $tgUser = $this->chat($bot, 'Ali', 0);

        $this->actingAs($bot->user)->postJson(route('chats.star', [$bot, $tgUser]), ['is_starred' => true])
            ->assertOk()->assertJson(['is_starred' => true]);
        $this->assertTrue($tgUser->fresh()->is_starred);

        $this->actingAs($bot->user)->post(route('chats.star', [$bot, $tgUser]))->assertSessionHas('success');
        $this->assertFalse($tgUser->fresh()->is_starred);
    }

    public function test_other_company_cannot_star_a_chat(): void
    {
        $bot = $this->makeBot();
        $tgUser = $this->chat($bot, 'Ali', 0);

        $this->actingAs(User::factory()->create())->postJson(route('chats.star', [$bot, $tgUser]))->assertNotFound();
        $this->assertFalse($tgUser->fresh()->is_starred);
    }

    public function test_unread_count_shows_until_the_chat_is_opened(): void
    {
        $bot = $this->makeBot();
        $tgUser = $this->chat($bot, 'Ali', 0);
        foreach (['hi', 'are you there?'] as $text) {
            $tgUser->messages()->create(['bot_id' => $bot->id, 'chat_id' => $tgUser->chat_id, 'direction' => 'in', 'message' => $text]);
        }
        $tgUser->messages()->create(['bot_id' => $bot->id, 'chat_id' => $tgUser->chat_id, 'direction' => 'out', 'message' => 'bot answer']);

        $this->actingAs($bot->user)->get(route('chats.index', $bot))->assertSee('2 new');

        $this->actingAs($bot->user)->get(route('chats.show', [$bot, $tgUser]))->assertOk();

        $this->actingAs($bot->user)->get(route('chats.index', $bot))->assertDontSee('2 new');
    }
}
