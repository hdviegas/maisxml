<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;

test('registration screen can be rendered', function () {
    $this->get('/register')->assertOk();
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => UserFactory::LEGACY_PASSWORD,
        'password_confirmation' => UserFactory::LEGACY_PASSWORD,
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();
    $created = User::query()->where('email', 'test@example.com')->firstOrFail();
    expect(md5(UserFactory::LEGACY_PASSWORD))->toBe((string) $created->senha)
        ->and($created->login)->toBe('test@example.com');
});
