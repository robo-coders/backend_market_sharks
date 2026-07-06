<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Notifications\TeamInviteNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;


class TeamController extends Controller
{
    public function index(Request $request)
    {
        $teams = User::role('team')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('whatsapp_number', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get();

        return Inertia::render('Admin/Teams/Index', [
            'teams' => $teams,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Teams/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'whatsapp_number' => $validated['whatsapp_number'] ?? null,
            'password' => Hash::make(Str::random(32)),
        ]);

        $user->assignRole(['team']);

        $token = Password::createToken($user);

        $user->notify(new TeamInviteNotification($token));

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team created. Invitation email sent.');
    }

    public function edit(User $team)
    {
        return Inertia::render('Admin/Teams/Edit', [
            'team' => $team,
        ]);
    }

    public function update(Request $request, User $team)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $team->id],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
        ]);

        $team->update($validated);

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team updated.');
    }

    public function destroy(User $team)
    {
        $team->delete();

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team deleted.');
    }

    public function block(Request $request, User $team)
    {
        if (! $request->user()->hasRole('super_admin')) {
            abort(403);
        }

        if (! $team->hasRole('team')) {
            abort(404);
        }

        $team->update(['status' => 'blocked']);

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team blocked successfully.');
    }

    public function unblock(Request $request, User $team)
    {
        if (! $request->user()->hasRole('super_admin')) {
            abort(403);
        }

        if (! $team->hasRole('team')) {
            abort(404);
        }

        $team->update(['status' => 'active']);

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team unblocked successfully.');
    } 
}
