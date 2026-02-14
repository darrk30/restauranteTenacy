<?php

namespace App\Filament\Restaurants\Resources\ConceptoCajasResource\Pages;

use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;
use App\Filament\Restaurants\Resources\ConceptoCajasResource;
use App\Models\SessionCashRegister;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListConceptoCajas extends ListRecords implements HasActions
{
    use InteractsWithActions;
    protected static string $resource = ConceptoCajasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),
        ];
    }

    public function mount(): void
    {
        parent::mount();

        $hasSession = SessionCashRegister::where('user_id', Auth::id())
            ->whereNull('closed_at')
            ->exists();

        if (! $hasSession) {
            Notification::make()
                ->title('CAJA CERRADA')
                ->body('No tienes una caja aperturada. No puedes registrar ingresos ni egresos.')
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
