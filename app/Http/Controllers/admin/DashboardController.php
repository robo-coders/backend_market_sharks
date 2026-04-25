<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Dashboard', [
            'role' => auth()->user()?->getRoleNames()->first(),
            'whatsappLink' => Setting::get('whatsapp_group_link'),
            'stats' => [
                'total'          => User::role('user')->count(),
                'active'         => User::role('user')->where('status', 'active')->count(),
                'payment_review' => User::role('user')->where('status', 'payment_review')->count(),
                'blocked'        => User::role('user')->where('status', 'blocked')->count(),
            ],
        ]);
    }
}