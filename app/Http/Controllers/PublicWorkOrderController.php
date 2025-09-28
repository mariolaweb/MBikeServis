<?php

namespace App\Http\Controllers;

use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PublicWorkOrderController extends Controller
{
    public function show(string $token): View
    {
        $wo = WorkOrder::query()
            ->where('public_token', $token)
            ->whereNull('public_token_disabled_at')            // ručno gašenje linka
            ->firstOrFail();

        // (opciono) auto-istek nakon isporuke, npr. 30 dana
        if ($wo->delivered_at && now()->greaterThan($wo->delivered_at->clone()->addDays(30))) {
            throw new HttpException(410, 'Link je istekao.'); // 410 Gone
        }

        // Učitaj što treba za prikaz
        $wo->load([
            'customer:id,name',        // PAŽNJA: prikaži samo ono što smije vidjeti klijent
            'gear:id,customer_id,category,brand,model,serial_number',
            'woItems' => fn($q) => $q->active()->orderBy('id'),
            'latestEstimate.items',
        ]);

        // Izaberi izvor stavki: ako postoje wo_items → konačne; inače estimate_items
        $items = $wo->woItems->isNotEmpty()
            ? $wo->woItems->map(fn($i) => [
                'sku' => $i->sku, 'name' => $i->name,
                'qty' => (float)$i->qty, 'unit_price' => (float)$i->unit_price,
                'line_total' => (float)$i->line_total,
            ])
            : optional($wo->latestEstimate?->items)->map(fn($i) => [
                'sku' => $i->sku, 'name' => $i->name,
                'qty' => (float)$i->qty, 'unit_price' => (float)$i->unit_price,
                'line_total' => (float)$i->line_total,
            ]) ?? collect();

        return view('public.track', [
            'wo'    => $wo,
            'items' => $items,
            'showing' => $wo->woItems->isNotEmpty() ? 'wo' : 'estimate',
        ]);
    }
}
