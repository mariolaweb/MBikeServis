<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Estimate;
use App\Models\EstimateItem;

class ErpWebhookController extends Controller
{
    public function estimateImport(Request $request)
    {
        // 1) Bearer token
        if ($request->bearerToken() !== config('services.erp.token')) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        // 2) HMAC potpis (isti format koji već koristiš: "sha256=<digest>")
        $raw = $request->getContent();
        $expected = 'sha256=' . hash_hmac('sha256', $raw, config('services.erp.secret'));
        if (! hash_equals($expected, (string) $request->header('X-Signature'))) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        // 3) JSON payload
        $payload = json_decode($raw, true) ?: [];

        // Minimalna validacija potrebnih polja
        $idempotencyKey = $payload['idempotency_key'] ?? null;
        if (! $idempotencyKey) {
            return response()->json(['error' => 'missing_idempotency_key'], 422);
        }

        // Idempotencija: ako već postoji — mirno izlazimo
        if (Estimate::where('idempotency_key', $idempotencyKey)->exists()) {
            return response()->noContent(); // 204
        }

        // Očekivani shape (prilagodi ako se promijeni mock)
        $intakeId  = $payload['intake_id'] ?? null;
        $external  = $payload['external_estimate_id'] ?? null;
        $currency  = $payload['currency'] ?? 'BAM';
        $subtotal  = (float) data_get($payload, 'totals.subtotal', 0);
        $tax       = (float) data_get($payload, 'totals.tax', 0);
        $grand     = (float) data_get($payload, 'totals.grand_total', 0);
        $lines     = (array) ($payload['lines'] ?? []);

        DB::transaction(function () use ($intakeId, $external, $idempotencyKey, $currency, $subtotal, $tax, $grand, $payload, $lines) {

            // 4) Kreiraj Estimate
            $estimate = Estimate::create([
                'intake_id'            => $intakeId,
                'external_estimate_id' => $external,
                'idempotency_key'      => $idempotencyKey,
                'currency'             => $currency,
                'subtotal'             => $subtotal,
                'tax'                  => $tax,
                'total'                => $grand,
                'raw_json'             => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);

            // 5) Kreiraj stavke
            foreach ($lines as $line) {
                $qty   = (float) ($line['qty'] ?? 1);
                $price = (float) ($line['unit_price'] ?? 0);
                $total = isset($line['total']) ? (float) $line['total'] : round($qty * $price, 2);

                EstimateItem::create([
                    'estimate_id' => $estimate->id,
                    'sku'         => $line['sku']        ?? null,
                    'name'        => $line['name']       ?? '',
                    'qty'         => $qty,
                    'unit_price'  => $price,
                    'line_total'  => $total,
                    // ako naknadno dodaš kolonu 'type' u estimate_items, ovdje je mapiraj:
                    // 'type'     => $line['type'] ?? null,
                ]);
            }
        });

        return response()->noContent(); // 204
    }
}
