<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RestaurantResource\Pages;
use App\Models\Restaurant;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;

class RestaurantResource extends Resource
{
    protected static ?string $model = Restaurant::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function canAccess(): bool
    {
        return Auth::user()->id == 1;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name_comercial')
                    ->maxLength(255),
                TextInput::make('ruc')
                    ->required()
                    ->maxLength(255),
                TextInput::make('address')
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                TextInput::make('department')
                    ->maxLength(255),
                TextInput::make('district')
                    ->maxLength(255),
                TextInput::make('province')
                    ->maxLength(255),
                TextInput::make('ubigeo')
                    ->maxLength(255),
                TextInput::make('status')
                    ->required()
                    ->maxLength(255)
                    ->default('activo'),
                TextInput::make('logo')
                    ->maxLength(255),
                TextInput::make('slug')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('logo')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('name_comercial')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ruc')
                    ->searchable(),
                TextColumn::make('address')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('department')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('district')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('province')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ubigeo')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'activo' => 'success',
                        'inactivo' => 'danger',
                    }),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('manageUsers')
                    ->icon('heroicon-o-user-plus')
                    ->label('Usuarios')
                    ->color('primary')
                    ->form(function (Restaurant $record) {
                        return [
                            Select::make('users')
                                ->label('Usuarios')
                                ->options(User::pluck('name', 'id')) // muestra todos los usuarios
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->default($record->users->pluck('id')->toArray()), // preselecciona los que ya están
                        ];
                    })
                    ->action(function (Restaurant $record, array $data) {
                        $userIds = $data['users'] ?? [];
                        $record->users()->sync($userIds); // actualiza la relación (agrega y quita)
                    }),
                Tables\Actions\EditAction::make(),
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRestaurants::route('/'),
            // 'create' => Pages\CreateRestaurant::route('/create'),
            // 'edit' => Pages\EditRestaurant::route('/{record}/edit'),
        ];
    }
}
