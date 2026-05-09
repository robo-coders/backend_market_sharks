<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminInviteNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function index(Request $request) {

        $searchInput = $request->input('search');

        $admins = User::whereHas('roles', function ($query) {
                $query->where('name', 'admin');
            })
            ->when($searchInput, function ($query) use ($searchInput) {
                $query->where('name', 'like', "%{$searchInput}%")
                      ->orWhere('email', 'like', "%{$searchInput}%");
            })
            ->select('id', 'name', 'email', 'whatsapp_number', 'status', 'created_at')
            ->orderBy('name')
            ->get();

            $admins->makeHidden(['display_name', 'subscription_status', 'subscription']);
            

        return Inertia::render('Admin/Admins/Index', [
            'admins' => $admins,
            'filters' => [
                'search' => $searchInput,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Admins/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
        ]);

        $user = User::create([
            'name'         => $validated['name'],
            'email'        => $validated['email'],
            'whatsapp_number' => $validated['whatsapp_number'] ?? null,
            'password'     => Hash::make(Str::random(32)),

        ]);

        $user->assignRole('admin');

        $token = Password::createToken($user);

        $user->notify(new AdminInviteNotification($token));

        return redirect()
            ->route('admin.admins.index')
            ->with('success', 'Admin created. Invitation email sent.');
    }

    public function edit(User $admin) {
        return Inertia::render('Admin/Admins/Edit', [
            'admin' => [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'whatsapp_number' => $admin->whatsapp_number,
            ],
        ]);
    }

    public function update(Request $request, User $admin){
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'email'             => ['required', 'email', 'max:255', 'unique:users,email,' . $admin->id],
            'whatsapp_number'   => ['nullable', 'string', 'max:30'],
        ]);

        $admin->update($validated);

        return redirect()
            ->route('admin.admins.index')
            ->with('success', 'Admin updated successfully.');

    }

    public function destroy(Request $request ,User $admin) {

        if (! $request->user()->hasRole('super_admin')) {
            abort(403);
        }

        if (! $admin->hasRole('admin')) {
            abort(404);
        }

        $admin->delete();

        return redirect()
            ->route('admin.admins.index')
            ->with('success', 'Admin deleted successfully');
    }

    public function block(Request $request, User $admin) {

        if (! $request->user()->hasRole('super_admin')) {
            abort(403);
        }

        if (! $admin->hasRole('admin')) {
            abort(404);
        }

        $admin->update(['status' => 'blocked']);

        return redirect()
            ->route('admin.admins.index')
            ->with('success', 'Admin blocked successfully.');
        
    }

    public function unblock(Request $request, User $admin)
    {
        if (! $request->user()->hasRole('super_admin')) {
            abort(403);
        }

        if (! $admin->hasRole('admin')) {
            abort(404);
        }

        $admin->update(['status' => 'active']);

        return redirect()
            ->route('admin.admins.index')
            ->with('success', 'Admin unblocked successfully.');

    }
}
