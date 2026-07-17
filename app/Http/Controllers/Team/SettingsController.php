<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function edit()
    {
        return Inertia::render('Team/Settings', [
            'updateUrl' => route('team.settings.update'),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'theme' => ['sometimes', 'required', Rule::in(['light', 'dark'])],
            'alert_sounds_muted' => ['sometimes', 'required', 'boolean'],
        ]);

        // forceFill so this works without touching the User model's $fillable.
        $request->user()->forceFill($validated)->save();

        return back()->with('status', [
            'type' => 'success',
            'title' => 'Settings',
            'text' => 'Preferences saved.',
        ]);
    }
}