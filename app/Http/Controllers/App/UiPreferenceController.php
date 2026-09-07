<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Support\PinnedTabs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UiPreferenceController extends Controller
{
    public function updatePinnedTab(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'page' => ['required', 'string', Rule::in(PinnedTabs::pages())],
            'tab' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $tab = filled($data['tab'] ?? null) ? (string) $data['tab'] : null;

        $user->setPinnedTab($data['page'], $tab);

        return back();
    }
}
