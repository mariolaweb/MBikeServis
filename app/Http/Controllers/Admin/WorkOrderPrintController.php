<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Services\QrCodeService;

class WorkOrderPrintController extends Controller
{
    public function label(WorkOrder $workOrder, QrCodeService $qr)
    {
        $svgPath = $qr->makeForPublicToken(
            $workOrder->public_track_url,
            $workOrder->public_token
        );

        return view('admin.print-label', [
            'wo' => $workOrder,
            'svgPath' => $svgPath,
        ]);
    }
}
