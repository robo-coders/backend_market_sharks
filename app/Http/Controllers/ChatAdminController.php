<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ChatAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage', ChatMessage::class);

        $users = User::query()
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id'       => $u->id,
                'name'     => $u->name,
                'email'    => $u->email,
                'role'     => $u->getRoleNames()->first(),
                'can_send' => $u->can('chat.send'),
                'can_view' => $u->can('chat.view'),
            ]);

        return response()->json([
            'features' => ChatSettings::all(),
            'roles'    => Role::pluck('name'),
            'users'    => $users,
        ]);
    }

    public function setUserAccess(Request $request, User $user): JsonResponse
    {
        $this->authorize('manage', ChatMessage::class);

        $data = $request->validate([
            'permission' => ['required', Rule::in(['chat.send', 'chat.view'])],
            'granted'    => ['required', 'boolean'],
        ]);

        if ($data['granted']) {
            $user->givePermissionTo($data['permission']);
        } else {
            $user->revokePermissionTo($data['permission']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'id'       => $user->id,
            'can_send' => $user->can('chat.send'),
            'can_view' => $user->can('chat.view'),
        ]);
    }

    public function setRoleAccess(Request $request): JsonResponse
    {
        $this->authorize('manage', ChatMessage::class);

        $data = $request->validate([
            'role'       => ['required', 'string', 'exists:roles,name'],
            'permission' => ['required', Rule::in(['chat.send', 'chat.view'])],
            'granted'    => ['required', 'boolean'],
        ]);

        $role = Role::where('name', $data['role'])->firstOrFail();

        if ($data['granted']) {
            $role->givePermissionTo($data['permission']);
        } else {
            $role->revokePermissionTo($data['permission']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['role' => $role->name, 'granted' => $data['granted']]);
    }

    public function updateFeature(Request $request): JsonResponse
    {
        $this->authorize('manage', ChatMessage::class);

        $data = $request->validate([
            'feature' => ['required', Rule::in(ChatSettings::FEATURES)],
            'enabled' => ['required', 'boolean'],
        ]);

        ChatSettings::setEnabled($data['feature'], $data['enabled']);

        return response()->json(['features' => ChatSettings::all()]);
    }
}
