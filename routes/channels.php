<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Team activity for one business.
 *
 * The authorisation callback is the tenant boundary for websockets: without
 * comparing the subscriber's own tenant_id, anyone signed in could listen to
 * any business's channel simply by naming its id in a subscribe frame. The id
 * in the channel name is untrusted input, exactly like a route parameter.
 */
Broadcast::channel('tenant.{tenantId}.team', function (User $user, string $tenantId) {
    return $user->tenant_id === $tenantId
        && $user->hasRole('owner', 'administrator', 'manager');
});
