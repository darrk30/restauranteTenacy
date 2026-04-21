<?php

namespace App\Filament\Restaurants\Pages\Facturacion;

use App\Models\CashRegisterMovement;
use App\Models\DailySummary;
use App\Models\Sale;
use App\Services\SunatGreenterApiService;
use App\Traits\ManjoStockProductos;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    public function table(Table $table): Table
    {
        // 1. Consulta original de Ventas
        $ventas = \App\Models\Sale::query()
            ->select(
                'id',
                'restaurant_id',
                'created_at',
                'fecha_emision',
                'tipo_comprobante',
                'tipo_comprobante as tipo_comprobante_original',
                'serie',
                'correlativo',
                'nombre_cliente',
                'numero_documento',
                'total',
                'status',
                'status_sunat',
                'code',
                'description',
                'message',
                'path_xml',
                'path_cdrZip',
                'path_pdf',
                DB::raw("'venta' as tipo_registro")
            )
            ->whereIn('tipo_comprobante', ['Boleta', 'Factura']);

        // 2. Consulta de Notas
        $notas = \App\Models\CreditDebitNote::query()
            ->join('sales', 'credit_debit_notes.sale_id', '=', 'sales.id')
            ->select(
                DB::raw('(CAST(credit_debit_notes.id AS SIGNED) * -1) as id'),
                'credit_debit_notes.restaurant_id',
                'credit_debit_notes.created_at',
                'credit_debit_notes.fecha_emision',
                DB::raw("IF(credit_debit_notes.tipo_nota = '07', 'Nota de Crédito', 'Nota de Débito') as tipo_comprobante"),
                'sales.tipo_comprobante as tipo_comprobante_original',
                'credit_debit_notes.serie',
                'credit_debit_notes.correlativo',
                'sales.nombre_cliente',
                'sales.numero_documento',
                'credit_debit_notes.total',
                DB::raw("'completado' as status"),
                'credit_debit_notes.status_sunat',
                'credit_debit_notes.code',
                'credit_debit_notes.description',
                DB::raw("credit_debit_notes.error_message as message"),
                'credit_debit_notes.path_xml',
                'credit_debit_notes.path_cdrZip',
                DB::raw("NULL as path_pdf"),
                DB::raw("'nota' as tipo_registro")
            );

        return $table
            ->query(
                \App\Models\Sale::query()->fromSub($ventas->union($notas), 'sales')
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('fecha_emision')->label('F. Emisión')->dateTime('d/m/Y H:i')->sortable(),

                TextColumn::make('tipo_comprobante')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Factura' => 'warning',
                        'Boleta'  => 'info',
                        'Nota de Crédito' => 'danger',
                        'Nota de Débito' => 'danger',
                        default   => 'gray',
                    })->toggleable(false),
                TextColumn::make('nombre_cliente')->label('Cliente')->searchable(['nombre_cliente', 'numero_documento'])->description(fn(Sale $record): string => $record->numero_documento ?? 'Sin documento'),
                TextColumn::make('comprobante')->label('Documento')->state(fn(Sale $record): string => "{$record->serie}-{$record->correlativo}")->searchable(['serie', 'correlativo']),

                TextColumn::make('notas_asociadas')
                    ->label('Notas Asociadas')
                    ->state(function (Sale $record) {
                        if (($record->tipo_registro ?? 'venta') === 'nota') return null;

                        $realSale = \App\Models\Sale::find($record->id);
                        if (!$realSale || $realSale->creditDebitNotes->isEmpty()) return null;

                        return $realSale->creditDebitNotes->map(function ($nota) {
                            $tipo = $nota->tipo_nota === '07' ? 'NC' : 'ND';
                            return "{$tipo}: {$nota->serie} - {$nota->correlativo}";
                        })->toArray();
                    })->toggleable(true),

                TextColumn::make('total')->label('Total')->money('PEN')->sortable(),
                TextColumn::make('status_sunat')
                    ->label('Estado SUNAT')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'aceptado' => 'success',
                        'error_api', 'anulado', 'rechazado' => 'danger',
                        'procesando', 'enviado' => 'warning',
                        'registrado', 'pendiente', 'no_enviado', 'procesando_anulacion' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => ucfirst(str_replace('_', ' ', $state))),
                TextColumn::make('code')->label('Cód. API')->toggleable(true),
                TextColumn::make('description')->label('Descripcion')->state(fn(Sale $record) => $record->description ?? $record->message ?? 'Sin detalles')->toggleable(true)->wrap(),
                TextColumn::make('message')->label('Mensaje')->state(fn(Sale $record) => $record->message ?? $record->message ?? 'Sin mensaje')->toggleable(true)->wrap(),
            ])
            ->filters([
                // Filtro 1: Comprobantes o Notas
                \Filament\Tables\Filters\SelectFilter::make('tipo_registro')
                    ->label('Vista de Documentos:')
                    ->options([
                        'venta' => 'Solo Comprobantes',
                        'nota'  => 'Solo Notas',
                    ]),

                // Filtro 2: Estado de SUNAT
                \Filament\Tables\Filters\SelectFilter::make('status_sunat')
                    ->label('Estado en SUNAT')
                    ->options([
                        'aceptado'   => 'Aceptado',
                        'registrado' => 'Solo Registrado',
                        'rechazado'  => 'Rechazado',
                        'error_api'  => 'Error API',
                        'anulado'    => 'Anulado',
                    ]),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->actions([
                ActionGroup::make([
                    Action::make('descargar_xml')
                        ->label('Descargar XML')
                        ->icon('heroicon-o-code-bracket-square')
                        ->color('success')
                        ->visible(fn(Sale $record) => Auth::user()->can('descargar_comprobantes_xml_cdr_pdf_rest') && !empty($record->path_xml))
                        ->action(function (Sale $record) {
                            $realId = $record->tipo_registro === 'nota' ? $record->id - 9000000 : $record->id;
                            $modelo = $record->tipo_registro === 'nota' ? \App\Models\CreditDebitNote::find($realId) : \App\Models\Sale::find($realId);
                            return Storage::disk('public')->download($modelo->path_xml);
                        }),

                    Action::make('descargar_cdr')
                        ->label('Descargar CDR (ZIP)')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('success')
                        ->visible(fn(Sale $record) => Auth::user()->can('descargar_comprobantes_xml_cdr_pdf_rest') && !empty($record->path_cdrZip))
                        ->action(function (Sale $record) {
                            $realId = $record->tipo_registro === 'nota' ? $record->id - 9000000 : $record->id;
                            $modelo = $record->tipo_registro === 'nota' ? \App\Models\CreditDebitNote::find($realId) : \App\Models\Sale::find($realId);
                            return Storage::disk('public')->download($modelo->path_cdrZip);
                        }),

                    Action::make('descargar_pdf')
                        ->label('Descargar PDF')
                        ->icon('heroicon-o-document')
                        ->color('info')
                        ->visible(fn(Sale $record) => Auth::user()->can('descargar_comprobantes_xml_cdr_pdf_rest') && !empty($record->path_pdf) && $record->tipo_registro === 'venta')
                        ->action(fn(Sale $record) => Storage::disk('public')->download($record->path_pdf)),

                    Action::make('enviar_sunat')
                        ->label(fn(Sale $record) => $record->status_sunat === 'error_api' ? 'Reenviar a SUNAT' : 'Enviar a SUNAT')
                        ->icon(fn(Sale $record) => $record->status_sunat === 'error_api' ? 'heroicon-o-arrow-path' : 'heroicon-o-cloud-arrow-up')
                        ->color(fn(Sale $record) => $record->status_sunat === 'error_api' ? 'warning' : 'primary')
                        ->visible(
                            fn(Sale $record) =>
                            Auth::user()->can('enviar_comprobante_sunat_rest') &&
                                in_array($record->status_sunat, ['registrado', 'error_api']) &&
                                $record->tipo_registro === 'venta'
                        )
                        ->requiresConfirmation()
                        ->action(function (Sale $record) {
                            $tenant = Filament::getTenant();
                            $sunatService = app(\App\Services\SunatGreenterApiService::class);
                            $tieneXml = !empty($record->path_xml) && Storage::disk('public')->exists($record->path_xml);

                            if ($tieneXml) {
                                $xmlContent = Storage::disk('public')->get($record->path_xml);
                                $xmlBase64 = base64_encode($xmlContent);
                                $tipoDocStr = $record->tipo_comprobante === 'Factura' ? '01' : '03';
                                $filename = "{$tenant->ruc}-{$tipoDocStr}-{$record->serie}-" . (int)$record->correlativo;
                                $respuesta = $sunatService->sendXmlDirect($xmlBase64, $filename, $tenant->api_token);
                            } else {
                                $payloadBuilder = app(\App\Services\InvoicePayloadService::class);
                                $payload = $payloadBuilder->buildFromSale($record, $tenant);
                                $respuesta = $sunatService->sendInvoice($payload, $tenant->api_token);
                            }

                            if (!$respuesta['success']) {
                                $record->update(['status_sunat' => 'error_api', 'message' => $respuesta['message'] ?? 'Error']);
                                Notification::make()->title('Fallo')->body($respuesta['message'] ?? '')->danger()->send();
                                return;
                            }

                            if ($respuesta['data']['sunatResponse']['success'] ?? false) {
                                // 1. Extraemos la descripción y las notas (observaciones) de SUNAT
                                $cdrResponse = $respuesta['data']['sunatResponse']['cdrResponse'] ?? [];
                                $descripcionSunat = $cdrResponse['description'] ?? 'Aceptado por SUNAT';
                                $observaciones = $cdrResponse['notes'] ?? [];

                                // 2. Si hay observaciones, las concatenamos al mensaje
                                $mensajeFinal = $descripcionSunat;
                                if (!empty($observaciones)) {
                                    $mensajeFinal .= " | Observaciones: " . implode(' - ', $observaciones);
                                }

                                // 3. Actualizamos el registro en la base de datos
                                $record->update([
                                    'status_sunat' => 'aceptado',
                                    'success'      => true,
                                    'description'  => $descripcionSunat,
                                    'message'      => $mensajeFinal
                                ]);

                                // 4. Mostramos el mensaje real en la notificación verde
                                Notification::make()
                                    ->title('Aceptado')
                                    ->body($descripcionSunat)
                                    ->success()
                                    ->send();
                            }
                        }),

                    Action::make('generar_nota')
                        ->label('Generar Nota C/D')
                        ->icon('heroicon-o-document-minus')
                        ->color('warning')
                        ->visible(
                            fn(Sale $record) =>
                            Auth::user()->can('emitir_nota_rest') &&
                                $record->status_sunat === 'aceptado' &&
                                $record->tipo_registro === 'venta'
                        )
                        ->url(fn(Sale $record) => \App\Filament\Restaurants\Pages\Facturacion\EmitirNota::getUrl(['record' => $record->id])),

                    Action::make('anular_comprobante')
                        ->label('Anular')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(
                            fn(Sale $record) =>
                            Auth::user()->can('generar_comunicacion_baja_rest') &&
                                in_array($record->status_sunat, ['aceptado']) &&
                                $record->status !== 'anulado'
                        )
                        ->form(function (Sale $record) {
                            $campos = [
                                \Filament\Forms\Components\Textarea::make('motivo')->label('Motivo de Anulación')->required()->minLength(3),
                            ];
                            if ($record->tipo_registro === 'venta') {
                                $realSale = \App\Models\Sale::find($record->id);
                                if ($realSale) {
                                    $hayStock = $realSale->details()->whereHas('product', fn($q) => $q->where('control_stock', true))->exists() || $realSale->details()->whereNotNull('promotion_id')->exists();
                                    if ($hayStock) {
                                        $campos[] = \Filament\Forms\Components\Toggle::make('restablecer_stock')->label('¿Restablecer stock?')->default(true)->onColor('success');
                                    }
                                }
                            }
                            return $campos;
                        })
                        ->modalHeading('Anular Comprobante')
                        ->action(function (Sale $record, array $data) {
                            $tenant = Filament::getTenant();
                            $sunatService = app(\App\Services\SunatGreenterApiService::class);

                            $isNota = $record->tipo_registro === 'nota';
                            $realId = $isNota ? abs($record->id) : $record->id;

                            $modeloReal = $isNota ? \App\Models\CreditDebitNote::with('sale.client')->find($realId) : \App\Models\Sale::with('client')->find($realId);

                            $fechaEmision = \Carbon\Carbon::parse($modeloReal->fecha_emision)->format('Y-m-d');
                            $fechaHoy = now()->format('Y-m-d');
                            $correlativoDocumento = str_pad(\App\Models\DailySummary::where('restaurant_id', $tenant->id)->whereDate('created_at', now())->count() + 1, 3, '0', STR_PAD_LEFT);

                            $esParaBaja = $record->tipo_comprobante_original === 'Factura';

                            if ($isNota) {
                                $tipoDoc = $record->tipo_comprobante === 'Nota de Crédito' ? '07' : '08';
                                $clienteTipo = $modeloReal->sale->client->tipo_documento ?? '1';
                                $clienteNro = $modeloReal->sale->numero_documento ?? '00000000';
                            } else {
                                $tipoDoc = $record->tipo_comprobante === 'Factura' ? '01' : '03';
                                $clienteTipo = $modeloReal->client->tipo_documento ?? '1';
                                $clienteNro = $modeloReal->numero_documento ?? '00000000';
                            }

                            if ($esParaBaja) {
                                $details = [[
                                    'tipoDoc' => $tipoDoc,
                                    'serie' => $modeloReal->serie,
                                    'correlativo' => $modeloReal->correlativo,
                                    'motivo' => $data['motivo']
                                ]];
                                $payload = ['company' => ['ruc' => $tenant->ruc], 'correlativo' => $correlativoDocumento, 'fechaGeneracion' => $fechaEmision, 'fechaComunicacion' => $fechaHoy, 'details' => $details];
                                $res = $sunatService->sendVoid($payload, $tenant->api_token);
                                $docType = 'Voided';
                            } else {
                                $details = [[
                                    'tipoDoc' => $tipoDoc,
                                    'serieNro' => "{$modeloReal->serie}-{$modeloReal->correlativo}",
                                    'estado' => '3',
                                    'clienteTipo' => $clienteTipo,
                                    'clienteNro' => $clienteNro,
                                    'total' => $modeloReal->total,
                                    'mtoOperGravadas' => $modeloReal->op_gravada ?? 0,
                                    'mtoOperInafectas' => 0,
                                    'mtoOperExoneradas' => 0,
                                    'mtoOperExportacion' => 0,
                                    'mtoOtrosCargos' => 0,
                                    'mtoIGV' => $modeloReal->monto_igv ?? 0,
                                ]];
                                $payload = ['company' => ['ruc' => $tenant->ruc], 'correlativo' => $correlativoDocumento, 'fechaGeneracion' => $fechaEmision, 'fechaResumen' => $fechaHoy, 'details' => $details];
                                $res = $sunatService->sendSummary($payload, $tenant->api_token);
                                $docType = 'Summary_Anulacion';
                            }

                            if ($res['success'] && !empty($res['data']['success'])) {
                                $apiData = $res['data'];
                                $identificador = ($esParaBaja ? 'RA' : 'RC') . "-" . str_replace('-', '', $fechaHoy) . "-{$correlativoDocumento}";

                                $pathXml = null;
                                if (!empty($apiData['xml'])) {
                                    $pathXml = "tenants/" . ($tenant->slug ?? 'default') . "/" . ($esParaBaja ? 'bajas' : 'resumenes') . "/xml/{$fechaHoy}/{$identificador}.xml";
                                    Storage::disk('public')->put($pathXml, base64_decode($apiData['xml']));
                                }

                                DB::beginTransaction();
                                try {
                                    $summary = \App\Models\DailySummary::create([
                                        'restaurant_id' => $tenant->id,
                                        'user_id' => Auth::id(),
                                        'fecha_generacion' => $fechaEmision,
                                        'fecha_resumen' => !$esParaBaja ? $fechaHoy : null,
                                        'fecha_comunicacion' => $esParaBaja ? $fechaHoy : null,
                                        'correlativo' => $correlativoDocumento,
                                        'identificador' => $identificador,
                                        'ticket' => $apiData['ticket'] ?? null,
                                        'path_xml' => $pathXml,
                                        'status_sunat' => 'procesando',
                                        'tipo_documento' => $docType,
                                        'details' => $details
                                    ]);

                                    if ($isNota) {
                                        $modeloReal->update(['status_sunat' => 'procesando_anulacion']);
                                    } else {
                                        $modeloReal->update(['status_sunat' => 'procesando_anulacion', 'status' => 'anulado', 'motivo_anulacion' => $data['motivo'], 'daily_summary_id' => $summary->id]);
                                    }

                                    if (!$isNota && ($data['restablecer_stock'] ?? false)) {
                                        $modeloReal->loadMissing(['details.product.unit', 'details.variant', 'details.promotion.promotionproducts.product.unit']);
                                        $stockManager = new class {
                                            use \App\Traits\ManjoStockProductos;
                                        };
                                        $stockManager->reverseVenta($modeloReal, "Anulación SUNAT: {$modeloReal->serie}-{$modeloReal->correlativo}");
                                    }

                                    \App\Models\CashRegisterMovement::where('referencia_type', get_class($modeloReal))
                                        ->where('referencia_id', $modeloReal->id)->where('status', 'aprobado')->update(['status' => 'anulado']);

                                    DB::commit();
                                    Notification::make()->title("Anulación Enviada")->body("Ticket generado.")->success()->send();
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                    Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                                }
                            } else {
                                Notification::make()->title('Error de SUNAT')->body($res['message'] ?? 'Error')->danger()->send();
                            }
                        })
                ])
            ]);
    }
}
