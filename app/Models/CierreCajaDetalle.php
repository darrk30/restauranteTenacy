<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CierreCajaDetalle extends Model
{
    protected $fillable = [
        'session_cash_register_id',
        'payment_method_id',
        'monto_sistema',
        'monto_cajero',
        'diferencia',
    ];

    public function sessionCashRegister()
    {
        return $this->belongsTo(SessionCashRegister::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

}
