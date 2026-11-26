<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [], [
            'login' => __('auth.login_username'),
            'password' => __('Password'),
        ]);

        $this->ensureIsNotRateLimited($request);

        $login = trim($request->string('login')->toString());
        $plain = $request->string('password')->toString();

        $normalizedEmail = Str::lower($login);

        $user = User::query()
            ->where('login', $login)
            ->orWhereRaw('LOWER(COALESCE(email, \'\')) = ?', [$normalizedEmail])
            ->first();

        if ($user !== null && (string) $user->senha === md5($plain)) {
            RateLimiter::clear($this->throttleKey($request));
            // Never "remember": legacy `usuario` has no remember_token column.
            Auth::login($user, false);

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard', absolute: false));
        }

        RateLimiter::hit($this->throttleKey($request));

        throw ValidationException::withMessages([
            'login' => trans('auth.failed'),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(Request $request): string
    {
        $loginKey = trim($request->string('login')->toString());

        return Str::transliterate(Str::lower($loginKey).'|'.$request->ip());
    }
}
