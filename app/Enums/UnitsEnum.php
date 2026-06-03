<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UnitsEnum: string implements HasColor, HasLabel
{
    case Kg = 'Kg';
    case Case = 'Case';
    case Pcs = 'Pcs';
    case Ltr = 'Ltr';
    case Each = 'Each';
    case Bottle = 'Bottle';
    case Bags = 'Bags';
    case Feet = 'Feet';
    case Meter = 'Meter';
    case NONE = '-';

    public static function resolve(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($enum = self::tryFrom($value)) {
            return $enum;
        }

        foreach (self::cases() as $case) {
            if (strcasecmp($case->name, $value) === 0 || strcasecmp($case->value, $value) === 0) {
                return $case;
            }
        }

        return self::NONE;
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Kg => 'Kg',
            self::Case => 'Case',
            self::Pcs => 'Pcs',
            self::Ltr => 'Ltr',
            self::Each => 'Each',
            self::Bottle => 'Bottle',
            self::Bags => 'Bags',
            self::Feet => 'Feet',
            self::Meter => 'Meter',
            self::NONE => '-',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Kg => 'gray',
            self::Case => 'gray',
            self::Pcs => 'gray',
            self::Ltr => 'gray',
            self::Each => 'gray',
            self::Bottle => 'gray',
            self::Bags => 'gray',
            self::Feet => 'gray',
            self::Meter => 'gray',
            self::NONE => 'gray',
        };
    }
}
