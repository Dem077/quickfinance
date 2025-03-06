<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Submitted = 'submitted';
    case Closed = 'closed';
    case Draft = 'draft';
}