<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PromotionRuleType: string implements HasLabel
{
    case Days = 'days';
    case TimeRange = 'time_range';
    case Limit = 'limit';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Days => 'Días de la semana',
            self::TimeRange => 'Horario específico',
            self::Limit => 'Límite de stock diario',
        };
    }

    /**
     * Retorna la configuración interna para la Base de Datos.
     * Esto reemplaza el 'match' gigante que tenías en el Resource.
     */
    public function getBehavior(): array
    {
        return match ($this) {
            self::Days => [
                'key' => 'days',
                'operator' => 'in',
                'value' => ['days' => []], // Estructura inicial
            ],
            self::TimeRange => [
                'key' => 'hours',
                'operator' => 'between',
                'value' => ['start' => null, 'end' => null],
            ],
            self::Limit => [
                'key' => 'daily_limit',
                'operator' => '<=',
                'value' => ['limit' => null],
            ],
        };
    }
}