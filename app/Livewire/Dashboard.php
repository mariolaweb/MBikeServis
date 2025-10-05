<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Location;
use App\Models\WorkOrder;
use Illuminate\Support\Carbon;
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

        // ---------- PERIOD FILTER ----------
        $period = request('period', 'today');
        $from   = request('from');
        $to     = request('to');

        // Normalizuj raspon (zatvoren interval po danima)
        [$start, $end] = $this->resolveDateRange($period, $from, $to);

        // ---------- BAZNI UPIT (opciono po lokaciji) ----------
        $base = WorkOrder::query();

        if ($selectedLocId) {
            $base->where('location_id', $selectedLocId);
        }

        // ---------- SAŽETAK (Sve poslovnice ili aktivna) ----------
        // 1) ukupno zaprimljeno (po created_at)
        $totalReceived = (clone $base)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        // 2) čeka dodjelu servisera
        $waitingAssignment = (clone $base)
            ->whereBetween('created_at', [$start, $end])
            ->whereNull('assigned_user_id')
            ->whereNull('cancelled_at')
            ->count();

        // 3) dodijeljen serviser (bez radnog naloga) – privremena definicija
        $assignedNoWo = (clone $base)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('assigned_user_id')
            ->whereNull('completed_at')
            ->whereNull('cancelled_at')
            ->count();

        // 4) otvoreni radni nalozi (u toku)
        $woOpen = (clone $base)
            ->whereBetween('created_at', [$start, $end])
            ->whereNull('completed_at')
            ->whereNull('cancelled_at')
            ->count();

        // 5) završeni (po completed_at)
        $completed = (clone $base)
            ->whereBetween('completed_at', [$start, $end])
            ->count();

        // 6) otkazani (po canceled_at)
        $cancelled = (clone $base)
            ->whereBetween('cancelled_at', [$start, $end])
            ->count();

        $summary = [
            'total_received'     => $totalReceived,
            'waiting_assignment' => $waitingAssignment,
            'assigned_no_wo'     => $assignedNoWo,
            'wo_open'            => $woOpen,
            'completed'          => $completed,
            'cancelled'          => $cancelled,
            'range'              => [$start, $end], // korisno ako želiš prikaz raspona
        ];

        return view('livewire.dashboard', compact(
            'user',
            'roleLabel',
            'isAdminOwner',
            'selectedLocId',
            'currentLocation',
            'activeLocations',
            'summary',          // ⬅️ prosledi u view
            'period',           // ⬅️ ako zatreba u UI
        ));
    }


    /**
     * Odredi [start, end] na osnovu period/from/to (end je kraj dana 23:59:59).
     */
    private function resolveDateRange(string $period, ?string $from, ?string $to): array
    {
        switch ($period) {
            case '7days':
                $start = Carbon::today()->subDays(6)->startOfDay();
                $end   = Carbon::today()->endOfDay();
                break;

            case 'month':
                $start = Carbon::now()->startOfMonth();
                $end   = Carbon::now()->endOfDay();
                break;

            case 'custom':
                // očekujemo YYYY-MM-DD
                $start = $from ? Carbon::parse($from)->startOfDay() : Carbon::today()->startOfDay();
                $end   = $to   ? Carbon::parse($to)->endOfDay()     : Carbon::today()->endOfDay();
                break;

            case 'today':
            default:
                $start = Carbon::today()->startOfDay();
                $end   = Carbon::today()->endOfDay();
                break;
        }

        return [$start, $end];
    }
}
