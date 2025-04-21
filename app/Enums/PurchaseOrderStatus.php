<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Submitted = 'submitted';
    case Closed = 'closed';
    case Reimbursed = 'reimbursed';
    case Draft = 'draft';
}