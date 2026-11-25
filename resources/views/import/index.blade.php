<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold leading-tight text-slate-900">
            {{ __('documents.title_import') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-page max-w-5xl">
            @if (session('import_ok'))
                <div
                    class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
                    {{ session('import_ok') }}
                </div>
            @endif
            @if (session('import_warn'))
                <div
                    class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {{ session('import_warn') }}
                </div>
            @endif
            @php($importErrors = session('import_errors_list', []))
            @if (count($importErrors) > 0)
                <ul
                    class="mb-4 list-inside list-disc rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    @foreach ($importErrors as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            @endif

            <form method="post" action="{{ route('import.store') }}" class="mx-card space-y-6 p-6" enctype="multipart/form-data">
                @csrf
                <div>
                    <label for="branch_id"
                        class="block text-sm font-medium text-slate-700">{{ __('documents.select_branch') }}</label>
                    <select id="branch_id" name="branch_id" required
                        class="mx-select max-w-2xl">
                        <option value="">—</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected((string) old('branch_id') === (string) $b->id)>
                                {{ $b->name }} ({{ $b->cnpj }})</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('branch_id')" />
                </div>

                <div>
                    <label for="on_duplicate"
                        class="block text-sm font-medium text-slate-700">{{ __('documents.on_duplicate') }}</label>
                    <p class="text-xs text-slate-500">{{ __('documents.on_duplicate_hint') }}</p>
                    <select id="on_duplicate" name="on_duplicate"
                        class="mx-select max-w-2xl">
                        <option value="reject" @selected(old('on_duplicate', 'reject') === 'reject')>{{ __('documents.on_duplicate_reject') }}
                        </option>
                        <option value="skip" @selected(old('on_duplicate') === 'skip')>{{ __('documents.on_duplicate_skip') }}</option>
                        <option value="replace" @selected(old('on_duplicate') === 'replace')>{{ __('documents.on_duplicate_replace') }}
                        </option>
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('on_duplicate')" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('documents.files') }}</label>
                    <p class="text-xs text-slate-500">{{ __('documents.upload_hint') }}</p>
                    <input type="file" name="files[]" multiple required accept=".xml,text/xml,application/xml"
                        class="mt-2 block w-full max-w-2xl rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm file:mr-4 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-200" />
                    <x-input-error class="mt-2" :messages="$errors->get('files')" />
                    <x-input-error class="mt-2" :messages="$errors->get('files.*')" />
                </div>

                <div class="border-t border-slate-200 pt-4">
                    <x-primary-button type="submit">{{ __('documents.upload_btn') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
