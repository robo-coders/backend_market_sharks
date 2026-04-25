<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function edit()
    {
        return Inertia::render('Admin/Settings', [
            'whatsappLink' => Setting::get('whatsapp_group_link'),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'whatsapp_link' => 'required|url|max:500',
        ]);

        Setting::set('whatsapp_group_link', $request->whatsapp_link);

        return back()->with('success', 'WhatsApp group link updated.');
    }
}