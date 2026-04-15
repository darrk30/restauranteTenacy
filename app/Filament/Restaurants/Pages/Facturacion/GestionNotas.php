<?php

namespace App\Filament\Restaurants\Pages\Facturacion;

use App\Models\CreditDebitNote;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GestionNotas extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-minus';
    protected static ?string $navigationLabel = 'Notas C/D';
    protected static ?string $title = 'Notas de Crédito y Débito';
    protected static ?string $navigationGroup = 'Facturación';
    protected static ?int $navigationSort = 150;
    protected static string $view = 'filament.facturacion.gestion-notas';

    
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
            return $user->hasPermissionTo('ver_notas_creditos_debitos_rest');
        } catch (\Exception $e) {
            return false;
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(CreditDebitNote::query()->latest()) // 🟢 Datos base de la tabla
            ->columns([
                TextColumn::make('fecha_emision')
                    ->label('Fecha Emisión')
                    ->date('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('comprobante')
                    ->label('Nota C/D')
                    ->state(fn (CreditDebitNote $record): string => "{$record->serie}-{$record->correlativo}")
                    ->searchable(['serie', 'correlativo'])
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('sale.comprobante')
                    ->label('Doc. Afectado')
                    ->state(fn (CreditDebitNote $record): string => "{$record->sale->serie}-{$record->sale->correlativo}")
                    ->description(fn (CreditDebitNote $record): string => $record->des_motivo)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable()
                    ->alignment('right'),

                TextColumn::make('status_sunat')
                    ->label('Estado SUNAT')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'aceptado'   => 'success',
                        'registrado' => 'warning',
                        'rechazado'  => 'danger',
                        'error_api'  => 'danger',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
            ])
            ->filters([
                //
            ])
            ->actions([
                // 🟢 ACCIÓN: PDF
                Action::make('descargar_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('danger')
                    ->visible(fn() => Auth::user()->can('descargar_notas_xml_cdr_pdf_rest'))
                    ->url(fn (CreditDebitNote $record) => route('notas.print.ticket', ['nota' => $record->id]))
                    ->openUrlInNewTab(),

                // 🟢 ACCIÓN: XML
                Action::make('descargar_xml')
                    ->label('XML')
                    ->icon('heroicon-o-code-bracket')
                    ->color('warning')
                    ->visible(fn (CreditDebitNote $record) => Auth::user()->can('descargar_notas_xml_cdr_pdf_rest') && !empty($record->path_xml))
                    ->action(fn (CreditDebitNote $record) => Storage::disk('public')->download($record->path_xml)),

                // 🟢 ACCIÓN: CDR
                Action::make('descargar_cdr')
                    ->label('CDR')
                    ->icon('heroicon-o-archive-box')
                    ->color('success')
                    ->visible(fn (CreditDebitNote $record) => Auth::user()->can('descargar_notas_xml_cdr_pdf_rest') && !empty($record->path_cdrZip))
                    ->action(fn (CreditDebitNote $record) => Storage::disk('public')->download($record->path_cdrZip)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}