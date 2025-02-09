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
}
