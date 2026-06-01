<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tables', function ($user) {
    return (bool) $user;
});
