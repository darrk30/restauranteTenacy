<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasTenants, HasName, FilamentUser, HasAvatar
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',       // 🟢 Usamos 'name' como está en tu BD
        'email',
        'password',
        'avatar_url', // 🟢 Agregado para que puedas guardar el avatar
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ==========================================
    // RELACIONES DE BASE DE DATOS
    // ==========================================

    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class);
    }

    public function cashRegisters()
    {
        return $this->belongsToMany(CashRegister::class);
    }

    public function sesionCashRegisters()
    {
        return $this->hasMany(SessionCashRegister::class);
    }

    public function cashRegisterMovements()
    {
        return $this->hasMany(CashRegisterMovement::class);
    }

    public function conceptoCajas()
    {
        return $this->hasMany(ConceptoCaja::class);
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }
    
    public function dailySummaries()
    {
        return $this->hasMany(DailySummary::class);
    }
    // ==========================================
    // INTERFAZ DE FILAMENT (TEXTOS E IMÁGENES)
    // ==========================================

    public function getFilamentName(): string
    {
        // 🟢 CORREGIDO: Usamos 'name' para evitar que Filament colapse buscando columnas que no existen
        return $this->name;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url;
    }

    // ==========================================
    // LÓGICA DE MULTI-EMPRESA (TENANCY)
    // ==========================================

    public function getTenants(Panel $panel): Collection
    {
        return $this->restaurants;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->restaurants()->whereKey($tenant)->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
