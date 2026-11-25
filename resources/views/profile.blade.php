<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900 dark:border-green-800 dark:bg-green-950/60 dark:text-green-100">
                    {{ session('status') }}
                </div>
            @endif
            @if (session('password_status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900 dark:border-green-800 dark:bg-green-950/60 dark:text-green-100">
                    {{ session('password_status') }}
                </div>
            @endif

            {{-- Informações do perfil --}}
            <div class="p-4 shadow sm:p-8 sm:rounded-lg dark:bg-gray-800 bg-white">
                <section class="max-w-xl space-y-6">
                    <header>
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Profile Information') }}</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __("Update your account's profile information and email address.") }}</p>
                    </header>

                    <form method="post" action="{{ route('profile.update') }}" class="space-y-6">
                        @csrf
                        @method('patch')

                        <div>
                            <x-input-label for="name" :value="__('Name')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', auth()->user()->name)" required
                                autofocus autocomplete="name" />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <div>
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                                :value="old('email', auth()->user()->email)" required autocomplete="username" />
                            <x-input-error class="mt-2" :messages="$errors->get('email')" />
                        </div>

                        <div>
                            <x-input-label for="login" value="{{ __('Login') }}" />
                            <x-text-input id="login" name="login" type="text" class="mt-1 block w-full"
                                :value="old('login', auth()->user()->login)" required autocomplete="username" />
                            <x-input-error class="mt-2" :messages="$errors->get('login')" />
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Nome de usuário legado (`usuario.login`).') }}</p>
                        </div>

                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </form>
                </section>
            </div>

            {{-- Alterar senha --}}
            <div class="p-4 shadow sm:p-8 sm:rounded-lg dark:bg-gray-800 bg-white">
                <section class="max-w-xl space-y-6">
                    <header>
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Update Password') }}</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('Ensure your account is using a long, random password to stay secure.') }}</p>
                    </header>

                    <form method="post" action="{{ route('profile.password.update') }}" class="space-y-6">
                        @csrf
                        @method('put')

                        <div>
                            <x-input-label for="current_password" :value="__('Current Password')" />
                            <x-text-input id="current_password" name="current_password" type="password" class="mt-1 block w-full"
                                autocomplete="current-password" />
                            <x-input-error class="mt-2" :messages="$errors->get('current_password')" />
                        </div>

                        <div>
                            <x-input-label for="password" :value="__('New Password')" />
                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full"
                                autocomplete="new-password" />
                            <x-input-error class="mt-2" :messages="$errors->get('password')" />
                        </div>

                        <div>
                            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                                class="mt-1 block w-full" autocomplete="new-password" />
                            <x-input-error class="mt-2" :messages="$errors->get('password_confirmation')" />
                        </div>

                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </form>
                </section>
            </div>

            {{-- Excluir conta --}}
            <div class="p-4 shadow sm:p-8 sm:rounded-lg dark:bg-gray-800 bg-white">
                <section class="max-w-xl space-y-6">
                    <header>
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Delete Account') }}</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
                        </p>
                    </header>

                    <x-danger-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
                        {{ __('Delete Account') }}
                    </x-danger-button>

                    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
                        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
                            @csrf
                            @method('delete')

                            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Are you sure you want to delete your account?') }}</h2>

                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}</p>

                            <div class="mt-6">
                                <x-input-label for="delete-password" value="{{ __('Password') }}" class="sr-only" />
                                <x-text-input id="delete-password" name="password" type="password" class="mt-1 block w-3/4"
                                    placeholder="{{ __('Password') }}" autocomplete="current-password" />
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>

                            <div class="mt-6 flex justify-end">
                                <x-secondary-button x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-secondary-button>

                                <x-danger-button class="ms-3">
                                    {{ __('Delete Account') }}
                                </x-danger-button>
                            </div>
                        </form>
                    </x-modal>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
