<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentLocation
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user(); // ili $request->user();
        if (! $user) {
            return $next($request);
        }

        // Master-admin može ručno prebaciti lokaciju (?location=ID ili ?location=CODE)
        if ($user->hasRole('master-admin', 'vlasnik') && $request->filled('location')) {
            $loc = $request->query('location');

            $location = is_numeric($loc)
                ? Location::whereKey($loc)->where('is_active', true)->first()
                : Location::where('code', $loc)->where('is_active', true)->first();

            if ($location) {
                session(['current_location_id' => $location->id]);
            }
        }

        // Ako sesija još nema lokaciju → preuzmi sa usera
        if (! session()->has('current_location_id')) {
            session(['current_location_id' => $user->location_id]);
        }

        // Ako je u sesiji nepostojeća/deaktivirana lokacija → vrati na user->location_id
        $current = session('current_location_id');
        if ($current && ! Location::whereKey($current)->where('is_active', true)->exists()) {
            session(['current_location_id' => $user->location_id]);
        }

        return $next($request);
    }
}
