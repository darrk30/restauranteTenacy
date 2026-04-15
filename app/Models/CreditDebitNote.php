<?php

namespace App\Models;

use App\Enums\MotivoNotaCredito;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditDebitNote extends Model
{
    use HasFactory;

    /**
     * Los atributos que se pueden asignar masivamente.
     */
    protected $fillable = [
        'restaurant_id',
        'user_id',
        'sale_id',
        
        // Datos del documento
        'tipo_nota',
        'serie',
        'correlativo',
        'fecha_emision',
        
        // Motivo
        'cod_motivo',
        'des_motivo',
        
        // Totales Monetarios
        'op_gravada',
        'monto_igv',
        'total',
        
        // Detalles en JSON
        'details',
        
        // Comunicación SUNAT
        'status_sunat',
        'success',
        'hash',
        'path_xml',
        'path_cdrZip',
        'code',
        'description',
        'notes',
        'error_message',
        'qr_data',
    ];

    /**
     * Los atributos que deben ser casteados a tipos nativos.
     */
    protected $casts = [
        'fecha_emision' => 'datetime',
        'op_gravada'    => 'decimal:2',
        'monto_igv'     => 'decimal:2',
        'total'         => 'decimal:2',
        'details'       => 'array',
        'notes'         => 'array',
        'success'       => 'boolean',
        'cod_motivo'    => MotivoNotaCredito::class,
    ];

    // ==========================================
    // RELACIONES
    // ==========================================

    /**
     * Restaurante al que pertenece la nota.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Usuario/Cajero que emitió la nota.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Comprobante original (Factura o Boleta) al que afecta esta nota.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}