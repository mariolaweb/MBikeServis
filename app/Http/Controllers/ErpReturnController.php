<?php

namespace App\Http\Controllers;

use App\Models\Intake;
use Illuminate\Http\RedirectResponse;

class ErpReturnController extends Controller
{
    public function handle(Intake $intake): RedirectResponse
    {
        // očekujemo da Intake ima converted_work_order_id (ti to već praviš u save())
        $woId = $intake->converted_work_order_id;

        if (! $woId) {
            // fallback – poštena poruka, pa nazad na board ili intake create
            return redirect()
                ->route('workorders-board')
                ->with('error', 'ERP: Nalog nije povezan sa prijemom.');
        }

        // 1) redirect direktno na edit WO (tvoja postojeća ruta)
        // 2) flash poruka da će se stavke pojaviti čim webhook upiše estimate
        return redirect()
            ->route('workorders-edit', ['workorder' => $woId])
            ->with('ok', 'ERP sesija završena. Ako ponuda još nije vidljiva, stiže za par trenutaka.');
    }
}
