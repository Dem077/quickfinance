<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PurchaseRequestsStatus: string implements HasLabel , HasColor
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case HODApproved = 'hod_approved';
    case HODRejected = 'hod_rejected';
    case DocumentUploaded = 'document_uploaded';
    case Canceled = 'canceled';
    case Approved = 'approved';
    case Closed = 'closed';

    public function getLabel(): ?string
    {
        return match($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::HODApproved => 'Department HOD Approved',
            self::HODRejected => 'Department HOD Rejected',
            self::DocumentUploaded => 'Document Uploaded',
            self::Canceled => 'Finance Rejected',
            self::Approved => 'Finance Approved',
            self::Closed => 'Closed',
        };
    }
    public function getColor(): ?string
    {
        return match($this) {
            self::Draft => 'gray',
            self::Submitted => 'warning',
            self::HODApproved => 'success',
            self::HODRejected => 'danger',
            self::DocumentUploaded => 'info',
            self::Canceled => 'danger',
            self::Approved => 'success',
            self::Closed => 'success',
        };
    }

}
