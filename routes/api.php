<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Route::post('/v1/webhooks/estimate-import', function (Request $request) {
//     // 1) Provjera Bearer tokena
//     if ($request->bearerToken() !== config('services.erp.token')) {
//         return response()->json(['error' => 'unauthorized'], 401);
//     }

//     // 2) Provjera HMAC potpisa
//     $raw = $request->getContent();
//     $expected = 'sha256=' . hash_hmac('sha256', $raw, config('services.erp.secret'));
//     if (! hash_equals($expected, (string) $request->header('X-Signature'))) {
//         return response()->json(['error' => 'invalid_signature'], 401);
//     }

//     // 3) JSON payload
//     $payload = json_decode($raw, true) ?: [];
//     // TODO: ovdje upišiš u svoje tabele (estimates, estimate_items...)
//     // Npr. Log::info('ERP payload', $payload);

//     return response()->noContent(); // 204
// });

// Route::post('/v1/webhooks/estimate-import', function (Request $request) {
//     // Privremeno: samo provjera Bearer tokena (lako)
//     if ($request->bearerToken() !== config('services.erp.token')) {
//         return response()->json(['error' => 'unauthorized'], 401);
//     }

//     // Minimalno: potvrdi prijem
//     // (kasnije ćemo ovdje upisivati u bazu)
//     return response()->noContent(); // 204
// });

Route::post('/v1/webhooks/estimate-import', function (Request $request) {
    // 1) Bearer token
    if ($request->bearerToken() !== config('services.erp.token')) {
        return response()->json(['error' => 'unauthorized'], 401);
    }

    // 2) HMAC potpis
    $raw = $request->getContent();
    $expected = 'sha256=' . hash_hmac('sha256', $raw, config('services.erp.secret'));
    if (! hash_equals($expected, (string) $request->header('X-Signature'))) {
        return response()->json(['error' => 'invalid_signature'], 401);
    }

    // 3) JSON payload
    $payload = json_decode($raw, true) ?: [];

    // --- MINIMALNI UPIS U BAZU (bez modela, direktno) ---
    // očekivana polja iz mock-a: intake_id, external_estimate_id, idempotency_key, currency, totals{subtotal,tax,grand_total}, lines[]
    $idempotencyKey = $payload['idempotency_key'] ?? null;
    if (!$idempotencyKey) {
        // bez ključa ne upisujemo (da ne dupliramo)
        return response()->json(['error' => 'missing_idempotency_key'], 422);
    }

    // idempotentno: ako već postoji — izađi mirno
    $exists = DB::table('estimates')->where('idempotency_key', $idempotencyKey)->exists();
    if ($exists) {
        return response()->noContent(); // 204 ok
    }

    DB::transaction(function () use ($payload, $idempotencyKey) {
        $estimateId = DB::table('estimates')->insertGetId([
            'intake_id'            => $payload['intake_id'] ?? null,
            'external_estimate_id' => $payload['external_estimate_id'] ?? null,
            'idempotency_key'      => $idempotencyKey,
            'currency'             => $payload['currency'] ?? 'BAM',
            'subtotal'             => $payload['totals']['subtotal']    ?? 0,
            'tax'                  => $payload['totals']['tax']         ?? 0,
            'total'                => $payload['totals']['grand_total'] ?? 0,
            'raw_json'             => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        foreach (($payload['lines'] ?? []) as $line) {
            DB::table('estimate_items')->insert([
                'estimate_id' => $estimateId,
                'sku'         => $line['sku']        ?? null,
                'name'        => $line['name']       ?? '',
                'qty'         => $line['qty']        ?? 1,
                'unit_price'  => $line['unit_price'] ?? 0,
                'line_total'  => $line['total']      ?? 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    });

    return response()->noContent(); // 204
});

