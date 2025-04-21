<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Departments extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hod',
        'hod_designation',
        'petty_cash_float_amount',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'department_id');
    }

    public function purchaseRequest()
    {
        return $this->hasMany(PurchaseRequests::class, 'department_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hod');
    }

    // public function hodfromusers():HasMany
    // {
    //     return $this->hasMany(User::class,  'hod_of' );
    // }

    public function subBudgetAccounts()
    {
        return $this->hasMany(SubBudgetAccounts::class, 'department_id');
    }

    
}
