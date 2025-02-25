<?php

namespace App\Models;

use App\Enums\PettyCashStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PettyCashReimbursment extends Model
{
    protected $fillable = [
        'date',
        'user_id',
        'status',
        'form_no',
        'supporting_documents',
        'pv_number',
        'verified_by',
        'approved_by',
    ];

    protected $casts = [
        'status' => PettyCashStatus::class,
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pettyCashReimbursmentDetails():HasMany
    {
        return $this->hasMany(PettyCashReimbursmentDetail::class, 'petty_cash_reimb_id');
    }

    public function VerifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function ApprovedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
