<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile');
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique($user->getTable(), 'email')->ignore($user->id)],
            'login' => ['required', 'string', 'max:64', Rule::unique($user->getTable(), 'login')->ignore($user->id)],
        ]);

        $user->social = $validated['name'];
        $user->email = $validated['email'];
        $user->login = $validated['login'];

        $user->save();

        return redirect()->route('profile')->with('status', __('Profile updated.'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        if (md5((string) $validated['current_password']) !== (string) $user->senha) {
            return back()->withErrors(['current_password' => __('auth.password')]);
        }

        $user->senha = md5((string) $validated['password']);
        $user->save();

        return redirect()->route('profile')->with('password_status', __('Saved.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        /** @var User $user */
        $user = $request->user();

        if (md5((string) $request->input('password')) !== (string) $user->senha) {
            return back()->withErrors(['password' => __('auth.password')]);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
