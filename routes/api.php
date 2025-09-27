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

Route::post('/v1/webhooks/estimate-import', function (Request $request) {
    // Privremeno: samo provjera Bearer tokena (lako)
    if ($request->bearerToken() !== config('services.erp.token')) {
        return response()->json(['error' => 'unauthorized'], 401);
    }

    // Minimalno: potvrdi prijem
    // (kasnije ćemo ovdje upisivati u bazu)
    return response()->noContent(); // 204
});
