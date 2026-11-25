<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold leading-tight text-slate-900">
            {{ __('sped.title') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-page max-w-5xl">
            @if (session('sped_ok'))
                <div
                    class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
                    {{ session('sped_ok') }}
                </div>
            @endif

            <div class="mx-card p-6">
                <p class="mb-6 text-sm text-slate-600">{{ __('sped.upload_hint') }}</p>

                <form method="post" action="{{ route('sped.store') }}" class="space-y-6" enctype="multipart/form-data">
                    @csrf

                    <div>
                        <label for="sped-branch"
                            class="block text-sm font-medium text-slate-700">{{ __('sped.select_branch') }}</label>
                        <select id="sped-branch" name="branch_id" required class="mx-select max-w-2xl">
                            <option value="">—</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" @selected((string) old('branch_id') === (string) $b->id)>
                                    {{ $b->name }} ({{ $b->cnpj }})</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('branch_id')" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ __('sped.file') }}</label>
                        <input type="file" name="file" required accept=".txt,text/plain"
                            class="mt-2 block w-full max-w-2xl rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm file:mr-4 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-200" />
                        <x-input-error class="mt-2" :messages="$errors->get('file')" />
                    </div>

                    <div class="border-t border-slate-200 pt-4">
                        <x-primary-button type="submit">{{ __('sped.upload_btn') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <p class="mt-6 text-sm text-slate-500">{{ __('sped.footer_hint') }}</p>
        </div>
    </div>
</x-app-layout>
