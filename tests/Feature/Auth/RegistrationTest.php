<?php

test('public registration is closed', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret-pass',
        'password_confirmation' => 'secret-pass',
    ])->assertNotFound();
});
