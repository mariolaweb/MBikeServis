<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estimate extends Model
{
    protected $fillable = [
        'intake_id',
        'external_estimate_id',
        'idempotency_key',
        'currency',
        'subtotal',
        'tax',
        'total',
        'raw_json',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax'      => 'decimal:2',
        'total'    => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(EstimateItem::class);
    }

    // (opciono) kad vežeš i na WO u budućnosti:
    // public function workOrder() { return $this->belongsTo(WorkOrder::class); }
}
