<?php

namespace App\Filament\Restaurants\Resources\UserResource\Pages;

use App\Filament\Restaurants\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
