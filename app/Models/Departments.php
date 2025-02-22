<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    public function hodfromusers():HasOne
    {
        return $this->hasOne(User::class,  'hod_of' );
    }

    public function subBudgetAccounts()
    {
        return $this->hasMany(SubBudgetAccounts::class, 'department_id');
    }
}
