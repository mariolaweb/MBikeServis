<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'phone',
        'email',
        'address',
        'city',
        'lat',
        'lng',
        'erp_warehouse_code',
        'pos_identifier',
        'invoice_prefix',
        'invoice_counter',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    // primjeri za kasnije:
    // public function workOrders() { return $this->hasMany(WorkOrder::class); }
    // public function invoices()   { return $this->hasMany(Invoice::class); }
}
