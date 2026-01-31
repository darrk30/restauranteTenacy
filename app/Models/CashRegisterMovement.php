<?php

namespace App\Models;

use App\Observers\CashRegisterMovementObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([CashRegisterMovementObserver::class])]
class CashRegisterMovement extends Model
{
    protected $fillable = [
        'session_cash_register_id',
        'payment_method_id',
        'usuario_id',
        'tipo',
        'motivo',
        'monto',
        'observacion',
        'referencia_type',
        'referencia_id',
    ];

    public function sessionCashRegister()
    {
        return $this->belongsTo(SessionCashRegister::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }



}
