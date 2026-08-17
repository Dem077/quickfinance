<?php

namespace App\Enums;

enum PettyCashStatus: string
{
    case Submitted = 'submited';
    case DepApproved = 'dep_approved';
    case FinApproved = 'fin_approved';
    case Rembursed = 'rembursed';
    case Fin_Reject = 'fin_reject';
    case Dep_Reject = 'dep_reject';
    case Draft = 'draft';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::DepApproved => 'Department approved',
            self::Dep_Reject => 'Department rejected',
            self::FinApproved => 'Finance approved',
            self::Fin_Reject => 'Finance rejected',
            self::Rembursed => 'Reimbursed',
        };
    }
}
