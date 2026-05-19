<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PurchaseRequestsStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case HODApproved = 'hod_approved';
    case HODRejected = 'hod_rejected';
    case DocumentUploaded = 'document_uploaded';
    case MD_DMD_Approved = 'md_dmd_approved';
    case MD_DMD_Rejected = 'md_dmd_rejected';
    case Canceled = 'canceled';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Closed = 'closed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::HODApproved => 'Department HOD Approved',
            self::HODRejected => 'Department HOD Rejected',
            self::DocumentUploaded => 'Document Uploaded',
            self::MD_DMD_Approved => 'MD / DMD Approved',
            self::MD_DMD_Rejected => 'MD / DMD Rejected',
            self::Canceled => 'Canceled',
            self::Approved => 'Finance Approved',
            self::Rejected => 'Finance Rejected',
            self::Closed => 'Closed',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'warning',
            self::HODApproved => 'success',
            self::HODRejected => 'danger',
            self::DocumentUploaded => 'info',
            self::MD_DMD_Approved => 'success',
            self::MD_DMD_Rejected => 'danger',
            self::Canceled => 'danger',
            self::Rejected => 'danger',
            self::Approved => 'success',
            self::Closed => 'success',
        };
    }
}
