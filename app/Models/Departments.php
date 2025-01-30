<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Departments extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'department_id');
    }

    public function purchaseRequest()
    {
        return $this->hasMany(PurchaseRequests::class, 'department_id');
    }
}
