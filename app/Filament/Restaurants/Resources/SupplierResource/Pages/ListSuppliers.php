<?php

namespace App\Filament\Restaurants\Resources\SupplierResource\Pages;

use App\Filament\Restaurants\Resources\SupplierResource;
use App\Imports\SuppliersImport;
use App\Exports\SuppliersExport;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                // 🟢 1. IMPORTAR (Procesado en memoria)
                Action::make('importar')
                    ->label('Importar Datos')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->form([
                        FileUpload::make('archivo')
                            ->label('Archivo Excel (.xlsx)')
                            ->helperText('Asegúrate de que las columnas coincidan con la plantilla.')
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
                                    ->url(fn() => asset('assets/Importar_Proveedores.xlsx'))
                                    ->openUrlInNewTab()
                            ),
                    ])
                    ->action(function (array $data) {
                        try {
                            $file = is_array($data['archivo']) ? array_values($data['archivo'])[0] : $data['archivo'];

                            $importador = new SuppliersImport();
                            Excel::import($importador, $file);

                            $mensajeBase = "Se crearon {$importador->proveedoresNuevos} proveedores nuevos.";

                            if ($importador->proveedoresOmitidos > 0) {
                                $ejemplosError = implode(', ', array_slice($importador->erroresDetalle, 0, 3));
                                $extra = $importador->proveedoresOmitidos > 3 ? "..." : "";

                                Notification::make()
                                    ->title("Importación finalizada con omisiones")
                                    ->body("$mensajeBase Se omitieron {$importador->proveedoresOmitidos} fila(s). Ej: $ejemplosError $extra")
                                    ->warning()
                                    ->persistent()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Importación Exitosa')
                                    ->body($mensajeBase)
                                    ->success()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error Crítico')
                                ->body('El archivo no tiene el formato correcto o está dañado.')
                                ->danger()
                                ->send();
                        }
                    }),

                // 🟢 2. EXPORTAR 
                Action::make('exportar')
                    ->label('Exportar Datos')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function () {
                        return Excel::download(new SuppliersExport, 'Proveedores_' . now()->format('dmY_His') . '.xlsx');
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
