<x-guest-layout>
    <div class="mb-4">
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Log in') }}</h1>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="post" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="login" :value="__('auth.login_username')" />
            <x-text-input id="login" class="mt-1 block w-full" type="text" name="login"
                value="{{ old('login') }}" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('login')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1 block w-full" type="password"
                name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4 block">
            <label for="remember" class="inline-flex items-center">
                <input id="remember" type="checkbox" name="remember" value="1"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:focus:ring-indigo-600" />
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="mt-4 flex items-center justify-end">
            @if (Route::has('password.request'))
                <a class="text-sm text-gray-600 underline dark:text-gray-400"
                    href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
            @endif

            <x-primary-button class="ms-4">{{ __('Log in') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
