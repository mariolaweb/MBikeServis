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
        'work_order_id',
        'accepted_by',
        'accepted_at',
        'raw_json',
        'received_at',
        'status',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax'      => 'decimal:2',
        'total'    => 'decimal:2',
        'accepted_at' => 'datetime',   // ✅ korisno
        'received_at' => 'datetime',   // ✅ korisno
    ];

    public function items()
    {
        return $this->hasMany(EstimateItem::class);
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function scopeForWorkOrder($q, int $woId)
    {
        return $q->where('work_order_id', $woId);
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }
}
