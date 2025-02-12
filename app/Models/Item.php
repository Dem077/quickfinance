<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_code',
        'name',
    ];

    public function purchaseRequestDetails()
    {
        return $this->hasMany(PurchaseRequestDetails::class , 'item_id');
    }

}
