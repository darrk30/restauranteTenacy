<?php

namespace App\Filament\Restaurants\Pages;

use App\Models\Configuration;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

class ManageConfiguration extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 135;
    protected static ?string $navigationLabel = 'Configuración General';
    protected static ?string $title = 'Ajustes del Restaurante';
    protected static string $view = 'filament.ajustes.manage-configuration';

    public ?array $data = [];

    // 🟢 1. Oculta la página del menú si no tiene NINGÚN permiso de configuración
    public static function canAccess(): bool
    {
        return auth()->user()->canAny([
            'guardar_configuracion_impresion_cocina_rest',
            'guardar_configuracion_carta_web_rest',
            'guardar_configuracion_facturacion_rest'
        ]);
    }

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        $config = Configuration::where('restaurant_id', $tenant->id)->first();
        if ($config) {
            $this->form->fill($config->toArray());
        } else {
            $this->form->fill();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Configuraciones')
                    ->tabs([

                        // 🖨️ PESTAÑA: IMPRESIÓN
                        Tabs\Tab::make('Impresión y Cocina')
                            ->icon('heroicon-o-printer')
                            // 🟢 2. Muestra esta pestaña solo si tiene este permiso específico
                            ->visible(fn () => auth()->user()->can('guardar_configuracion_impresion_cocina_rest'))
                            ->schema([

                                Section::make('Impresión Automática / Directa')
                                    ->description('Se envía directamente a la tiquetera sin preguntar.')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Toggle::make('impresion_directa_comanda')->label('Comandas')->onColor('success'),
                                            Toggle::make('impresion_directa_precuenta')->label('Pre-cuentas')->onColor('success'),
                                            Toggle::make('impresion_directa_comprobante')->label('Boletas/Facturas')->onColor('success'),
                                        ]),
                                    ]),

                                Section::make('Mostrar Modal de Impresión')
                                    ->description('Muestra un cuadro en pantalla preguntando si desea imprimir.')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Toggle::make('mostrar_modal_impresion_comanda')->label('Comandas')->onColor('warning'),
                                            Toggle::make('mostrar_modal_impresion_precuenta')->label('Pre-cuentas')->onColor('warning'),
                                            Toggle::make('mostrar_modal_impresion_comprobante')->label('Boletas/Facturas')->onColor('warning'),
                                        ]),
                                    ]),

                                Section::make('Pantalla KDS (Cocina)')
                                    ->schema([
                                        Toggle::make('mostrar_pantalla_cocina')
                                            ->label('Activar visualización de pedidos en pantalla de cocina')
                                            ->helperText('Ideal si no usas tiquetera física de comandas.')
                                            ->onColor('primary'),
                                    ]),
                            ]),

                        // 📱 PESTAÑA: CARTA DIGITAL Y WEB
                        Tabs\Tab::make('Carta Web')
                            ->icon('heroicon-o-globe-alt')
                            // 🟢 3. Muestra esta pestaña solo si tiene este permiso específico
                            ->visible(fn () => auth()->user()->can('guardar_configuracion_carta_web_rest'))
                            ->schema([
                                Grid::make(2)->schema([
                                    Toggle::make('guardar_pedidos_web')
                                        ->label('Integrar pedidos web al sistema')
                                        ->helperText('Si se desactiva, los pedidos solo llegarán por WhatsApp.')
                                        ->onColor('primary')
                                        ->columnSpanFull(),

                                    Toggle::make('habilitar_delivery_web')
                                        ->label('Ofrecer Delivery')
                                        ->onColor('success'),

                                    Toggle::make('habilitar_recojo_web')
                                        ->label('Ofrecer Recojo en Tienda')
                                        ->onColor('success'),
                                ]),
                            ]),

                        // 💰 PESTAÑA: FACTURACIÓN
                        Tabs\Tab::make('Facturación')
                            ->icon('heroicon-o-receipt-percent')
                            // 🟢 4. Muestra esta pestaña solo si tiene este permiso específico
                            ->visible(fn () => auth()->user()->can('guardar_configuracion_facturacion_rest'))
                            ->schema([
                                Grid::make(2)->schema([
                                    Toggle::make('precios_incluyen_impuesto')
                                        ->label('Los precios ya incluyen impuestos')
                                        ->helperText('Actívalo si tus precios en carta ya tienen el IGV sumado.')
                                        ->onColor('success'),

                                    TextInput::make('porcentaje_impuesto')
                                        ->label('Porcentaje de Impuesto (%)')
                                        ->numeric()
                                        ->suffix('%')
                                        ->required()
                                        ->minValue(0)
                                        ->maxValue(100),
                                ]),
                            ]),

                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $tenant = Filament::getTenant();

        // 💡 NOTA SEGURA: Si una pestaña está oculta por falta de permisos,
        // Filament no envía sus campos en $data. 
        // `updateOrCreate` solo actualizará las columnas que vengan en el $data,
        // por lo tanto, no borrará accidentalmente la configuración de las pestañas ocultas.
        $config = Configuration::updateOrCreate(
            ['restaurant_id' => $tenant->id],
            $data
        );

        Notification::make()
            ->success()
            ->title('¡Cambios guardados!')
            ->body('La configuración se aplicó correctamente.')
            ->send();
    }
}