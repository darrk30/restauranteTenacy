<?php

namespace App\Filament\Restaurants\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Symfony\Component\Mime\Message;

class RestaurantProfile extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = 'Mi Restaurante';
    protected static ?string $title = 'Perfil del Local';
    protected static string $view = 'filament.restaurants.restaurant-profile';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 85;

    public $restaurant;

    public function mount()
    {
        Cache::forget("restaurant_data_user_" . Auth::id());
        $this->restaurant = Filament::getTenant();

        if (!$this->restaurant) {
            abort(403, 'No hay un contexto de restaurante seleccionado.');
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()->canAny(['editar_mi_restaurante_rest']);
    }

    /**
     * Lógica de Sincronización con la API de Facturación
     */
    protected function sincronizarConFacturador(array $data, bool $incluirCertificado = true): bool
    {
        $baseUrl = env('GREENTER_API_URL');
        if (!$baseUrl) return false;

        $token = $data['api_token'] ?? $this->restaurant->api_token;
        $urlApi = rtrim($baseUrl, '/') . "/api/my-company/update";

        try {
            $requestApi = Http::withToken($token)->timeout(15);

            // Si hay un certificado nuevo en el formulario o uno guardado localmente por fallo previo
            $pathCert = $data['cert_path'] ?? $this->restaurant->cert_path;

            if ($incluirCertificado && !empty($pathCert)) {
                $rutaFisica = Storage::disk('public')->path($pathCert);
                if (file_exists($rutaFisica)) {
                    $requestApi->attach('cert', file_get_contents($rutaFisica), basename($rutaFisica));
                }
            }

            $response = $requestApi->post($urlApi, [
                'ruc' => $data['ruc'] ?? $this->restaurant->ruc,
                'razon_social' => $data['name'] ?? $this->restaurant->name,
                'sol_user' => $data['sol_user'] ?? $this->restaurant->sol_user,
                'sol_pass' => $data['sol_pass'] ?? $this->restaurant->sol_pass,
            ]);
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Editar Datos del Local')
                ->icon('heroicon-m-pencil-square')
                ->modalWidth('4xl')
                ->mountUsing(fn($form) => $form->fill($this->restaurant->toArray()))
                ->form([
                    Section::make('Información de Identidad')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('name')->label('Razón Social')->required(),
                                TextInput::make('name_comercial')->label('Nombre Comercial')->required(),
                                TextInput::make('ruc')->label('RUC')->numeric()->length(11)->required(),
                            ]),
                        ]),

                    Section::make('Configuración de Facturación Electrónica (SUNAT)')
                        ->description('Credenciales para la API de Facturación.')
                        ->collapsible()
                        ->schema([
                            Grid::make(2)->schema([
                                FileUpload::make('cert_path')
                                    ->label('Certificado Digital (PEM / TXT)')
                                    ->disk('public')
                                    ->directory('certificates')
                                    ->columnSpanFull()
                                    ->hintActions([
                                        // ACCIÓN: Descargar desde la API
                                        \Filament\Forms\Components\Actions\Action::make('download_api')
                                            ->label('Descargar de API')
                                            ->icon('heroicon-o-cloud-arrow-down')
                                            ->action(function () {
                                                $url = rtrim(env('GREENTER_API_URL'), '/') . "/api/companies/{$this->restaurant->ruc}/certificate";
                                                try {
                                                    $res = Http::withToken($this->restaurant->api_token)->get($url);
                                                    if ($res->successful() && $res->json('code') === 200) {
                                                        return response()->streamDownload(fn() => print(base64_decode($res->json('data.file_base64'))), $res->json('data.filename'));
                                                    }
                                                } catch (\Exception $e) {}
                                                Notification::make()->title('No disponible en el Facturador')->warning()->send();
                                            }),
                                        // ACCIÓN: Sincronizar Manual
                                        \Filament\Forms\Components\Actions\Action::make('sync_now')
                                            ->label('Sincronizar ahora')
                                            ->icon('heroicon-o-arrow-path')
                                            ->color('success')
                                            ->action(function () {
                                                if ($this->sincronizarConFacturador($this->restaurant->toArray())) {
                                                    if ($this->restaurant->cert_path) {
                                                        Storage::disk('public')->delete($this->restaurant->cert_path);
                                                        $this->restaurant->update(['cert_path' => null]);
                                                    }
                                                    Notification::make()->title('Sincronizado correctamente')->success()->send();
                                                } else {
                                                    Notification::make()->title('Error de conexión')->danger()->send();
                                                }
                                            }),
                                    ]),

                                TextInput::make('sol_user')->label('Usuario SOL'),
                                TextInput::make('sol_pass')->label('Clave SOL')->password()->revealable(),
                                TextInput::make('api_token')->label('API Token'),
                                Toggle::make('production')->label('Modo Producción')->onColor('success'),
                            ]),
                        ]),

                    Section::make('Contacto y Ubicación')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('email')->label('Correo')->email(),
                                TextInput::make('phone')->label('Teléfono'),
                                TextInput::make('address')->label('Dirección')->columnSpanFull()->required(),
                            ]),
                            Grid::make(3)->schema([
                                TextInput::make('department')->label('Departamento'),
                                TextInput::make('province')->label('Provincia'),
                                TextInput::make('district')->label('Distrito'),
                            ]),
                        ]),

                    Section::make('Identidad Visual')
                        ->schema([
                            FileUpload::make('logo')
                                ->label('Logo')
                                ->image()
                                ->disk('public')
                                ->directory('tenants/' . $this->restaurant->slug . '/logo'),
                        ]),
                ])
                ->action(function (array $data) {
                    // 1. Intentar sincronizar
                    $exitoApi = $this->sincronizarConFacturador($data);

                    if ($exitoApi) {
                        // Si funcionó, borramos el certificado local para no ocupar espacio
                        if (!empty($data['cert_path'])) {
                            Storage::disk('public')->delete($data['cert_path']);
                            $data['cert_path'] = null; 
                        }
                        $title = 'Actualizado y Sincronizado';
                        $status = 'success';
                    } else {
                        // Si falló, se guarda localmente (incluyendo el path del certificado)
                        $title = 'Guardado Localmente (Sin conexión con Facturador)';
                        $status = 'warning';
                    }

                    $this->restaurant->update($data);
                    $this->restaurant->refresh();
                    Cache::forget("restaurant_data_user_" . Auth::id());

                    Notification::make()->title($title)->status($status)->send();
                }),
        ];
    }
}