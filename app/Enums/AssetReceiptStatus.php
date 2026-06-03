<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetReceiptStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Received = 'received';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Received => 'Received',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Received => 'success',
        };
    }
}
