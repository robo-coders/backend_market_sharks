<?php

namespace App\Policies;

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatSettings;

class ChatMessagePolicy
{
    private const EDIT_WINDOW_MINUTES = 15;

    public function viewAny(User $user): bool
    {
        return $user->can('chat.view');
    }

    public function create(User $user): bool
    {
        return $user->can('chat.send');
    }

    public function update(User $user, ChatMessage $message): bool
    {
        if (! ChatSettings::enabled('edit')) {
            return false;
        }
        if (! $user->can('chat.send')) {
            return false;
        }
        if ($message->user_id !== $user->id) {
            return false;
        }
        return $message->created_at->diffInMinutes(now()) < self::EDIT_WINDOW_MINUTES;
    }

    public function delete(User $user, ChatMessage $message): bool
    {
        if (! ChatSettings::enabled('delete')) {
            return false;
        }
        if ($message->user_id === $user->id && $user->can('chat.send')) {
            return true;
        }
        return $user->can('chat.manage') || $user->hasRole(['admin', 'super_admin']);
    }

    public function manage(User $user): bool
    {
        return $user->can('chat.manage');
    }
}
