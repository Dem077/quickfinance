<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetAccounts extends Model
{
    
    protected $fillable = [
        'name',
        'code',
        'amount',
    ];

    public function purchaseRequests():HasMany
    {
        return $this->hasMany(PurchaseRequests::class);
    }
}
