<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Index', [
            'signature' => $request->user()->signature,
        ]);
    }

    public function updateSignature(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'signature' => ['nullable', 'string'],
        ]);

        $signature = $data['signature'] ?? null;

        if ($signature === '•••• saved') {
            return back()->with('success', 'Signature unchanged.');
        }

        $request->user()->update([
            'signature' => filled($signature) ? $signature : null,
        ]);

        if (filled($request->user()->signature)) {
            session()->forget('remind_signature');
        }

        return back()->with('success', 'Signature updated.');
    }
}
