<?php

it('redirects guests from home to login', function () {
    $this->get('/')
        ->assertRedirect(route('login', absolute: false));
});
