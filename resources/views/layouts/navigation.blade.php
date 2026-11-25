@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Organization> $navOrganizations */
    $navOrganizations = \App\Models\Organization::query()->forUser(auth()->user())->orderBy('social')->get();
    $activeOrgIdSession = session('active_organization_id');
@endphp

<nav x-data="{ open: false }" class="sticky top-0 z-30 border-b border-slate-200/90 bg-white/85 shadow-nav backdrop-blur-md">
    <div class="mx-page flex min-h-[4rem] items-center justify-between gap-4">
        <div class="flex min-w-0 flex-1 items-center gap-8">
            <div class="flex shrink-0 items-center">
                <a href="{{ route('dashboard') }}" class="rounded-xl outline-none ring-brand-500/0 transition hover:ring-2 focus-visible:ring-2">
                    <x-application-logo class="block" />
                </a>
            </div>

            <div class="hidden min-w-0 items-center gap-1 lg:flex">
                <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    {{ __('Dashboard') }}
                </x-nav-link>
                <x-nav-link :href="route('documents.index')" :active="request()->routeIs('documents.index')">
                    {{ __('Documents') }}
                </x-nav-link>
                <x-nav-link :href="route('import.index')" :active="request()->routeIs('import.*')">
                    {{ __('Import') }}
                </x-nav-link>
                <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.index')">
                    {{ __('Reports') }}
                </x-nav-link>
                <x-nav-link :href="route('sped.index')" :active="request()->routeIs('sped.*')">
                    {{ __('SPED') }}
                </x-nav-link>
                <x-nav-link :href="route('manifestacao.index')" :active="request()->routeIs('manifestacao.index')">
                    {{ __('Manifestação') }}
                </x-nav-link>
                <x-nav-link :href="route('settings.index')" :active="request()->routeIs('settings.index')">
                    {{ __('Settings') }}
                </x-nav-link>
            </div>
        </div>

        <div class="hidden shrink-0 items-center gap-3 xl:flex">
            <div class="w-[14rem]">
                <form method="post" action="{{ route('scope.organization') }}" class="m-0 p-0">
                    @csrf
                    <label for="active-org" class="sr-only">{{ __('scope.org_label') }}</label>
                    <select id="active-org" name="organization_id"
                        onchange="this.form.submit()"
                        class="mx-select py-2 text-xs font-semibold text-slate-700">
                        <option value="">{{ __('scope.all_orgs') }}</option>
                        @foreach ($navOrganizations as $o)
                            <option value="{{ $o->id }}" @selected((int) $activeOrgIdSession === (int) $o->id)>{{ $o->name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        <div class="hidden shrink-0 items-center sm:flex">
            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500/25">
                        <div class="max-w-[12rem] truncate">{{ auth()->user()->name }}</div>
                        <div class="ms-1">
                            <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('profile')">
                        {{ __('Profile') }}
                    </x-dropdown-link>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="w-full px-4 py-2 text-start text-sm font-medium leading-5 text-slate-700 hover:bg-slate-50">
                            {{ __('Log Out') }}
                        </button>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>

        <div class="-me-2 flex items-center sm:hidden">
            <button type="button" @click="open = !open"
                class="inline-flex items-center justify-center rounded-md p-2 text-slate-500 transition duration-150 ease-in-out hover:bg-slate-100 hover:text-slate-700 focus:bg-slate-100 focus:text-slate-700 focus:outline-none">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{ 'hidden': open, 'inline-flex': !open }" class="inline-flex" stroke-linecap="round"
                        stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path :class="{ 'hidden': !open, 'inline-flex': open }" class="hidden" stroke-linecap="round"
                        stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <div :class="{ 'block': open, 'hidden': !open }" class="hidden border-t border-slate-200 bg-white sm:hidden">
        <div class="space-y-1 pb-3 pt-2">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('documents.index')" :active="request()->routeIs('documents.index')">
                {{ __('Documents') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('import.index')" :active="request()->routeIs('import.*')">
                {{ __('Import') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.index')">
                {{ __('Reports') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('sped.index')" :active="request()->routeIs('sped.*')">
                {{ __('SPED') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('manifestacao.index')"
                :active="request()->routeIs('manifestacao.index')">
                {{ __('Manifestação') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('settings.index')" :active="request()->routeIs('settings.index')">
                {{ __('Settings') }}
            </x-responsive-nav-link>
            <div class="px-4 pt-3">
                <form method="post" action="{{ route('scope.organization') }}">
                    @csrf
                    <label for="active-org-mobile" class="sr-only">{{ __('scope.org_label') }}</label>
                    <select id="active-org-mobile" name="organization_id"
                        onchange="this.form.submit()"
                        class="mx-select py-1.5 text-xs font-medium">
                        <option value="">{{ __('scope.all_orgs') }}</option>
                        @foreach ($navOrganizations as $o)
                            <option value="{{ $o->id }}" @selected((int) $activeOrgIdSession === (int) $o->id)>{{ $o->name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        <div class="border-t border-slate-200 pb-1 pt-4">
            <div class="px-4">
                <div class="text-base font-medium text-slate-800">{{ auth()->user()->name }}</div>
                <div class="text-sm font-medium text-slate-500">{{ auth()->user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}" class="w-full px-4">
                    @csrf
                    <button type="submit"
                        class="w-full px-4 py-2 text-start text-sm leading-5 text-slate-700 hover:bg-slate-100">
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
