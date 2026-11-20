<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_change_its_password_and_log_in_with_it(): void
    {
        $user = User::factory()->create(['username' => 'acme', 'password' => 'oldpass123']);

        $this->actingAs($user)->get(route('password.edit'))->assertOk();

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'oldpass123',
            'password' => 'newpass456',
            'password_confirmation' => 'newpass456',
        ])->assertRedirect(route('password.edit'))->assertSessionHas('success');

        $this->assertTrue(Hash::check('newpass456', $user->fresh()->password));

        $this->post(route('logout'));
        $this->post('/login', ['username' => 'acme', 'password' => 'newpass456'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_superadmin_can_change_own_password(): void
    {
        $admin = User::factory()->superadmin()->create(['password' => 'superadmin99999']);

        $this->actingAs($admin)->put(route('password.update'), [
            'current_password' => 'superadmin99999',
            'password' => 'brandnew789',
            'password_confirmation' => 'brandnew789',
        ])->assertSessionHas('success');

        $this->assertTrue(Hash::check('brandnew789', $admin->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'oldpass123']);

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'not-my-password',
            'password' => 'newpass456',
            'password_confirmation' => 'newpass456',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('oldpass123', $user->fresh()->password));
    }

    public function test_confirmation_must_match_and_new_password_must_differ(): void
    {
        $user = User::factory()->create(['password' => 'oldpass123']);

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'oldpass123',
            'password' => 'newpass456',
            'password_confirmation' => 'typo456',
        ])->assertSessionHasErrors('password');

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'oldpass123',
            'password' => 'oldpass123',
            'password_confirmation' => 'oldpass123',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('oldpass123', $user->fresh()->password));
    }

    public function test_guest_cannot_open_change_password_page(): void
    {
        $this->get(route('password.edit'))->assertRedirect('/login');
    }

    public function test_wrong_current_password_message_is_translated(): void
    {
        $user = User::factory()->create(['password' => 'oldpass123', 'locale' => 'zh_CN']);

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'wrong',
            'password' => 'newpass456',
            'password_confirmation' => 'newpass456',
        ])->assertSessionHasErrors(['current_password' => '当前密码不正确。']);
    }
}
