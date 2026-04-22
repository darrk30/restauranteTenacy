<?php

use App\Models\Restaurant;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('impresora.{token}', function ($user, $token) {
    return Restaurant::where('api_token', $token)->exists();
});
