<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Location;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;

class Dashboard extends Component
{

    public function render()
    {
        $user = Auth::user();

        $roleLabel    = $user?->getRoleNames()->first();
        $isAdminOwner = $user?->hasAnyRole(['master-admin', 'vlasnik']) ?? false;

        // Ako owner/admin pošalje formu sa ?location=ID → validiraj i upiši u session (prepiši staru)
        if ($isAdminOwner && request()->has('location')) {
            // Uzmi vrijednost iz GET parametra (?location=)
            $locParam = request()->input('location');

            if ($locParam === null || $locParam === '') {
        // "Sve poslovnice" → očisti kontekst
        session()->forget('current_location');
    } else {

            $loc = (int) $locParam;
            if (Location::where('id', $loc)->where('is_active', true)->exists()) {
                session(['current_location' => $loc]);
            }
            // ako je poslao nepostojeći/nea ktivan ID, jednostavno ignorišemo i zadržimo staru session vrijednost
        }
    }

        // Odabir lokacije za prikaz:
        // - owner/admin: iz session-a (ostaje dok se ne promijeni iz dropdowna ili dok se ne odjavi)
        // - ostali: iz profila korisnika
        $selectedLocId = $isAdminOwner ? session('current_location') : $user?->location_id;

        // Validacija & dohvat trenutne lokacije
        if ($selectedLocId) {
            $selectedLocId = Location::where('id', $selectedLocId)
                ->where('is_active', true)
                ->value('id'); // null ako ne postoji / nije aktivna
        }

        $currentLocation = $selectedLocId
            ? Location::select('id', 'code', 'name', 'city')->find($selectedLocId)
            : null;

        // Dropdown punimo samo owner/admin
        $activeLocations = $isAdminOwner
            ? Location::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'city'])
            : collect();

        return view('livewire.dashboard', compact(
            'user',
            'roleLabel',
            'isAdminOwner',
            'selectedLocId',
            'currentLocation',
            'activeLocations'
        ));
    }
}
