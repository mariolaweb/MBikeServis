<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'city',
        'address',
        'notes',
    ];

    public function bikes()
    {
        return $this->hasMany(Bike::class);
    }

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }
}
