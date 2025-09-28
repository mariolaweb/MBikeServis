<?php

namespace App\Livewire\WorkOrders;

use App\Models\User;
use Livewire\Component;
use App\Models\Location;
use App\Models\WorkOrder;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use App\Enums\WorkOrderStatus;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Intake as IntakeModel;

class Board extends Component
{
    use WithPagination;

    // URL filteri
    #[Url(as: 'scope')]  public string $scope = 'poslovnica';  // 'poslovnica' | 'moji'
    #[Url(as: 'status')] public ?string $status = null;        // opcionalni filter statusa

    protected $paginationTheme = 'tailwind';

    // Modal za „Dodijeli servisera & kreiraj nalog”
    public bool $showAssignModal = false;
    public ?int $intakeIdForAssign = null;
    public ?int $technicianId = null;
    public array $technicians = [];

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

        // --- PRIJEMI BEZ NALOGA (converted_at IS NULL) ---
        $intakes = collect();
        if ($locId) {
            $intakes = IntakeModel::query()
                ->with([
                    'customer:id,name,phone',
                    'gear:id,brand,model,customer_id',   // ⬅️ GEAR umjesto BIKE
                ])
                ->where('location_id', $locId)
                ->whereNull('converted_at')
                ->latest()
                ->paginate(10, ['*'], 'intakes_page'); // odvojena paginacija
        }

        // --- SVI RADNI NALOZI (uključuje i one bez servisera) ---
        $woQuery = WorkOrder::query()
            ->with([
                'customer:id,name,phone',
                'gear:id,brand,model,customer_id',     // ⬅️ GEAR umjesto BIKE
                'assignedUser:id,name',
            ])
            ->latest();

        if ($locId) {
            $woQuery->where('location_id', $locId);
        } else {
            // owner/admin bez lokacije ne vidi ništa
            if ($isAdminOwner) {
                $woQuery->whereRaw('1=0');
            } else {
                $woQuery->where('location_id', -1);
            }
        }

        // Filter statusa (ako je zadat kao string)
        if ($this->status) {
            $woQuery->where('status', $this->status);
        }

        // Serviser: „moji” vs „poslovnica”
        if ($isServiser && $this->scope === 'moji') {
            $woQuery->where('assigned_user_id', $user->id);
        }

        $workOrders = $woQuery->paginate(10, ['*'], 'wo_page');

        // Lista servisera za modal (trenutna lokacija)
        $this->technicians = [];
        if ($locId) {
            $this->technicians = User::role('serviser')
                ->where('location_id', $locId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->toArray();
        }

        return view('livewire.work-orders.board', compact(
            'user',
            'isAdminOwner',
            'isManager',
            'isServiser',
            'currentLocation',
            'intakes',
            'workOrders',
        ));
    }

    // Otvori modal za izbor servisera i konverziju
    public function openAssignModal(int $intakeId): void
    {
        $this->intakeIdForAssign = $intakeId;
        $this->technicianId = null;
        $this->resetErrorBag();    // ⇐ očisti stare greške
        $this->showAssignModal = true;
    }

    public function claimAndConvert(int $intakeId): void
    {
        $me = Auth::user();
        if (! $me) abort(403);

        $this->convertIntake($intakeId, $me->id);
    }

    public function convertIntake(int $intakeId): void
{
    $technicianId = $this->technicianId;   // ⇐ UZMI IZ STATE-a, NE IZ ARGUMENTA

    $user         = Auth::user();
    $isAdminOwner = $user?->hasAnyRole(['master-admin', 'vlasnik']) ?? false;
    $isManager    = $user?->hasRole('menadzer') ?? false;

    // (po tvojoj želji: dozvoli SVIMA dodjelu)
    // if (!($isAdminOwner || $isManager)) abort(403);

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

    if (!$technicianId) {
        $this->addError('technician', 'Odaberi servisera.');
        return;
    }

    $techOk = User::role('serviser')
        ->where('id', $technicianId)
        ->where('location_id', $locId)
        ->exists();

    if (!$techOk) {
        $this->addError('technician', 'Serviser nije iz ove poslovnice.');
        return;
    }

    DB::transaction(function () use ($intakeId, $locId, $user, $technicianId) {
        $intake = IntakeModel::query()
            ->where('id', $intakeId)
            ->where('location_id', $locId)
            ->whereNull('converted_at')
            ->lockForUpdate()
            ->first();

        if (!$intake) {
            $this->addError('intake', 'Prijem nije pronađen ili je već konvertovan.');
            return;
        }

        $loc    = Location::find($locId);
        $base   = ($loc?->code ?? 'WO') . '-' . now()->format('Ymd-His');
        $number = $base;
        $tries  = 0;
        while (WorkOrder::where('number', $number)->exists() && $tries < 3) {
            $number = $base . '-' . substr(strtoupper(bin2hex(random_bytes(1))), 0, 2);
            $tries++;
        }

        $wo = WorkOrder::create([
            'number'           => $number,
            'location_id'      => $locId,
            'customer_id'      => $intake->customer_id,
            'gear_id'          => $intake->gear_id,
            'assigned_user_id' => $technicianId,
            'status'           => WorkOrderStatus::RECEIVED->value,
            'created_by'       => $user?->id,
        ]);

        $intake->update([
            'converted_work_order_id' => $wo->id,
            'converted_at'            => now(),
        ]);

        session()->flash('ok', 'Radni nalog kreiran i serviser dodijeljen.');
        $this->showAssignModal = false;
        $this->technicianId = null;
        $this->resetPage('intakes_page');
        $this->resetPage('wo_page');
        $this->redirectRoute('workorders-edit', ['workorder' => $wo->id]);
    });
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
