<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class HomeRedirectController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return auth()->check()
            ? redirect()->route('dashboard')
            : redirect()->route('login');
    }
}
