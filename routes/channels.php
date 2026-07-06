<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('team.dashboard', function ($user) {
    return $user->hasRole('team');
});