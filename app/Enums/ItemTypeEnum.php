<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ItemTypeEnum: string implements HasLabel
{
    case Asset = 'asset';
    case Accessory = 'accessory';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Asset => 'Asset',
            self::Accessory => 'Accessory',
            self::Other => 'Other',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Asset => 'success',
            self::Accessory => 'info',
            self::Other => 'gray',
        };
    }

    public function syncsToSnipeIt(): bool
    {
        return match ($this) {
            self::Asset, self::Accessory => true,
            self::Other => false,
        };
    }
}
