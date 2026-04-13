<?php

namespace App\Filament\Restaurants\Pages\Facturacion;

use App\Enums\MotivoNotaCredito;
use App\Models\CreditDebitNote;
use App\Models\Sale;
use App\Services\NotePayloadService;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

// 🟢 IMPORTACIONES NECESARIAS PARA LOS BOTONES Y ACCIONES
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\Auth;

class Comprobantes extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'Facturación';
    protected static ?int $navigationSort = 140;
    protected static ?string $title = 'Comprobantes Emitidos';
    protected static ?string $navigationLabel = 'Comprobantes';
    protected static string $view = 'filament.facturacion.comprobantes';

    public static function canAccess(): bool
    {
        if (! Filament::getTenant()) {
            return false;
        }

        $user = auth()->user();

        if ($user->hasRole('Super Admin')) {
            return false;
        }

        try {
            return $user->hasPermissionTo('ver_comprobantes_rest');
        } catch (\Exception $e) {
            return false;
        }
    }

    // 🟢 CONFIGURACIÓN DE LA TABLA
    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Consultamos las ventas, filtrando solo Boletas y Facturas
                Sale::query()->whereIn('tipo_comprobante', ['Boleta', 'Factura'])->latest()
            )
            ->columns([
                TextColumn::make('fecha_emision')
                    ->label('F. Emisión')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('tipo_comprobante')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Factura' => 'warning',
                        'Boleta'  => 'info',
                        default   => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('nombre_cliente')
                    ->label('Cliente')
                    ->searchable(['nombre_cliente', 'numero_documento'])
                    ->description(fn(Sale $record): string => $record->numero_documento ?? 'Sin documento'),

                TextColumn::make('comprobante')
                    ->label('Documento')
                    ->state(fn(Sale $record): string => "{$record->serie}-{$record->correlativo}")
                    ->searchable(['serie', 'correlativo']),

                TextColumn::make('notas_asociadas')
                    ->label('Notas Asociadas')
                    ->state(function (Sale $record) {
                        // Usamos tu relación directa
                        if ($record->creditDebitNotes->isEmpty()) {
                            return null;
                        }
                        return $record->creditDebitNotes->map(function ($nota) {
                            $tipo = $nota->tipo_nota === '07' ? 'NC' : 'ND';
                            return "{$tipo}: {$nota->serie} - {$nota->correlativo}";
                        })->toArray();
                    })
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('creditDebitNotes', function (Builder $q) use ($search) {
                            $q->where('serie', 'like', "%{$search}%")
                                ->orWhere('correlativo', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable(),

                // ESTADO SUNAT
                TextColumn::make('status_sunat')
                    ->label('Estado SUNAT')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'aceptado' => 'success',
                        'error_api', 'anulado' => 'danger',
                        'rechazado' => 'gray',
                        'procesando', 'enviado' => 'warning',
                        'registrado', 'pendiente', 'no_enviado' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => ucfirst(str_replace('_', ' ', $state))),

                // 🚀 CAMPOS OCULTOS POR DEFECTO PARA AUDITORÍA DE ERRORES
                TextColumn::make('code')
                    ->label('Cód. Respuesta')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Detalle / Mensaje')
                    ->state(fn(Sale $record) => $record->description ?? $record->message ?? 'Sin detalles')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap() // Permite que los errores largos ocupen varias líneas
                    ->searchable(['description', 'message']),
            ])
            ->filters([
                SelectFilter::make('status_sunat')
                    ->label('Estado en SUNAT')
                    ->options([
                        'aceptado'   => 'Aceptado',
                        'registrado' => 'Solo Registrado',
                        'rechazado'  => 'Rechazado',
                        'error_api'  => 'Error de Conexión / API',
                    ]),
            ])
            ->actions([
                ActionGroup::make([
                    // 🟢 BOTÓN: DESCARGAR XML
                    Action::make('descargar_xml')
                        ->label('Descargar XML')
                        ->icon('heroicon-o-code-bracket-square')
                        ->color('success')
                        ->visible(fn(Sale $record) => Auth::user()->can('descargar_comprobantes_xml_cdr_pdf_rest') && !empty($record->path_xml))
                        ->action(fn(Sale $record) => Storage::disk('public')->download($record->path_xml)),
                    // 🟢 BOTÓN: DESCARGAR CDR (ZIP)
                    Action::make('descargar_cdr')
                        ->label('Descargar CDR (ZIP)')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('success')
                        ->visible(fn(Sale $record) => Auth::user()->can('descargar_comprobantes_xml_cdr_pdf_rest') && !empty($record->path_cdrZip))
                        ->action(fn(Sale $record) => Storage::disk('public')->download($record->path_cdrZip)),

                    // 🟢 BOTÓN: DESCARGAR PDF
                    Action::make('descargar_pdf')
                        ->label('Descargar PDF')
                        ->icon('heroicon-o-document')
                        ->color('info')
                        ->visible(fn(Sale $record) => Auth::user()->can('descargar_comprobantes_xml_cdr_pdf_rest') && !empty($record->path_pdf))
                        ->action(fn(Sale $record) => Storage::disk('public')->download($record->path_pdf)),

                    // 🚀 BOTÓN INTELIGENTE: ENVIAR / REENVIAR A SUNAT
                    Action::make('enviar_sunat')
                        ->label(fn(Sale $record) => $record->status_sunat === 'error_api' ? 'Reenviar a SUNAT' : 'Enviar a SUNAT')
                        ->icon(fn(Sale $record) => $record->status_sunat === 'error_api' ? 'heroicon-o-arrow-path' : 'heroicon-o-cloud-arrow-up')
                        ->color(fn(Sale $record) => $record->status_sunat === 'error_api' ? 'warning' : 'primary')
                        // Se muestra si es la primera vez (registrado) o si hubo error de conexión/validación (error_api)
                        ->visible(fn(Sale $record) => Auth::user()->can('enviar_comprobante_sunat_rest') && in_array($record->status_sunat, ['registrado', 'error_api']))
                        ->requiresConfirmation()
                        ->modalHeading(fn(Sale $record) => $record->status_sunat === 'error_api' ? 'Reintentar envío a SUNAT' : 'Enviar Comprobante a SUNAT')
                        ->modalDescription('El sistema detectará si debe enviar el XML existente o generar uno nuevo.')
                        ->action(function (Sale $record) {
                            $tenant = Filament::getTenant();
                            $sunatService = app(\App\Services\SunatGreenterApiService::class);

                            // Comprobamos si el XML físico existe
                            $tieneXml = !empty($record->path_xml) && Storage::disk('public')->exists($record->path_xml);

                            if ($tieneXml) {
                                // ==============================================================
                                // 🟢 ESCENARIO 1: SÍ TENEMOS XML (Fallo de conexión en envío a SUNAT)
                                // ==============================================================
                                $xmlContent = Storage::disk('public')->get($record->path_xml);
                                $xmlBase64 = base64_encode($xmlContent);

                                $tipoDocStr = $record->tipo_comprobante === 'Factura' ? '01' : '03';
                                $correlativoInt = (int) $record->correlativo;
                                $filename = "{$tenant->ruc}-{$tipoDocStr}-{$record->serie}-{$correlativoInt}";

                                // Usamos el endpoint para enviar XML directo
                                $respuesta = $sunatService->sendXmlDirect($xmlBase64, $filename, $tenant->api_token);
                            } else {
                                // Usamos el nuevo servicio para reconstruir el Payload exacto
                                $payloadBuilder = app(\App\Services\InvoicePayloadService::class);
                                $payload = $payloadBuilder->buildFromSale($record, $tenant);

                                $respuesta = $sunatService->sendInvoice($payload, $tenant->api_token);
                            }

                            // ==============================================================
                            // PROCESAMOS LA RESPUESTA (VÁLIDA PARA AMBOS ESCENARIOS)
                            // ==============================================================
                            if (!$respuesta['success']) {
                                $mensaje = $respuesta['error_data']['error'] ?? $respuesta['message'] ?? 'Error de conexión';
                                $record->update([
                                    'status_sunat' => 'error_api',
                                    'message' => $mensaje,
                                    'description' => 'Fallo al intentar comunicar con la API',
                                    'code' => 'API-ERR'
                                ]);
                                Notification::make()->title('Fallo de conexión')->body($mensaje)->danger()->send();
                                return;
                            }

                            $data = $respuesta['data'];

                            // La respuesta puede variar un poco dependiendo si fue directo o reconstruido
                            $apiSuccess = $data['sunatResponse']['success'] ?? ($data['success'] ?? false);

                            if ($apiSuccess) {
                                // SUNAT ACEPTÓ EL COMPROBANTE
                                $cdrBase64   = $data['sunatResponse']['cdrZip'] ?? $data['cdrZip'] ?? null;
                                $apiCode     = $data['sunatResponse']['cdrResponse']['code'] ?? null;
                                $apiDescription = $data['sunatResponse']['cdrResponse']['description'] ?? null;
                                $notes       = $data['sunatResponse']['cdrResponse']['notes'] ?? [];

                                // 🚀 SI VENIMOS DEL ESCENARIO 2 (Nuevo), GUARDAMOS EL XML Y EL HASH
                                if (!$tieneXml) {
                                    $hash = $data['hash'] ?? null;
                                    $xmlBase64Nuevo = $data['xml'] ?? null;

                                    if ($xmlBase64Nuevo) {
                                        $slug  = $tenant->slug ?? 'default';
                                        $fecha = \Carbon\Carbon::parse($record->fecha_emision)->format('Y-m-d');
                                        $tipoDocStr = $record->tipo_comprobante === 'Factura' ? '01' : '03';
                                        $correlativoInt = (int) $record->correlativo;
                                        $pathXml = "tenants/{$slug}/comprobantes/xml/{$fecha}/{$tenant->ruc}-{$tipoDocStr}-{$record->serie}-{$correlativoInt}.xml";

                                        Storage::disk('public')->put($pathXml, base64_decode($xmlBase64Nuevo));

                                        // Actualizamos el objeto en memoria
                                        $record->path_xml = $pathXml;
                                        $record->hash = $hash;
                                    }
                                }

                                $pathCdrZip = $record->path_cdrZip;

                                // Guardamos el CDR ZIP
                                if ($cdrBase64) {
                                    $slug  = $tenant->slug ?? 'default';
                                    $fecha = \Carbon\Carbon::parse($record->fecha_emision)->format('Y-m-d');
                                    $folder = "tenants/{$slug}/comprobantes/cdr/{$fecha}";
                                    $correlativoInt = (int) $record->correlativo;
                                    $nombreBase = "{$tenant->ruc}-{$record->serie}-{$correlativoInt}";
                                    $pathCdrZip = "{$folder}/R-{$nombreBase}.zip";

                                    Storage::disk('public')->put($pathCdrZip, base64_decode($cdrBase64));
                                }

                                // Actualizamos la base de datos con todo
                                $record->update([
                                    'status_sunat' => 'aceptado',
                                    'path_cdrZip'  => $pathCdrZip,
                                    'success'      => true,
                                    'code'         => $apiCode,
                                    'description'  => $apiDescription,
                                    'message'      => 'Comprobante procesado correctamente',
                                    'notes'        => json_encode($notes),
                                    'path_xml'     => $record->path_xml, // Fuerza guardar si es del Escenario 2
                                    'hash'         => $record->hash ?? null,
                                ]);

                                Notification::make()->title('¡Comprobante Aceptado!')->success()->send();
                            } else {
                                // SUNAT RECHAZÓ EL COMPROBANTE
                                $apiCode     = $data['sunatResponse']['error']['code'] ?? null;
                                $apiMessage  = $data['sunatResponse']['error']['message'] ?? null;

                                $record->update([
                                    'status_sunat' => 'rechazado',
                                    'success'      => false,
                                    'code'         => $apiCode,
                                    'message'      => $apiMessage,
                                    'description'  => "Error SUNAT: " . $apiMessage,
                                ]);

                                Notification::make()->title('Rechazado por SUNAT')->body($apiMessage)->danger()->send();
                            }
                        }),

                    // 🟢 BOTÓN: GENERAR NOTA DE CRÉDITO / DÉBITO
                    Action::make('generar_nota')
                        ->label('Generar Nota C/D')
                        ->icon('heroicon-o-document-minus')
                        ->color('warning')
                        ->visible(fn(Sale $record) => Auth::user()->can('emitir_nota_rest') && $record->status_sunat === 'aceptado')
                        ->url(fn(Sale $record) => EmitirNota::getUrl(['record' => $record->id])),

                    // 🟢 BOTÓN: ANULAR COMPROBANTE (Baja o Resumen de anulación)
                    Action::make('anular_comprobante')
                        ->label('Anular Comprobante')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn(Sale $record) => Auth::user()->can('generar_comunicacion_baja_rest') && $record->status_sunat === 'aceptado' && $record->status !== 'anulado')
                        ->form([
                            \Filament\Forms\Components\Textarea::make('motivo')
                                ->label('Motivo de Anulación')
                                ->required()
                                ->minLength(3)
                                ->placeholder('Ej: Error de digitación / Cambio de RUC'),
                        ])
                        ->modalHeading('Enviar Anulación a SUNAT')
                        ->modalDescription('Se enviará la solicitud y se generará un Ticket en la bandeja de Resúmenes y Bajas.')
                        ->modalSubmitActionLabel('Proceder')
                        ->action(function (Sale $record, array $data) {
                            $tenant = Filament::getTenant();
                            $sunatService = app(\App\Services\SunatGreenterApiService::class);

                            $fechaEmision = \Carbon\Carbon::parse($record->fecha_emision)->format('Y-m-d');
                            $fechaHoy = now()->format('Y-m-d');

                            // 1. Generamos un correlativo diario para la Baja o el Resumen
                            $conteoHoy = \App\Models\DailySummary::where('restaurant_id', $tenant->id)
                                ->whereDate('created_at', now())
                                ->count() + 1;
                            $correlativoDocumento = str_pad($conteoHoy, 3, '0', STR_PAD_LEFT);

                            // 2. Lógica para Facturas y Notas vinculadas a Facturas
                            $esParaBaja = in_array($record->tipo_comprobante, ['Factura', 'Nota de Credito Factura', 'Nota de Debito Factura']);

                            if ($esParaBaja) {
                                $tipoDoc = match ($record->tipo_comprobante) {
                                    'Factura' => '01',
                                    'Nota de Credito Factura' => '07',
                                    'Nota de Debito Factura' => '08',
                                    default => '01'
                                };

                                $details = [[
                                    "tipoDoc" => $tipoDoc,
                                    "serie" => $record->serie,
                                    "correlativo" => $record->correlativo,
                                    "motivo" => $data['motivo']
                                ]];

                                $payload = [
                                    "company" => ["ruc" => $tenant->ruc],
                                    "correlativo" => $correlativoDocumento,
                                    "fechaGeneracion" => $fechaEmision,
                                    "fechaComunicacion" => $fechaHoy,
                                    "details" => $details
                                ];

                                $res = $sunatService->sendVoid($payload, $tenant->api_token);
                                $tipoOperacion = 'Comunicación de Baja';
                                $docType = 'Voided';
                            }
                            // 3. Lógica para Boletas y Notas vinculadas a Boletas
                            else {
                                $tipoDoc = match ($record->tipo_comprobante) {
                                    'Boleta' => '03',
                                    'Nota de Credito Boleta' => '07',
                                    'Nota de Debito Boleta' => '08',
                                    default => '03'
                                };

                                $details = [[
                                    "tipoDoc" => $tipoDoc,
                                    "serieNro" => "{$record->serie}-{$record->correlativo}",
                                    "estado" => "3", // 3 = ANULACIÓN
                                    "clienteTipo" => $record->client->tipo_documento ?? '1',
                                    "clienteNro" => $record->numero_documento ?? '00000000',
                                    "total" => $record->total,
                                    "mtoOperGravadas" => $record->op_gravada ?? 0,
                                    "mtoOperInafectas" => 0,
                                    "mtoOperExoneradas" => 0,
                                    "mtoOperExportacion" => 0,
                                    "mtoOtrosCargos" => 0,
                                    "mtoIGV" => $record->monto_igv ?? 0
                                ]];

                                $payload = [
                                    "company" => ["ruc" => $tenant->ruc],
                                    "correlativo" => $correlativoDocumento,
                                    "fechaGeneracion" => $fechaEmision,
                                    "fechaResumen" => $fechaHoy,
                                    "details" => $details
                                ];

                                $res = $sunatService->sendSummary($payload, $tenant->api_token);
                                $tipoOperacion = 'Resumen de Anulación';
                                $docType = 'Summary_Anulacion';
                            }

                            // 4. Procesamos la respuesta del Ticket
                            if ($res['success'] && !empty($res['data']['success'])) {
                                $apiData = $res['data'];
                                $ticket = $apiData['ticket'] ?? null;

                                $hash = $apiData['hash'] ?? null;
                                $xmlBase64 = $apiData['xml'] ?? null;

                                $prefijo = $esParaBaja ? 'RA' : 'RC';
                                $fechaFormat = str_replace('-', '', $fechaHoy);
                                $identificador = "{$prefijo}-{$fechaFormat}-{$correlativoDocumento}";

                                $pathXml = null;
                                if ($xmlBase64) {
                                    $slug = $tenant->slug ?? 'default';
                                    $carpeta = $esParaBaja ? 'bajas' : 'resumenes';
                                    $pathXml = "tenants/{$slug}/{$carpeta}/xml/{$fechaHoy}/{$identificador}.xml";
                                    Storage::disk('public')->put($pathXml, base64_decode($xmlBase64));
                                }

                                $summary = \App\Models\DailySummary::create([
                                    'restaurant_id' => $tenant->id,
                                    'user_id' => auth()->id(),
                                    'fecha_generacion' => $fechaEmision,
                                    'fecha_resumen' => !$esParaBaja ? $fechaHoy : null,
                                    'fecha_comunicacion' => $esParaBaja ? $fechaHoy : null,
                                    'correlativo' => $correlativoDocumento,
                                    'identificador' => $identificador,
                                    'ticket' => $ticket,
                                    'hash' => $hash,
                                    'path_xml' => $pathXml,
                                    'status_sunat' => 'procesando',
                                    'tipo_documento' => $docType,
                                    'details' => $details,
                                    'notes' => [['motivo_anulacion' => $data['motivo']]],
                                ]);

                                $record->update([
                                    'status_sunat' => 'procesando_anulacion',
                                    'motivo_anulacion' => $data['motivo'],
                                    'daily_summary_id' => $summary->id
                                ]);

                                Notification::make()
                                    ->title("{$tipoOperacion} Enviada")
                                    ->body("Ticket: {$ticket}. Ve a la sección de Resúmenes para consultarlo.")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Error de SUNAT')
                                    ->body($res['message'] ?? 'Revisa la conexión.')
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])
            ]);
    }
}
