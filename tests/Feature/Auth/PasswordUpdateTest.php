<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $settings = route('account.settings', ['tab' => 'security']);

        $response = $this
            ->actingAs($user)
            ->from($settings)
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'New-Password42!',
                'password_confirmation' => 'New-Password42!',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect($settings);

        $this->assertTrue(Hash::check('New-Password42!', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $settings = route('account.settings', ['tab' => 'security']);

        $response = $this
            ->actingAs($user)
            ->from($settings)
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'New-Password42!',
                'password_confirmation' => 'New-Password42!',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect($settings);

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_previous_weak_password_fixture_is_rejected_under_v25_policy(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $settings = route('account.settings', ['tab' => 'security']);

        $this->actingAs($user)->from($settings)->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('password')->assertRedirect($settings);

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }
}
