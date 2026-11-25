<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->from(route('profile'))->put(route('profile.password.update'), [
        'current_password' => UserFactory::LEGACY_PASSWORD,
        'password' => 'new-password-value',
        'password_confirmation' => 'new-password-value',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('profile'));

    expect(md5('new-password-value'))->toBe((string) $user->fresh()->senha);
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->from(route('profile'))->put(route('profile.password.update'), [
        'current_password' => 'wrong-password',
        'password' => 'new-password-value',
        'password_confirmation' => 'new-password-value',
    ]);

    $response->assertSessionHasErrors(['current_password'])->assertRedirect(route('profile'));
});
