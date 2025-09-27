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
// ✨ ADDED: enum za status i helper
use Illuminate\Support\Facades\Http;
use App\Models\Intake as IntakeModel;

class Board extends Component
{
    use WithPagination;

    // URL filteri
    #[Url(as: 'scope')]    public string $scope = 'poslovnica';  // 'poslovnica' | 'moji' (za servisere)
    #[Url(as: 'status')]   public ?string $status = null;        // filter statusa (opciono)

    protected $paginationTheme = 'tailwind';

    // ✨ ADDED: za modal „Dodijeli servisera & kreiraj nalog”
    public bool $showAssignModal = false;
    public ?int $intakeIdForAssign = null;
    public ?int $technicianId = null;
    public $technicians = [];



    //testiramo metod za predračun
    public function startEstimate(int $intakeId)
    {
        // 1) URL-ovi i tokeni iz config/services.php
        $base    = rtrim(config('services.erp.base_url'), '/');      // npr. https://bilijar.mariolaweb.com/mock-erp
        $token   = config('services.erp.token');
        $return  = rtrim(config('services.erp.redirect'), '/');      // npr. https://bikeservice.mariolaweb.com

        // 2) Vrati se na tvoju rutu nakon potvrde
        $redirectUrl = $return . "/intakes/{$intakeId}/estimate/return";

        try {
            $resp = Http::withToken($token)
                ->acceptJson()
                ->post($base . '/api/v1/sessions.php', [
                    'intake_id'    => $intakeId,
                    'redirect_url' => $redirectUrl,
                ]);

            if (!$resp->ok()) {
                // greška — prikaži poruku korisniku
                session()->flash('error', 'ERP konekcija nije uspjela (' . $resp->status() . ').');
                return;
            }

            $sessionUrl = data_get($resp->json(), 'session_url');
            if (!$sessionUrl) {
                session()->flash('error', 'ERP: session_url nedostaje u odgovoru.');
                return;
            }

            // 3) Preusmjeri korisnika na mock-ERP da izabere paket
            // Livewire 3:
            return $this->redirect($sessionUrl, navigate: false);
            // (alternativa) return redirect()->away($sessionUrl);

        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'Greška pri povezivanju sa ERP-om.');
        }
    }





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
                ->paginate(10, ['*'], 'intakes_page'); // ⬅️ odvojena paginacija za intakes
        }

        // 🗑️ REMOVED: "NALOZI BEZ SERVISERA" međutabela ($pendingWo)
        // Sada WO lista uključuje i one bez servisera.

        // --- SVI RADNI NALOZI (SADA UKLJUČUJE I ONE BEZ SERVISERA) ---
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

        // 🔧 CHANGED: više NE isključujemo WO bez servisera
        // (ranije je ovdje bio filter da ih izbaci jer su bili u posebnoj tabeli)
        // $woQuery->where(function ($q) { ... })  // 🗑️ REMOVED

        $workOrders = $woQuery->paginate(10, ['*'], 'wo_page');

        // ✨ ADDED: lista servisera za trenutnu lokaciju (za modal)
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

    // ✨ ADDED: otvori modal za dodjelu servisera prije kreiranja WO
    public function openAssignModal(int $intakeId): void
    {
        $this->intakeIdForAssign = $intakeId;
        $this->technicianId = null;
        $this->showAssignModal = true;
    }

    // 🔧 CHANGED: konverzija sada ZAHTJEVA servisera i odmah kreira WO sa dodjelom
    public function convertIntake(int $intakeId, ?int $technicianId = null): void
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

        if (!$technicianId) {
            $this->addError('technician', 'Odaberi servisera.');
            return;
        }

        // Provjeri da je serviser iz iste poslovnice
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
                ->lockForUpdate() // ✨ ADDED: zaštita od dvoklika
                ->first();

            if (!$intake) {
                $this->addError('intake', 'Prijem nije pronađen ili je već konvertovan.');
                return;
            }

            // Generiši broj WO (isti princip kao ranije)
            $loc    = Location::find($locId);
            $base   = ($loc?->code ?? 'WO') . '-' . now()->format('Ymd-His');
            $number = $base;

            // Minimalni fallback da izbjegnemo rijedak sudar
            $tries = 0;
            while (WorkOrder::where('number', $number)->exists() && $tries < 3) {
                $number = $base . '-' . substr(strtoupper(bin2hex(random_bytes(1))), 0, 2);
                $tries++;
            }

            $wo = WorkOrder::create([
                'number'           => $number,
                'location_id'      => $locId,
                'customer_id'      => $intake->customer_id,
                'bike_id'          => $intake->bike_id,
                // odmah dodjela servisera:
                'assigned_user_id'    => $technicianId,
                'assigned_at'         => now(),
                'assigned_by_user_id' => $user?->id,
                // status kroz enum value
                'status'           => WorkOrderStatus::RECEIVED->value,
                'created_by'       => $user?->id,
                // (opciono) veza na intake, ako kolona postoji:
                'intake_id'        => $intake->id,
            ]);

            $intake->update([
                'converted_work_order_id' => $wo->id,
                'converted_at'            => now(),
                'converted_by_user_id'    => $user?->id, // ✨ ADDED: audit ko je konvertovao
            ]);

            session()->flash('ok', 'Radni nalog kreiran i serviser dodijeljen.');
            $this->showAssignModal = false;

            // osvježi listu
            $this->resetPage('intakes_page');
            $this->resetPage('wo_page');

            // redirect na Uredi
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
