<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AdvanceFormStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case HOD_Approved = 'hod_approved';
    case HOD_Rejected = 'hod_rejected';
    case DMD_MD_Approved = 'dmd_md_approved';
    case DMD_MD_Rejected = 'dmd_md_rejected';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::HOD_Approved => 'HOD Approved',
            self::HOD_Rejected => 'HOD Rejected',
            self::DMD_MD_Approved => 'DMD / MD Approved',
            self::DMD_MD_Rejected => 'DMD / MD Rejected',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'warning',
            self::HOD_Approved => 'success',
            self::HOD_Rejected => 'danger',
            self::DMD_MD_Approved => 'success',
            self::DMD_MD_Rejected => 'danger',
        };
    }
}
