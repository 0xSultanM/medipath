<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channels (Reverb)
|--------------------------------------------------------------------------
| Notification channel is private per user; the id must equal the
| authenticated DB id — the server is the only source (G8).
*/
Broadcast::channel('App.Models.User.{userId}', function (User $user, int $userId) {
    return (int) $user->id === (int) $userId;
});

// Settings changes can include private operational values (revenue, cutoffs):
// only administrators may subscribe to the broadcast channel.
Broadcast::channel('admin.settings', fn (User $user) => $user->isAdmin());
