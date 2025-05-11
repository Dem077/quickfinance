<?php

namespace App\Enums;

enum PurchaseRequestsStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case HODApproved = 'hod_approved';
    case HODRejected = 'hod_rejected';
    case DocumentUploaded = 'document_uploaded';
    case Canceled = 'canceled';
    case Approved = 'approved';
    case Closed = 'closed';

}
