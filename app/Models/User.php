<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

class User extends Authenticatable implements HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url;
    }

    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class);
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->restaurants;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->restaurants()->whereKey($tenant)->exists();
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
}
