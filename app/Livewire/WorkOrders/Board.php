<?php

namespace App\Livewire\WorkOrders;

use App\Models\Intake as IntakeModel;
use App\Models\Location;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Board extends Component
{
    use WithPagination;

    // URL filteri
    #[Url(as: 'scope')]    public string $scope = 'poslovnica';  // 'poslovnica' | 'moji' (za servisere)
    #[Url(as: 'status')]   public ?string $status = null;        // filter statusa (opciono)

    protected $paginationTheme = 'tailwind';

    #[Layout('layouts.app')]
    public function render()
    {
        $user         = Auth::user();
        $isAdminOwner = $user?->hasAnyRole(['master-admin', 'vlasnik']) ?? false;
        $isManager    = $user?->hasRole('menadzer') ?? false;
        $isServiser   = $user?->hasRole('serviser') ?? false;

        // Lokacija: owner/admin iz sessiona; ostali iz usera
        $locId = $isAdminOwner ? session('current_location') : ($user?->location_id ?? null);

        if ($locId) {
            $locId = Location::where('id', $locId)->where('is_active', true)->value('id');
        }
        $currentLocation = $locId
            ? Location::select('id', 'code', 'name', 'city')->find($locId)
            : null;


        // --- PRIJEMI BEZ NALOGA (intakes.converted_at IS NULL) ---
        $intakes = collect();
        if (($isAdminOwner || $isManager) && $locId) {
            $intakes = IntakeModel::query()
                ->with(['customer:id,name,phone', 'bike:id,brand,model,customer_id'])
                ->where('location_id', $locId)
                ->whereNull('converted_at')
                ->latest()
                ->paginate(10, ['*'], 'intakes_page'); // ⬅️ ispravan potpis paginate()
        }

        // --- NALOZI BEZ SERVISERA (status=received AND assigned_user_id IS NULL) ---
        $pendingWo = collect();
        if (($isAdminOwner || $isManager) && $locId) {
            $pendingWo = WorkOrder::query()
                ->with(['customer:id,name,phone', 'bike:id,brand,model,customer_id'])
                ->where('location_id', $locId)
                ->where('status', 'received')
                ->whereNull('assigned_user_id')
                ->latest()
                ->paginate(10, ['*'], 'pending_wo_page'); // ⬅️ odvojena paginacija
        }

        // --- SVI RADNI NALOZI (bez "nedodijeljenih" da se ne dupliraju) ---
        $woQuery = WorkOrder::query()
            ->with([
                'customer:id,name,phone',
                'bike:id,brand,model,customer_id',
                'assignedUser:id,name',
            ])
            ->latest();

        if ($locId) {
            $woQuery->where('location_id', $locId);
        } else {
            // owner/admin bez lokacije ne vidi ništa (da ne vidi "sve")
            if ($isAdminOwner) {
                $woQuery->whereRaw('1=0');
            } else {
                $woQuery->where('location_id', -1);
            }
        }

        // Globalni filter statusa (opciono)
        if ($this->status) {
            $woQuery->where('status', $this->status);
        }

        // Serviser: "moji" vs "poslovnica"
        if ($isServiser && $this->scope === 'moji') {
            $woQuery->where('assigned_user_id', $user->id);
        }

        // ⬇️ KLJUČNO: isključi "nedodijeljene" (oni su već u $pendingWo bloku iznad)
        $woQuery->where(function ($q) {
            $q->where('status', '!=', 'received')
                ->orWhereNotNull('assigned_user_id');
        });

        $workOrders = $woQuery->paginate(10, ['*'], 'wo_page');

        return view('livewire.work-orders.board', compact(
            'user',
            'isAdminOwner',
            'isManager',
            'isServiser',
            'currentLocation',
            'intakes',
            'pendingWo',
            'workOrders'
        ));
    }

    // Klik u "Prijemi": konvertuj Intake -> kreiraj WO -> redirect na Uredi
    public function convertIntake(int $intakeId): void
    {
        $user         = Auth::user();
        $isAdminOwner = $user?->hasAnyRole(['master-admin', 'vlasnik']) ?? false;
        $isManager    = $user?->hasRole('menadzer') ?? false;

        if (!($isAdminOwner || $isManager)) {
            abort(403);
        }

        $locId = $isAdminOwner ? session('current_location') : ($user?->location_id ?? null);
        if (!$locId) {
            $this->addError('location', 'Nije izabrana poslovnica.');
            return;
        }
        $locId = Location::where('id', $locId)->where('is_active', true)->value('id');
        if (!$locId) {
            $this->addError('location', 'Nepostojeća ili neaktivna poslovnica.');
            return;
        }


        $intake = IntakeModel::query()
            ->where('id', $intakeId)
            ->where('location_id', $locId)
            ->whereNull('converted_at')
            ->first();

        if (!$intake) {
            $this->addError('intake', 'Prijem nije pronađen ili je već konvertovan.');
            return;
        }

        $loc    = Location::find($locId);
        $number = ($loc?->code ?? 'WO') . '-' . now()->format('Ymd-His');

        $wo = WorkOrder::create([
            'number'           => $number,
            'location_id'      => $locId,
            'customer_id'      => $intake->customer_id,
            'bike_id'          => $intake->bike_id,
            'assigned_user_id' => null,       // menadžer dodjeljuje u "Uredi"
            'status'           => 'received',
            'created_by'       => $user?->id,
        ]);

        $intake->update([
            'converted_work_order_id' => $wo->id,
            'converted_at'            => now(),
        ]);

        $this->redirectRoute('workorders-edit', ['workorder' => $wo->id]);
    }

    // Vraćanje naloga u "nedodijeljene" (bez servisera) – ostaje u work_orders, ali ide u pending listu
    public function unassignWo(int $woId): void
    {
        $user         = Auth::user();
        $isAdminOwner = $user?->hasAnyRole(['master-admin', 'vlasnik']) ?? false;
        $isManager    = $user?->hasRole('menadzer') ?? false;

        if (!($isAdminOwner || $isManager)) {
            abort(403);
        }

        $locId = $isAdminOwner ? session('current_location') : ($user?->location_id ?? null);
        if (!$locId) {
            $this->addError('location', 'Nije izabrana poslovnica.');
            return;
        }
        $locId = Location::where('id', $locId)->where('is_active', true)->value('id');
        if (!$locId) {
            $this->addError('location', 'Nepostojeća ili neaktivna poslovnica.');
            return;
        }


        $wo = WorkOrder::where('id', $woId)->where('location_id', $locId)->first();
        if (!$wo) {
            $this->addError('wo', 'Nalog nije pronađen.');
            return;
        }

        $wo->update([
            'assigned_user_id' => null,
            'status'           => 'received',
        ]);

        session()->flash('ok', 'Nalog je vraćen u nedodijeljene.');
        // osvježi odgovarajuće paginacije da se pojavi u "pending" i nestane iz glavne liste
        $this->resetPage('wo_page');
        $this->resetPage('pending_wo_page');
    }

    // Reset paginacija na promjenu filtera
    public function updatedScope()
    {
        $this->resetPage('wo_page');
    }
    public function updatedStatus()
    {
        $this->resetPage('wo_page');
    }
}
