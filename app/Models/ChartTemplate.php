<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChartTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'model_type',
        'chart_type',
        'group_by',
        'group_period',
        'aggregation',
        'metric_field',
        'filters',
        'from_date',
        'to_date',
        'show_on_dashboard',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'filters' => 'array',
        'from_date' => 'date',
        'to_date' => 'date',
        'show_on_dashboard' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
