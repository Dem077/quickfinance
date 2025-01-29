<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendors extends Model
{
   
    protected $fillable = [
        'name',
        'address',
        'account_no',
        'mobile',
        'gst_no',
    ];

    public function purchaseOrders():HasMany
    {
        return $this->hasMany(PurchaseOrders::class);
    }
}
