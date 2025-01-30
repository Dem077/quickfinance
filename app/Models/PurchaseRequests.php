<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseRequests  extends Model
{
    protected $fillable = [
        'pr_no',
        'date',
        'budget_account_id',
        'department_id',
        'user_id',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $year = date('Y');
            $count = self::whereYear('created_at', $year)->count() + 1;
            $model->pr_no = sprintf('PR/AGRO/%s/%04d', $year, $count);
        });
    }

    public function budgetAccount(): BelongsTo
    {
        return $this->belongsTo(SubBudgetAccounts::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchaseRequestDetails(): HasOne
    {
        return $this->hasOne(PurchaseRequestDetails::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Departments::class, 'department_id');
    }



}
