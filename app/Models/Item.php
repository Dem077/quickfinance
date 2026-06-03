<?php

namespace App\Models;

use App\Enums\ItemTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_code',
        'name',
        'type',
    ];

    protected $casts = [
        'type' => ItemTypeEnum::class,
    ];

    public function purchaseRequestDetails()
    {
        return $this->hasMany(PurchaseRequestDetails::class, 'item_id');
    }

    public function purchaseOrderDetails()
    {
        return $this->hasMany(PurchaseOrderDetails::class, 'item_id');
    }

    public function pettyCashReimbursmentDetails()
    {
        return $this->hasMany(PettyCashReimbursmentDetail::class, 'item_id');
    }
}
