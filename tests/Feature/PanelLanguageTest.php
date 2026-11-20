<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_can_be_switched_to_chinese_and_the_choice_is_kept_after_login(): void
    {
        $user = User::factory()->create(['username' => 'acme', 'password' => 'secret123']);

        $this->post(route('locale.update'), ['locale' => 'zh_CN'])->assertRedirect();
        $this->get(route('login'))->assertOk()->assertSee('登录')->assertSee('用户名');

        $this->post('/login', ['username' => 'acme', 'password' => 'secret123'])->assertRedirect();

        $this->assertSame('zh_CN', $user->fresh()->locale);
        $this->get(route('bots.index'))->assertOk()->assertSee('我的机器人');
    }

    public function test_logged_in_user_language_is_saved_on_the_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('locale.update'), ['locale' => 'ms'])->assertRedirect();

        $this->assertSame('ms', $user->fresh()->locale);
        $this->actingAs($user->fresh())->get(route('bots.index'))->assertOk()->assertSee('Bot Saya')->assertSee('Sambungkan bot Telegram');
    }

    public function test_unknown_language_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('locale.update'), ['locale' => 'xx'])->assertSessionHasErrors('locale');
        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_validation_and_flash_messages_use_the_panel_language(): void
    {
        $admin = User::factory()->superadmin()->create(['locale' => 'ms']);

        $this->actingAs($admin)->post(route('admin.companies.store'), ['username' => 'x'])
            ->assertSessionHasErrors(['name' => 'Ruangan nama diperlukan.']);

        $this->actingAs($admin)->post(route('admin.companies.store'), [
            'name' => 'Kedai Ali', 'username' => 'kedaiali', 'password' => 'secret123', 'bot_limit' => 1, 'is_active' => 1, 'locale' => 'zh_CN',
        ])->assertSessionHas('success', 'Syarikat dicipta.');

        $this->assertSame('zh_CN', User::where('username', 'kedaiali')->value('locale'));
    }
}
