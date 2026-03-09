<?php

namespace App\Filament\Restaurants\Resources\ClientResource\Pages;

use App\Filament\Restaurants\Resources\ClientResource;
use App\Imports\ClientImporter;
use App\Exports\ClientExporter;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                // 🟢 1. IMPORTAR (Procesado en memoria sin guardar)
                Action::make('importar')
                    ->label('Importar Datos')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->visible(fn() => Auth::user()->can('importar_clientes_rest'))
                    ->form([
                        FileUpload::make('archivo')
                            ->label('Archivo Excel (.xlsx)')
                            ->helperText('Descarga la plantilla, llénala y súbela aquí.')
                            ->storeFiles(false)
                            ->required()
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/csv',
                            ])
                            ->hintAction(
                                \Filament\Forms\Components\Actions\Action::make('descargar_formato')
                                    ->label('Descargar plantilla')
                                    ->icon('heroicon-o-document-arrow-down')
                                    ->url(fn() => asset('assets/Importar_Cliente.xlsx'))
                                    ->openUrlInNewTab()
                            ),
                    ])
                    ->action(function (array $data) {
                        try {
                            $file = is_array($data['archivo']) ? array_values($data['archivo'])[0] : $data['archivo'];

                            // 🟢 Instanciamos la clase antes de pasarla al import para poder leer sus variables después
                            $importador = new ClientImporter();
                            Excel::import($importador, $file);

                            // 🟢 Lógica de Notificaciones
                            $cantidadDuplicados = count($importador->yaRegistrados);

                            if ($cantidadDuplicados > 0) {
                                // Mostramos los nombres de los 3 primeros duplicados para no saturar el texto
                                $nombresMuestra = implode(', ', array_slice($importador->yaRegistrados, 0, 3));
                                $textoExtra = $cantidadDuplicados > 3 ? " y " . ($cantidadDuplicados - 3) . " más." : ".";

                                Notification::make()
                                    ->title("Se importaron {$importador->importadosNuevos} clientes nuevos.")
                                    ->body("Se omitieron {$cantidadDuplicados} clientes que ya estaban registrados (Ej: {$nombresMuestra}{$textoExtra})")
                                    ->warning() // Alerta amarilla
                                    ->persistent() // Hace que no se cierre sola para que puedas leerla
                                    ->send();
                            } else {
                                // Si todo fue perfecto y no hubo duplicados
                                Notification::make()
                                    ->title("¡Éxito!")
                                    ->body("Se importaron los {$importador->importadosNuevos} clientes correctamente.")
                                    ->success()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error en la importación')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // 🟢 2. EXPORTAR 
                Action::make('exportar')
                    ->label('Exportar Datos')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->visible(fn() => Auth::user()->can('exportar_clientes_rest'))
                    ->action(function () {
                        return Excel::download(new ClientExporter, 'Clientes_' . now()->format('dmY_His') . '.xlsx');
                    }),
            ])
            ->label('Opciones de Datos')
            ->icon('heroicon-m-chevron-down')
            ->iconPosition('after')
            ->button() 
            ->color('info'),

            Actions\CreateAction::make()->label('Nuevo')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }
}
