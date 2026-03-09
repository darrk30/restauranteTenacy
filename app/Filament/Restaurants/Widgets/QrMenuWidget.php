<?php

namespace App\Filament\Restaurants\Widgets;

use Filament\Widgets\Widget;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

// 🟢 Nuevas importaciones para Formularios
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Auth;

// 🟢 Agregamos "implements HasForms"
class QrMenuWidget extends Widget implements HasForms
{
    // 🟢 Usamos el trait
    use InteractsWithForms;

    protected static string $view = 'filament.restaurants.widgets.qr-menu-widget';
    
    // Aquí se guardará el estado del formulario
    public ?array $data = []; 
    public $cartaActivaAdmin;

    public function mount()
    {
        $tenant = Filament::getTenant();
        $this->cartaActivaAdmin = $tenant->carta_activa_admin === 'activo';

        // Inicializamos el formulario con el dato de la base de datos
        $this->form->fill([
            'carta_activa_cliente' => $tenant->carta_activa_cliente === 'activo',
        ]);
    }

    // 🟢 Creamos el Formulario Nativo de Filament
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Toggle::make('carta_activa_cliente')
                    ->label('Activar Menú Público')
                    // Lo bloquea automáticamente si el Admin lo desactivó
                    ->disabled(!$this->cartaActivaAdmin) 
                    ->live() // Hace que guarde al instante al hacer clic
                    ->visible(fn() => Auth::user()->can('activar_menu_publico_rest'))
                    ->afterStateUpdated(function ($state) {
                        // Esta función se ejecuta sola al mover el interruptor
                        $tenant = Filament::getTenant();
                        $tenant->carta_activa_cliente = $state ? 'activo' : 'inactivo';
                        $tenant->save();

                        Notification::make()
                            ->title($state ? 'Carta Activada' : 'Carta Desactivada')
                            ->body($state ? 'Los clientes ya pueden ver el menú.' : 'El enlace público ha sido bloqueado.')
                            ->success()
                            ->send();
                    }),
            ])
            ->statePath('data');
    }

    public function getQrCodeHtml()
    {
        $tenant = Filament::getTenant();
        $url = route('carta.digital', ['tenant' => $tenant->slug]);
        return QrCode::size(250)->style('round')->margin(1)->color(37, 99, 235)->generate($url);
    }

    public function procesarDescargaPng($base64Image)
    {
        $imageParts = explode(";base64,", $base64Image);
        $imageBase64 = base64_decode($imageParts[1]);
        $tenant = Filament::getTenant();
        $fileName = 'qr-carta-' . $tenant->slug . '.png';

        return response()->streamDownload(function () use ($imageBase64) {
            echo $imageBase64;
        }, $fileName, ['Content-Type' => 'image/png']);
    }
}