<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('team.dashboard', function ($user) {
    return $user->hasRole('team');
});

Broadcast::channel('chat.room', function ($user) {
    if (! $user->can('chat.view')) {
        return false;
    }

    return [
        'id'   => $user->id,
        'name' => $user->name,
        'role' => $user->getRoleNames()->first(),
    ];
});