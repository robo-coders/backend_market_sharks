<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        // $users = User::with('subscription')
        // ->whereNotIn('role', ['admin', 'super_admin'])
        // ->get();

        $users = User::role('user')
            ->with('subscription')
            ->get();
        $counts = [
            'all'      => $users->count(),
            'pending'  => $users->filter(fn($user) => $user->subscription_status === 'pending')->count(),
            'blocked'  => $users->filter(fn($user) => $user->subscription_status === 'blocked')->count(),
            'active'   => $users->filter(fn($user) => $user->subscription_status === 'active')->count(),
            'expiring' => $users->filter(fn($user) => $user->subscription_status === 'expiring')->count(),
            'expired'  => $users->filter(fn($user) => $user->subscription_status === 'expired')->count(),
        ];

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'counts' => $counts,
            'success' => session('success'), //temporary for testing purpose only

        ]);
    }

    public function approve(User $user) {
        $user->update(['status' => 'active']);
        return back()->with('success', 'User approved');

    }

    public function show(User $user) {
        // return Inertia::render('Admin/Users/Show3');
        $user->load('subscription');

        return Inertia::render('Admin/Users/Show3', [
            'user' => $user,
        ]);
    
    }

    public function destroy(User $user) {
        if($user->subscription) {
            $user->subscription->delete();
        }
        
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted successfully.');

    }
}