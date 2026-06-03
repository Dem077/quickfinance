<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ItemTypeEnum: string implements HasLabel
{
    case Asset = 'asset';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Asset => 'Asset',
            self::Other => 'Other',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Asset => 'success',
            self::Other => 'gray',
        };
    }
}
