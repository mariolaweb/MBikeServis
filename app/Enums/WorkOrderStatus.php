<?php

namespace App\Enums;

enum WorkOrderStatus: string
{
    case RECEIVED      = 'received';       // zaprimljen
    case IN_PROGRESS   = 'in_progress';    // u radu
    case WAITING_PARTS = 'waiting_parts';  // čekaju se dijelovi
    case COMPLETED     = 'completed';      // završen
    case DELIVERED     = 'delivered';      // isporučen

        public function label(): string
    {
        return match($this) {
            self::RECEIVED      => 'Zaprimljen',
            self::IN_PROGRESS   => 'U radu',
            self::WAITING_PARTS => 'Čekaju se dijelovi',
            self::COMPLETED     => 'Završen',
            self::DELIVERED     => 'Isporučen',
        };
    }

}
