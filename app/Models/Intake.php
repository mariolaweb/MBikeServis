<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Intake extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'customer_id',
        'gear_id',
        'created_by',
        'converted_work_order_id',
        'converted_at'
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function gear()
    {
        return $this->belongsTo(Gear::class);
    }

    //ovo kasnije obrisati
    public function bike()
    {
        return $this->belongsTo(Bike::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'converted_work_order_id');
    }
}
