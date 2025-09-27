<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkOrderPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'type',
        'path',
        'caption',
        'taken_by_user_id',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function takenBy()
    {
        return $this->belongsTo(User::class, 'taken_by_user_id');
    }
}
