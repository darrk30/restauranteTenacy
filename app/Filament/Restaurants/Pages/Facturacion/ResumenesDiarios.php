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
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Set;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Auth;

class ResumenesDiarios extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationGroup = 'Facturación';
    protected static ?int $navigationSort = 145;
    protected static ?string $title = 'Bandeja de Resúmenes y Bajas';
    protected static string $view = 'filament.facturacion.resumenes-diarios';


    public static function canAccess(): bool
    {
        if (! Filament::getTenant()) {
            return false;
        }

        $user = Auth::user();

        if ($user->hasRole('Super Admin')) {
            return false;
        }

        try {
            return $user->hasPermissionTo('ver_resumenes_diarios_rest');
        } catch (\Exception $e) {
            return false;
        }
    }

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
                ->visible(fn() => Auth::user()->can('generar_resumen_diario_rest'))
                ->modalHeading('Revisión de Resumen Diario')
                ->modalDescription('Verifica los comprobantes que se enviarán. La fecha de envío será la de hoy.')
                ->modalSubmitActionLabel('Confirmar y Enviar a SUNAT')
                ->form([
                    // 🔍 Fecha para BUSCAR las boletas en la BD
                    DatePicker::make('fecha_comprobantes')
                        ->label('Buscar boletas de la fecha:')
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

                    // 📝 REPEATER ORIGINAL
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
                        ->visible(fn(Get $get) => !empty($get('comprobantes')))
                ])
                ->action(function (array $data) {
                    $comprobantesForm = $data['comprobantes'] ?? [];

                    if (empty($comprobantesForm)) {
                        Notification::make()->title('Sin comprobantes')->warning()->send();
                        return;
                    }

                    $tenant = Filament::getTenant();
                    $fechaBoletas = $data['fecha_comprobantes']; // Fecha de referencia de las boletas
                    $fechaGeneracion = now()->format('Y-m-d');  // 🟢 HOY: Esto soluciona el error 2671

                    $saleIds = [];
                    $details = [];

                    foreach ($comprobantesForm as $item) {
                        $saleIds[] = $item['sale_id'];
                        $details[] = [
                            "tipoDoc"         => "03",
                            "serieNro"         => $item['serieNro'],
                            "estado"           => $item['estado'],
                            "clienteTipo"     => $item['clienteTipo'],
                            "clienteNro"       => $item['clienteNro'],
                            "total"           => (float) $item['total'],
                            "mtoOperGravadas" => (float) $item['mtoOperGravadas'],
                            "mtoIGV"           => (float) $item['mtoIGV'],
                        ];
                    }

                    // Calculamos correlativo basado en la fecha de hoy
                    $ultimo = DailySummary::where('restaurant_id', $tenant->id)
                        ->where('fecha_generacion', $fechaGeneracion)
                        ->max('correlativo');

                    $nuevoCorrelativo = str_pad(($ultimo ? (int)$ultimo + 1 : 1), 3, '0', STR_PAD_LEFT);
                    $identificador = "RC-" . str_replace('-', '', $fechaGeneracion) . "-" . $nuevoCorrelativo;

                    $payload = [
                        "company"         => ["ruc" => $tenant->ruc],
                        "fechaGeneracion" => $fechaBoletas, // cbc:IssueDate (Debe ser Hoy)
                        "fechaResumen"    => $fechaGeneracion,    // cbc:ReferenceDate (Día de las boletas)
                        "correlativo"     => $nuevoCorrelativo,
                        "details"         => $details
                    ];

                    $sunatService = app(SunatGreenterApiService::class);
                    $response = $sunatService->sendSummary($payload, $tenant->api_token);

                    // 🔴 MANEJO DE ERROR DE API (PERSISTENTE)
                    if (!$response['success']) {
                        Notification::make()
                            ->title('Error de Comunicación')
                            ->body($response['message'] ?? 'Error desconocido')
                            ->danger()
                            ->persistent()
                            ->send();
                        return;
                    }

                    $apiData = $response['data'];

                    // 🔴 MANEJO DE ERROR SUNAT (PERSISTENTE)
                    if (isset($apiData['success']) && $apiData['success'] === false) {
                        $errCode = $apiData['error']['code'] ?? '---';
                        $errMsg = $apiData['error']['message'] ?? 'Error desconocido';

                        Notification::make()
                            ->title("Error SUNAT [{$errCode}]")
                            ->body($errMsg)
                            ->danger()
                            ->persistent()
                            ->send();
                        return;
                    }

                    // 🟢 GUARDADO EXITOSO
                    $xmlBase64 = $apiData['xml'] ?? null;
                    $pathXml = null;

                    if ($xmlBase64) {
                        $slug = $tenant->slug ?? 'default';
                        $pathXml = "tenants/{$slug}/resumenes/xml/{$fechaGeneracion}/{$identificador}.xml";
                        Storage::disk('public')->put($pathXml, base64_decode($xmlBase64));
                    }

                    $summary = DailySummary::create([
                        'restaurant_id'    => $tenant->id,
                        'user_id'          => auth()->id(),
                        'tipo_documento'   => 'Summary_Envio',
                        'fecha_generacion' => $fechaGeneracion,
                        'fecha_resumen'    => $fechaBoletas,
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

                    Notification::make()
                        ->title('Resumen Enviado')
                        ->body("Ticket: {$summary->ticket}")
                        ->success()
                        ->send();
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
                        ->visible(fn(DailySummary $record) => Auth::user()->can('consultar_tiket_resumen_diario_rest') && $record->ticket && $record->status_sunat === 'procesando')
                        ->action(function (DailySummary $record) {
                            $tenant = Filament::getTenant();
                            $payload = [
                                "company" => ["ruc" => $tenant->ruc],
                                "ticket"  => $record->ticket
                            ];

                            $sunatService = app(\App\Services\SunatGreenterApiService::class);

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

                                \Illuminate\Support\Facades\DB::beginTransaction();
                                try {
                                    // 1. Actualizar el Registro del Resumen/Baja
                                    $record->update([
                                        'status_sunat' => $nuevoEstado,
                                        'path_cdrZip'  => $pathCdrZip,
                                        'code'         => $code,
                                        'description'  => $description,
                                        'notes'        => json_encode($cdrResponse['notes'] ?? []),
                                    ]);

                                    // 2. Lógica Inteligente para actualizar Ventas Y NOTAS Originales
                                    if ($isAceptado) {
                                        // Extraemos los detalles del DailySummary (ahí guardamos qué documentos se mandaron)
                                        $detalles = is_array($record->details) ? $record->details : json_decode($record->details, true);

                                        if (!empty($detalles)) {
                                            foreach ($detalles as $detalle) {
                                                $tipoDoc = $detalle['tipoDoc'] ?? null;

                                                // Para bajas es 'serie' y 'correlativo'. Para resumenes es 'serieNro' (ej. B001-1)
                                                if ($record->tipo_documento === 'Voided') {
                                                    $serie = $detalle['serie'] ?? null;
                                                    $correlativo = $detalle['correlativo'] ?? null;
                                                } else {
                                                    $partes = explode('-', $detalle['serieNro'] ?? '');
                                                    $serie = $partes[0] ?? null;
                                                    $correlativo = $partes[1] ?? null;
                                                    $estadoOperacion = $detalle['estado'] ?? null; // '3' significa Anulación
                                                }

                                                if ($serie && $correlativo) {
                                                    // Es una Nota (Crédito o Débito)
                                                    if (in_array($tipoDoc, ['07', '08'])) {
                                                        $nota = \App\Models\CreditDebitNote::where('restaurant_id', $tenant->id)
                                                            ->where('serie', $serie)
                                                            ->where('correlativo', $correlativo)
                                                            ->first();

                                                        if ($nota) {
                                                            if ($record->tipo_documento === 'Voided' || ($record->tipo_documento === 'Summary_Anulacion' && $estadoOperacion == '3')) {
                                                                $nota->update(['status_sunat' => 'anulado']);
                                                            } else {
                                                                $nota->update(['status_sunat' => 'aceptado']);
                                                            }
                                                        }
                                                    }
                                                    // Es una Venta (Factura o Boleta)
                                                    else {
                                                        $venta = \App\Models\Sale::where('restaurant_id', $tenant->id)
                                                            ->where('serie', $serie)
                                                            ->where('correlativo', $correlativo)
                                                            ->first();

                                                        if ($venta) {
                                                            if ($record->tipo_documento === 'Voided' || ($record->tipo_documento === 'Summary_Anulacion' && $estadoOperacion == '3')) {
                                                                $venta->update(['status_sunat' => 'anulado', 'status' => 'anulado']);
                                                            } else {
                                                                $venta->update(['status_sunat' => 'aceptado']);
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        \Illuminate\Support\Facades\DB::commit();
                                        Notification::make()->title('Operación Aceptada')->body($description)->success()->send();
                                    } else {
                                        // Si rechazan el Resumen/Baja, regresamos las ventas/notas a "aceptado" (porque SUNAT no aprobó la anulación)
                                        // O si era un resumen de envío nuevo, a "rechazado". 
                                        // Por seguridad, usaremos la lógica actual que tienes para las ventas.
                                        $record->sales()->update(['status_sunat' => 'rechazado']);
                                        \Illuminate\Support\Facades\DB::commit();
                                        Notification::make()->title('Operación Rechazada')->body($description)->danger()->send();
                                    }
                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\DB::rollBack();
                                    Notification::make()->title('Error interno')->body('No se pudo actualizar el estado de los comprobantes.')->danger()->send();
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
                        ->visible(fn(DailySummary $record) => Auth::user()->can('descargar_resumenes_xml_cdr_rest') && !empty($record->path_xml))
                        ->action(fn(DailySummary $record) => Storage::disk('public')->download($record->path_xml)),

                    // 🟢 ACCIÓN: DESCARGAR CDR
                    TableAction::make('descargar_cdr')
                        ->label('Descargar CDR (ZIP)')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('success')
                        ->visible(fn(DailySummary $record) => Auth::user()->can('descargar_resumenes_xml_cdr_rest') && !empty($record->path_cdrZip))
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
