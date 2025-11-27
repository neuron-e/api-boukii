<?php

use App\Models\Monitor;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('monitor.{id}', function ($user, $id) {
    $isMonitorUser = in_array($user->type, ['monitor', 3, '3'], true);

    if ($isMonitorUser && Monitor::where('user_id', $user->id)->where('id', $id)->exists()) {
        return true;
    }

    return false;
});
