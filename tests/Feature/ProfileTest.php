<?php

use App\Models\User;
use Database\Factories\UserFactory;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/profile');

    $response->assertOk()
        ->assertSee(__('Profile'), escape: false)
        ->assertSee(__('Profile Information'), escape: false)
        ->assertSee(__('Delete Account'), escape: false);
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->from(route('profile'))->patch(route('profile.update'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'login' => 'usuario_login',
    ])->assertSessionHasNoErrors()->assertRedirect(route('profile'));

    $user->refresh();

    expect($user->social)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com')
        ->and($user->login)->toBe('usuario_login');
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->from(route('profile'))->delete(route('profile.destroy'), [
        'password' => UserFactory::LEGACY_PASSWORD,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect('/');
    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->from(route('profile'))->delete(route('profile.destroy'), [
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});
