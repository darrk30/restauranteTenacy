<?php

namespace App\Filament\Restaurants\Pages\Facturacion;

use App\Models\DailySummary;
use App\Models\Sale;
use App\Services\SunatGreenterApiService;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action as HeaderAction;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Set;
use Filament\Forms\Get;

class ResumenesDiarios extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationGroup = 'Facturación';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Bandeja de Resúmenes y Bajas';

    protected static string $view = 'filament.facturacion.resumenes-diarios';

    // ==========================================
    // 🚀 BOTÓN SUPERIOR: GENERAR RESUMEN (MODAL)
    // ==========================================
    protected function getHeaderActions(): array
    {
        return [
            HeaderAction::make('generar_resumen')
                ->label('Generar Resumen de Boletas')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->modalHeading('Revisión de Resumen Diario')
                ->modalDescription('Verifica los comprobantes que se enviarán. Puedes eliminar de esta lista los que no desees incluir hoy.')
                ->modalSubmitActionLabel('Confirmar y Enviar a SUNAT')
                ->form([
                    DatePicker::make('fecha_resumen')
                        ->label('Fecha de Emisión de las Boletas')
                        ->required()
                        ->maxDate(now())
                        ->default(now()->format('Y-m-d'))
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            if (!$state) {
                                $set('comprobantes', []);
                                return;
                            }
                            $tenant = Filament::getTenant();
                            $set('comprobantes', $this->obtenerBoletasParaRepeater($tenant->id, $state));
                        }),

                    Placeholder::make('no_data')
                        ->hiddenLabel()
                        ->content(new HtmlString('<div class="p-4 rounded-lg bg-warning-50 text-warning-700 font-bold border border-warning-200">No se encontraron comprobantes pendientes para la fecha filtrada.</div>'))
                        ->visible(fn(Get $get) => empty($get('comprobantes'))),

                    Repeater::make('comprobantes')
                        ->label('Comprobantes Seleccionados')
                        ->default(fn() => $this->obtenerBoletasParaRepeater(Filament::getTenant()->id, now()->format('Y-m-d')))
                        ->schema([
                            Hidden::make('sale_id'),
                            Hidden::make('clienteTipo'),
                            Hidden::make('clienteNro'),
                            Hidden::make('mtoOperGravadas'),
                            Hidden::make('mtoIGV'),

                            TextInput::make('serieNro')->label('Comprobante')->readOnly(),
                            TextInput::make('estado')->label('Est. (1=Ok, 3=Anulado)')->readOnly(),
                            TextInput::make('total')->label('Total (S/)')->readOnly(),
                        ])
                        ->columns(3)
                        ->addable(false)
                        ->deletable(true)
                        ->reorderable(false)
                ])
                ->action(function (array $data) {
                    $comprobantesForm = $data['comprobantes'] ?? [];

                    if (empty($comprobantesForm)) {
                        Notification::make()->title('Sin comprobantes')->body('No hay datos válidos para enviar.')->warning()->send();
                        return;
                    }

                    $tenant = Filament::getTenant();
                    $fechaResumen = $data['fecha_resumen'];

                    $saleIds = [];
                    $details = [];

                    foreach ($comprobantesForm as $item) {
                        $saleIds[] = $item['sale_id'];
                        $details[] = [
                            "tipoDoc"         => "03",
                            "serieNro"        => $item['serieNro'],
                            "estado"          => $item['estado'],
                            "clienteTipo"     => $item['clienteTipo'],
                            "clienteNro"      => $item['clienteNro'],
                            "total"           => (float) $item['total'],
                            "mtoOperGravadas" => (float) $item['mtoOperGravadas'],
                            "mtoIGV"          => (float) $item['mtoIGV'],
                        ];
                    }

                    $ultimo = DailySummary::where('restaurant_id', $tenant->id)
                        ->where('tipo_documento', 'Summary')
                        ->where('fecha_resumen', $fechaResumen)
                        ->max('correlativo');

                    $nuevoCorrelativo = str_pad(($ultimo ? (int)$ultimo + 1 : 1), 3, '0', STR_PAD_LEFT);
                    $identificador = "RC-" . str_replace('-', '', $fechaResumen) . "-" . $nuevoCorrelativo;

                    $payload = [
                        "company"         => ["ruc" => $tenant->ruc],
                        "fechaGeneracion" => now()->format('Y-m-d'),
                        "fechaResumen"    => $fechaResumen,
                        "correlativo"     => $nuevoCorrelativo,
                        "details"         => $details
                    ];

                    // 🟢 USAMOS EL SERVICIO
                    $sunatService = app(SunatGreenterApiService::class);
                    $response = $sunatService->sendSummary($payload, $tenant->api_token);

                    // 1. Validamos que la API central respondió con éxito
                    if (!$response['success']) {
                        Notification::make()->title('Error API')->body($response['message'])->danger()->send();
                        return;
                    }

                    // 2. Extraemos el cuerpo de la respuesta de tu API
                    $apiData = $response['data'];

                    // 3. Validamos que SUNAT haya aceptado la trama
                    if (empty($apiData['success'])) {
                        Notification::make()->title('Error en SUNAT')->body('La API rechazó el envío.')->danger()->send();
                        return;
                    }

                    // 4. Guardamos todo, incluyendo el XML y el Hash
                    $xmlBase64 = $apiData['xml'] ?? null;
                    $pathXml = null;

                    // Si hay XML, lo guardamos físicamente en el disco
                    if ($xmlBase64) {
                        $slug = $tenant->slug ?? 'default';
                        $fechaCarpeta = now()->format('Y-m-d');
                        $pathXml = "tenants/{$slug}/resumenes/xml/{$fechaCarpeta}/{$identificador}.xml";
                        Storage::disk('public')->put($pathXml, base64_decode($xmlBase64));
                    }

                    // 🚀 Creamos el Summary con toda la info
                    $summary = DailySummary::create([
                        'restaurant_id'    => $tenant->id,
                        'user_id'          => auth()->id(),
                        'tipo_documento'   => 'Summary_Envio',
                        'fecha_generacion' => now(),
                        'fecha_resumen'    => $fechaResumen,
                        'correlativo'      => $nuevoCorrelativo,
                        'identificador'    => $identificador,
                        'details'          => $details,
                        'ticket'           => $apiData['ticket'] ?? null,
                        'hash'             => $apiData['hash'] ?? null,
                        'path_xml'         => $pathXml,
                        'status_sunat'     => 'procesando',
                    ]);

                    Sale::whereIn('id', $saleIds)->update([
                        'daily_summary_id' => $summary->id,
                        'status_sunat'     => 'procesando'
                    ]);

                    Notification::make()->title('Resumen Enviado Exitosamente')->body("N° de Ticket: {$summary->ticket}.")->success()->send();
                })
        ];
    }

    // ==========================================
    // 🛠️ MÉTODO AUXILIAR PARA LA BÚSQUEDA
    // ==========================================
    private function obtenerBoletasParaRepeater($tenantId, $fecha)
    {
        return Sale::where('restaurant_id', $tenantId)
            ->where('tipo_comprobante', 'Boleta')
            ->whereDate('fecha_emision', $fecha)
            ->whereIn('status_sunat', ['registrado', 'error_api']) // Boletas nuevas
            ->orWhere(function ($query) use ($tenantId, $fecha) {
                // O boletas que fueron marcadas para anulación
                $query->where('restaurant_id', $tenantId)
                    ->where('tipo_comprobante', 'Boleta')
                    ->whereDate('fecha_emision', $fecha)
                    ->where('status_sunat', 'pendiente_anulacion');
            })
            ->whereNull('daily_summary_id')
            ->get()
            ->map(function ($b) {
                return [
                    'sale_id'         => $b->id,
                    'serieNro'        => "{$b->serie}-{$b->correlativo}",
                    'estado'          => ($b->status === 'anulado' || $b->status_sunat === 'pendiente_anulacion') ? "3" : "1",
                    'clienteTipo'     => $b->tipo_documento === 'DNI' ? "1" : "-",
                    'clienteNro'      => $b->numero_documento ?? "00000000",
                    'total'           => $b->total,
                    'mtoOperGravadas' => $b->op_gravada,
                    'mtoIGV'          => $b->monto_igv,
                ];
            })->toArray();
    }

    // ==========================================
    // 📊 TABLA DE HISTORIAL DE TRAMAS ASÍNCRONAS
    // ==========================================
    public function table(Table $table): Table
    {
        return $table
            ->query(DailySummary::query()->latest())
            ->columns([
                TextColumn::make('identificador')
                    ->label('Identificador')
                    ->searchable()
                    ->sortable()
                    ->description(fn(DailySummary $record): string => \Carbon\Carbon::parse($record->created_at)->format('d/m/Y H:i')),

                TextColumn::make('tipo_documento')
                    ->label('Tipo Operación')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Summary_Envio' => 'info',      // Azul para envíos normales
                        'Summary_Anulacion' => 'danger', // Rojo para anulaciones de boleta
                        'Voided'  => 'warning',         // Naranja para bajas de factura
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'Summary_Envio' => 'Resumen (Envío Nuevo)',
                        'Summary_Anulacion' => 'Resumen (Anulación)',
                        'Voided'  => 'Baja de Facturas',
                        default   => $state,
                    }),

                TextColumn::make('ticket')
                    ->label('N° Ticket')
                    ->copyable()
                    ->description(fn(DailySummary $record): string => str()->limit($record->description ?? '', 30)),

                // 🚀 NUEVA COLUMNA: HASH
                TextColumn::make('hash')
                    ->label('Código Hash')
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // Oculto por defecto para no saturar

                TextColumn::make('status_sunat')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'aceptado' => 'success',
                        'procesando' => 'warning',
                        'rechazado', 'error_api', 'anulado' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => ucfirst($state)),
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    // 🟢 ACCIÓN INTELIGENTE: CONSULTAR TICKET
                    TableAction::make('consultar_ticket')
                        ->label('Verificar en SUNAT')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->visible(fn(DailySummary $record) => $record->ticket && $record->status_sunat === 'procesando')
                        ->action(function (DailySummary $record) {
                            $tenant = Filament::getTenant();
                            $payload = [
                                "company" => ["ruc" => $tenant->ruc],
                                "ticket"  => $record->ticket
                            ];

                            $sunatService = app(SunatGreenterApiService::class);

                            // 🚀 DERIVAMOS AL ENDPOINT CORRECTO SEGÚN EL TIPO
                            if ($record->tipo_documento === 'Voided') {
                                $response = $sunatService->checkVoidStatus($payload, $tenant->api_token);
                                $carpetaCdr = 'bajas';
                            } else {
                                $response = $sunatService->checkSummaryStatus($payload, $tenant->api_token);
                                $carpetaCdr = 'resumenes';
                            }

                            if (!$response['success']) {
                                Notification::make()->title('Error')->body($response['message'])->danger()->send();
                                return;
                            }

                            $data = $response['data'];

                            // Control de Ticket aún en proceso
                            $codeObj = $data['cdrResponse']['code'] ?? null;
                            if ($codeObj === '98' || $codeObj === 98) {
                                Notification::make()->title('Aún procesando')->body('SUNAT sigue evaluando el ticket. Intenta más tarde.')->warning()->send();
                                return;
                            }

                            if ($data['success'] ?? false) {
                                $cdrResponse = $data['cdrResponse'] ?? [];
                                $code = $cdrResponse['code'] ?? null;
                                $description = $cdrResponse['description'] ?? 'Aceptado por SUNAT';
                                $cdrBase64 = $data['cdrZip'] ?? null;

                                $isAceptado = ($code == 0 || $code === '0');
                                $nuevoEstado = $isAceptado ? 'aceptado' : 'rechazado';
                                $pathCdrZip = $record->path_cdrZip;

                                // Guardar CDR
                                if ($cdrBase64) {
                                    $slug  = $tenant->slug ?? 'default';
                                    $fecha = now()->format('Y-m-d');
                                    $pathCdrZip = "tenants/{$slug}/{$carpetaCdr}/cdr/{$fecha}/{$record->identificador}.zip";
                                    Storage::disk('public')->put($pathCdrZip, base64_decode($cdrBase64));
                                }

                                // 1. Actualizar el Registro del Resumen/Baja
                                $record->update([
                                    'status_sunat' => $nuevoEstado,
                                    'path_cdrZip'  => $pathCdrZip,
                                    'code'         => $code,
                                    'description'  => $description,
                                    'notes'        => json_encode($cdrResponse['notes'] ?? []),
                                ]);

                                // 2. Lógica Inteligente para actualizar las Ventas Originales
                                if ($isAceptado) {
                                    foreach ($record->sales as $sale) {
                                        // Si es Baja, o si el Resumen llevaba una boleta con orden de anulación
                                        if ($record->tipo_documento === 'Voided' || $sale->status_sunat === 'procesando_anulacion') {
                                            $sale->update(['status_sunat' => 'anulado', 'status' => 'anulado']);
                                        } else {
                                            $sale->update(['status_sunat' => 'aceptado']);
                                        }
                                    }
                                    Notification::make()->title('Operación Aceptada')->body($description)->success()->send();
                                } else {
                                    // Si rechazan el Resumen, marcamos las ventas como rechazadas
                                    $record->sales()->update(['status_sunat' => 'rechazado']);
                                    Notification::make()->title('Operación Rechazada')->body($description)->danger()->send();
                                }
                            } else {
                                Notification::make()->title('Error Interno')->body('La respuesta de SUNAT no pudo ser procesada.')->warning()->send();
                            }
                        }),

                    // 🚀 NUEVA ACCIÓN: DESCARGAR XML
                    TableAction::make('descargar_xml')
                        ->label('Descargar XML')
                        ->icon('heroicon-o-code-bracket-square')
                        ->color('success')
                        ->visible(fn(DailySummary $record) => !empty($record->path_xml))
                        ->action(fn(DailySummary $record) => Storage::disk('public')->download($record->path_xml)),

                    // 🟢 ACCIÓN: DESCARGAR CDR
                    TableAction::make('descargar_cdr')
                        ->label('Descargar CDR (ZIP)')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('success')
                        ->visible(fn(DailySummary $record) => !empty($record->path_cdrZip))
                        ->action(fn(DailySummary $record) => Storage::disk('public')->download($record->path_cdrZip)),

                    // 🟢 ACCIÓN: ANULAR RESUMEN INTERNAMENTE
                    TableAction::make('anular')
                        ->label('Anular Internamente')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn(DailySummary $record) => in_array($record->status_sunat, ['rechazado', 'error_api']))
                        ->requiresConfirmation()
                        ->modalHeading('Anular Operación en el Sistema')
                        ->modalDescription('Liberará los comprobantes asociados para que puedas corregirlos y volver a enviarlos.')
                        ->action(function (DailySummary $record) {
                            $record->update(['status_sunat' => 'anulado']);

                            // Revertimos las ventas a su estado original para reintento
                            foreach ($record->sales as $sale) {
                                $revertirA = ($sale->status === 'anulado') ? 'pendiente_anulacion' : 'registrado';
                                $sale->update([
                                    'daily_summary_id' => null,
                                    'status_sunat' => $revertirA
                                ]);
                            }

                            Notification::make()->title('Operación Cancelada')->body('Comprobantes liberados con éxito.')->success()->send();
                        }),
                ])
            ]);
    }
}
