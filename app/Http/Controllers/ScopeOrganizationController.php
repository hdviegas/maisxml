<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScopeOrganizationController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->input('organization_id') === '') {
            $request->merge(['organization_id' => null]);
        }

        $validated = $request->validate([
            'organization_id' => ['nullable', 'integer'],
        ]);

        $raw = $validated['organization_id'] ?? null;
        if ($raw === null) {
            $request->session()->forget('active_organization_id');
        } else {
            $exists = Organization::query()->forUser($request->user())->whereKey($raw)->exists();
            if (! $exists) {
                abort(403);
            }
            $request->session()->put('active_organization_id', $raw);
        }

        return redirect()->route('documents.index');
    }
}
