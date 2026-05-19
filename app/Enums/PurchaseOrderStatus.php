<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PurchaseOrderStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case Closed = 'closed';

    case GRNCreated = 'grn_created';
    case Reimbursed = 'reimbursed';
    case WaitingReimbursement = 'reimbursement_pending';
    case Draft = 'draft';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::GRNCreated => 'GRN Created',
            self::Reimbursed => 'Reimbursed',
            self::WaitingReimbursement => 'Pending Reimbursement',
            self::Closed => 'Closed',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'primary',
            self::Reimbursed => 'success',
            self::WaitingReimbursement => 'warning',
            self::GRNCreated => 'info',
            self::Closed => 'success',
        };
    }
}
