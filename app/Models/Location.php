<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'location_id');
    }

    public function subBudgetAccounts(): HasMany
    {
        return $this->hasMany(SubBudgetAccounts::class, 'location_id');
    }

    public function purchaseRequests()
    {
        return $this->belongsToMany(PurchaseRequests::class, 'location_purchase_request', 'location_id', 'purchase_request_id');
    }
}
