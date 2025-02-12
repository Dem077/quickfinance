<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendors extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'account_no',
        'mobile',
        'gst_no',
        'bank',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrders::class);
    }

    public function advanceForms(): HasMany
    {
        return $this->hasMany(AdvanceForm::class , 'vendors_id');
    }

    public function pettyCashReimbursments(): HasMany
    {
        return $this->hasMany(PettyCashReimbursment::class , 'Vendor_id');
    }
}
