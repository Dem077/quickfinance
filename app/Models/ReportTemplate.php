<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'model_type',
        'field_configs',
        'from_date',
        'to_date',
        'created_by'
    ];

    protected $casts = [
        'field_configs' => 'array',
        'from_date' => 'date',
        'to_date' => 'date',
    ];
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
