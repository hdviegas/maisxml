<?php

use App\Models\User;
use Database\Factories\UserFactory;

test('login screen can be rendered', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee(__('Log in'), escape: false);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create([
        'login' => 'fixture-login',
        'email' => 'fixture@example.com',
    ]);

    $response = $this->post('/login', [
        'login' => 'fixture-login',
        'password' => UserFactory::LEGACY_PASSWORD,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
});

test('login accepts email alias for usuario', function () {
    $user = User::factory()->create([
        'login' => 'fixture-login-b',
        'email' => 'fixture-b@example.com',
    ]);

    $this->post('/login', [
        'login' => 'fixture-b@example.com',
        'password' => UserFactory::LEGACY_PASSWORD,
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('users can not authenticate with invalid password', function () {
    User::factory()->create([
        'login' => 'fixture-login-invalid',
        'email' => 'fixture-invalid@example.com',
    ]);

    $response = $this->post('/login', [
        'login' => 'fixture-login-invalid',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('login');
    $this->assertGuest();
});

test('authenticated layout shows documents link', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk()
        ->assertSee(__('Documents'), escape: false);
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('logout'));

    $response->assertRedirect('/');
    $this->assertGuest();
});

test('forced remember flag does not touch the missing remember_token column', function () {
    expect(Schema::hasColumn('usuario', 'remember_token'))->toBeFalse();

    $user = User::factory()->create(['login' => 'fixture-remember', 'email' => 'remember@example.com']);

    expect($user->getRememberTokenName())->toBe('');

    $response = $this->post('/login', [
        'login' => 'fixture-remember',
        'password' => UserFactory::LEGACY_PASSWORD,
        'remember' => '1',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
    expect(collect($response->headers->getCookies())->contains(fn ($c) => str_starts_with($c->getName(), 'remember_web')))->toBeFalse();

    $this->post(route('logout'))->assertRedirect('/');
    $this->assertGuest();
});
