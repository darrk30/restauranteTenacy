<?php

namespace App\Filament\Clusters\Products\Resources\ProductResource\Pages;

use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Imports\ProductsImport;
use App\Exports\ProductsExport; // 🟢 No olvides importar la clase
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                // 🟢 1. IMPORTAR (El que ya teníamos configurado)
                Action::make('importar')
                    ->label('Importar Datos')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->visible(fn() => Auth::user()->can('importar_productos_rest'))
                    ->form([
                        FileUpload::make('archivo')
                            ->label('Archivo Excel (.xlsx o .csv)')
                            ->helperText('Asegúrate de que las columnas no se muevan y que el formato sea correcto.')
                            ->storeFiles(false)
                            ->required()
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/csv',
                                'application/csv'
                            ])
                            ->hintAction(
                                \Filament\Forms\Components\Actions\Action::make('descargar_formato')
                                    ->label('Descargar formato de ejemplo')
                                    ->icon('heroicon-o-document-arrow-down')
                                    ->url(fn() => asset('assets/formato_productos.xlsx'))
                                    ->openUrlInNewTab()
                            ),
                    ])
                    ->action(function (array $data) {
                        try {
                            $file = is_array($data['archivo']) ? array_values($data['archivo'])[0] : $data['archivo'];

                            $importador = new ProductsImport();
                            Excel::import($importador, $file);

                            // 🟢 CORRECCIÓN: Quitamos la variable $productosActualizados que ya no existe
                            $mensajeBase = "Se crearon {$importador->productosNuevos} productos nuevos.";

                            if ($importador->productosOmitidos > 0) {
                                $ejemplosError = implode(', ', array_slice($importador->erroresDetalle, 0, 3));
                                $extra = $importador->productosOmitidos > 3 ? "..." : "";

                                Notification::make()
                                    ->title("Importación finalizada con omisiones")
                                    ->body("$mensajeBase Se omitieron {$importador->productosOmitidos} fila(s). Ej: $ejemplosError $extra")
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

                // 🟢 2. EXPORTAR PRODUCTOS
                Action::make('exportar')
                    ->label('Exportar Datos')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->visible(fn() => Auth::user()->can('exportar_productos_rest'))
                    ->action(function () {
                        return Excel::download(new ProductsExport, 'Productos_' . now()->format('dmY_His') . '.xlsx');
                    }),
                Action::make('actualizar_precios')
                    ->label('Actualizar Precios')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('info')
                    ->form([
                        FileUpload::make('archivo')
                            ->label('Archivo Excel de Precios (.xlsx)')
                            ->helperText('Formato: CODIGO | NOMBRE | PRECIO_BASE | ATRIBUTO | VALORES | PRECIOS_EXTRA')
                            ->storeFiles(false)
                            ->required()
                            ->visible(fn() => Auth::user()->can('actualizar_precios_productos_rest'))
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ])
                            ->hintAction(
                                \Filament\Forms\Components\Actions\Action::make('descargar_formato_precios')
                                    ->label('Descargar plantilla de precios')
                                    ->icon('heroicon-o-document-arrow-down')
                                    ->url(fn() => asset('assets/Actualizar_Precios.xlsx'))
                                    ->openUrlInNewTab()
                            ),
                    ])
                    ->action(function (array $data) {
                        try {
                            $file = is_array($data['archivo']) ? array_values($data['archivo'])[0] : $data['archivo'];

                            $importador = new \App\Imports\UpdatePricesImport();
                            Excel::import($importador, $file);

                            $mensajeBase = "Se actualizaron correctamente {$importador->productosActualizados} productos.";

                            if ($importador->productosNoEncontrados > 0) {
                                $ejemplosError = implode(', ', array_slice($importador->erroresDetalle, 0, 3));
                                $extra = $importador->productosNoEncontrados > 3 ? "..." : "";

                                Notification::make()
                                    ->title("Proceso con advertencias")
                                    ->body("$mensajeBase No se encontraron {$importador->productosNoEncontrados} productos. Ej: $ejemplosError $extra")
                                    ->warning()
                                    ->persistent()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Actualización Exitosa')
                                    ->body($mensajeBase)
                                    ->success()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body('El archivo no tiene el formato correcto o está dañado.')
                                ->danger()
                                ->send();
                        }
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
