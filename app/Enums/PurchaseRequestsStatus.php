<?php

namespace App\Enums;

enum PurchaseRequestsStatus: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Closed = 'closed';
    case DocumentUploaded = 'document_uploaded';
    case Canceled = 'canceled';
    case Draft = 'draft';
}