<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkOrder extends Model
{
    use HasFactory;
    // use LocationScoped; // ⬅️ uključi kada želiš globalni scope po sesijskoj lokaciji

    protected $fillable = [
        'number',
        'location_id',
        'customer_id',
        'gear_id',
        'assigned_user_id',
        'status',
        'started_at',
        'completed_at',
        'delivered_at',
        'total_elapsed_minutes',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'status' => WorkOrderStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'assigned_user_id' => 'integer',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    //ovo kasnije obrisati
    // public function bike()
    // {
    //     return $this->belongsTo(Bike::class);
    // }

    public function gear()
    {
        return $this->belongsTo(Gear::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function photos()
    {
        return $this->hasMany(WorkOrderPhoto::class);
    }

    public function items()
    {
        return $this->hasMany(WoItem::class);
    }

    public function intake()
    {
        return $this->hasOne(Intake::class, 'converted_work_order_id');
    }

    public function estimates()
    {
        return $this->hasMany(Estimate::class);
    }

    public function latestEstimate()  // pošalji iz webhook-a received_at
    {
        return $this->hasOne(Estimate::class)->latestOfMany('received_at');
    }

    public function woItems()         // postojeća tabela stavki naloga
    {
        return $this->hasMany(WoItem::class);
    }


    // Helper label (ako želiš)
    // public function getStatusLabelAttribute(): string
    // {
    //     return match ($this->status) {
    //         WorkOrderStatus::RECEIVED       => 'Zaprimljen',
    //         WorkOrderStatus::IN_PROGRESS    => 'U radu',
    //         WorkOrderStatus::WAITING_PARTS  => 'Čeka dijelove',
    //         WorkOrderStatus::COMPLETED      => 'Završen',
    //         WorkOrderStatus::DELIVERED      => 'Isporučen',
    //     };
    // }
}
