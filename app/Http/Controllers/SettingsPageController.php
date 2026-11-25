<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsPageController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('settings.index', [
            'organizations' => Organization::query()
                ->forUser($user)
                ->with('branches')
                ->orderBy('social')
                ->get(),
        ]);
    }
}
