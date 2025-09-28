<?php

namespace App\Livewire\WorkOrders;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Bike;
use App\Models\Customer;
use App\Models\Location;
use App\Models\WorkOrder;
use App\Models\Intake as IntakeModel;
use App\Enums\WorkOrderStatus;

class Intake extends Component
{
    public ?int $modelId = null;
    public bool $formLoaded = false;

    #[Validate('required|string|min:2')]
    public string $customer_name = '';

    #[Validate('nullable|string|max:50')]
    public ?string $customer_phone = null;

    #[Validate('required|string|min:2')]
    public string $bike_brand = '';

    #[Validate('nullable|string|max:100')]
    public ?string $bike_model = null;

    #[Validate('nullable|string|max:500')]
    public ?string $bike_description = null;

    // vidi samo admin/vlasnik/menadžer; nullable
    #[Validate('nullable|integer|exists:users,id')]
    public ?int $assigned_user_id = null;

    // persist lokacije kroz Livewire zahtjeve (postavljeno u renderu)
    public ?int $locId = null;

    protected function loadModel(int $id): void
    {
        $wo = WorkOrder::with(['customer', 'bike', 'location'])->findOrFail($id);

        // persist lokacije (treba za submit)
        $this->locId = $wo->location_id;

        // polja mušterije
        $this->customer_name  = (string) $wo->customer?->name;
        $this->customer_phone = $wo->customer?->phone;

        // polja bicikla
        $this->bike_brand       = (string) $wo->bike?->brand;
        $this->bike_model       = $wo->bike?->model;
        $this->bike_description = $wo->bike?->description;

        // dodjela (može biti null)
        $this->assigned_user_id = $wo->assigned_user_id;
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $user = Auth::user();
        $isAdminOwner = $user?->hasAnyRole(['master-admin', 'vlasnik']) ?? false;

        // ✅ BOOTSTRAP modelId SAMO JEDNOM iz rute (Livewire POST nema route param!)
        if ($this->modelId === null) {
            $routeId = request()->route('workorder');
            $this->modelId = $routeId ? (int) $routeId : null;
        }
        $editing = (bool) $this->modelId;

        // Lokacija i entiteti za prikaz
        $location = null;
        $locId    = null;

        if ($editing) {
            // EDIT: lokacija iz postojećeg WO (izvor istine)
            $wo = WorkOrder::with('location:id,code,name,city')->findOrFail($this->modelId);
            $location = $wo->location;
            $locId    = $wo->location_id;

            // ✅ Popuni formu JEDNOM (na prvom renderu edita)
            if (!$this->formLoaded) {
                $this->loadModel($this->modelId);
                $this->formLoaded = true;
            }
        } else {
            // CREATE: owner/admin → session('current_location'); ostali → user->location_id
            $rawLocId = $isAdminOwner ? session('current_location') : $user?->location_id;

            $location = $rawLocId
                ? Location::select('id', 'code', 'name', 'city')->where('is_active', true)->find($rawLocId)
                : null;

            $locId = $location?->id;
        }

        // persist za naredne korake (submit itd.)
        $this->locId = $locId;

        // ko smije vidjeti/dodijeliti servisera (ostaje za EDIT pravila; na CREATE svi mogu dodijeliti)
        $canAssign = $user?->hasAnyRole(['master-admin', 'vlasnik', 'menadzer']) ?? false;

        // 🔧 CHANGED: punimo listu tehničara uvijek kad ima lokacije (bez role gate-a)
        $technicians = collect();
        if ($this->locId) {
            $technicians = User::role('serviser')
                ->where('location_id', $this->locId)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return view('livewire.work-orders.intake', compact(
            'user',
            'isAdminOwner',
            'location',
            'canAssign',
            'technicians',
            'editing', // može ti trebati u blade-u (nije obavezno)
        ));
    }

    public function save()
    {
        $this->validate();

        $user      = Auth::user();
        $canReassignOnEdit = $user?->hasAnyRole(['master-admin', 'vlasnik', 'menadzer']) ?? false;
        $editing   = (bool) $this->modelId;

        // ✅ Definiši $wo prije closure-a (da je siguran u use(...))
        $wo = null;

        // locId određujemo PO GRANAMA (UPDATE via WO, CREATE via $this->locId)
        if ($editing) {
            $wo = WorkOrder::with(['customer', 'bike'])->findOrFail($this->modelId);

            $locId = Location::where('id', $wo->location_id)
                ->where('is_active', true)
                ->value('id') ?? abort(422, 'Nepostojeća ili neaktivna poslovnica.');
        } else {
            $locId = Location::where('id', $this->locId)
                ->where('is_active', true)
                ->value('id') ?? abort(422, 'Nepostojeća ili neaktivna poslovnica.');
        }

        // ---- zajednički payloadi (bez DB poziva) ----
        $customerData = [
            'name'  => $this->customer_name,
            'phone' => $this->customer_phone,
        ];

        $bikeData = [
            'brand'       => $this->bike_brand,
            'model'       => $this->bike_model,
            'description' => $this->bike_description,
        ];

        $intakeData = [
            'location_id'         => $locId,
            'received_by_user_id' => $user->id,
        ];

        $assignPayload = [
            'assigned_user_id'    => $this->assigned_user_id ?: null,
            'assigned_at'         => $this->assigned_user_id ? now() : null,
            'assigned_by_user_id' => $this->assigned_user_id ? $user->id : null,
        ];

        // dodatna zaštita: serviser mora biti iz iste poslovnice
        if ($this->assigned_user_id) {
            $techOk = User::role('serviser')
                ->where('id', $this->assigned_user_id)
                ->where('location_id', $locId)
                ->exists();

            if (!$techOk) {
                return $this->addError('assigned_user_id', 'Serviser nije iz ove poslovnice.');
            }
        }

        return DB::transaction(function () use (
            $editing, $canReassignOnEdit, $locId, $user,
            $customerData, $bikeData, $intakeData, $assignPayload, $wo
        ) {

            // ============= CREATE =============
            if (!$editing) {
                // 1) Kupac (upsert po telefonu, ako je unesen)
                if (!empty($customerData['phone'])) {
                    $customer = Customer::firstOrCreate(
                        ['phone' => $customerData['phone']],
                        ['name'  => $customerData['name']]
                    );
                    if (!$customer->wasRecentlyCreated && $customer->name !== $customerData['name']) {
                        $customer->update(['name' => $customerData['name']]);
                    }
                } else {
                    $customer = Customer::create(['name' => $customerData['name'], 'phone' => null]);
                }

                // 2) Bicikl
                $bike = Bike::create($bikeData + ['customer_id' => $customer->id]);

                // 3) Intake (audit)
                $intake = IntakeModel::create($intakeData + [
                    'customer_id' => $customer->id,
                    'bike_id'     => $bike->id,
                ]);

                // 4) Opcionalno WO (samo ako je serviser izabran)
                if (!empty($assignPayload['assigned_user_id'])) {
                    // ✨ ADDED: broj WO se generiše ovdje (u istoj transakciji)
                    $number = $this->makeWorkOrderNumber($locId);

                    $woNew = WorkOrder::create([
                        'intake_id'        => $intake->id,
                        'location_id'      => $locId,
                        'customer_id'      => $customer->id,
                        'bike_id'          => $bike->id,
                        // 🔧 CHANGED: status kroz enum value
                        'status'           => WorkOrderStatus::RECEIVED->value,
                        // ✨ ADDED:
                        'number'           => $number,
                    ] + $assignPayload);

                    $intake->update([
                        'converted_at'         => now(),
                        'converted_by_user_id' => $user->id,
                    ]);

                    return redirect()->route('workorders-edit', ['workorder' => $woNew->id]);
                }

                session()->flash('ok', 'Prijem evidentiran – bez dodijeljenog servisera.');
                return redirect()->route('workorders-board');
            }

            // ============= UPDATE =============
            // $wo je već učitan iznad; radi se u istoj transakciji
            $wo->customer->update([
                'name'  => $customerData['name'],
                'phone' => $customerData['phone'],
            ]);

            $wo->bike->update($bikeData);

            if ($canReassignOnEdit) {
                $prev = $wo->assigned_user_id;
                $new  = $assignPayload['assigned_user_id'];

                if ($prev && !$new) {
                    // uklanjanje dodjele → WO ostaje u listi, bez servisera
                    $wo->update([
                        'assigned_user_id'    => null,
                        'assigned_at'         => null,
                        'assigned_by_user_id' => null,
                        // 🔧 CHANGED: status kroz enum value (ako vraćaš na RECEIVED)
                        'status'              => WorkOrderStatus::RECEIVED->value,
                    ]);
                } elseif (!$prev && $new) {
                    // prva dodjela
                    $wo->update($assignPayload);
                } elseif ($prev && $new && $prev != $new) {
                    // zamjena servisera
                    $wo->update($assignPayload);
                }
            }

            session()->flash('ok', 'Nalog sačuvan.');
            return redirect()->route('workorders-edit', ['workorder' => $wo->id]);
        });
    }

    // ✨ ADDED: generator broja naloga, bez dodatnih tabela
    private function makeWorkOrderNumber(int $locationId): string
    {
        $code = Location::where('id', $locationId)->value('code') ?? 'WO';

        // Osnovni format: CODE-YYYYMMDD-HHMMSS
        $base = $code . '-' . now()->format('Ymd-His');
        $number = $base;

        // Minimalna zaštita od rijetkog sudara (u istoj sekundi):
        $tries = 0;
        while (WorkOrder::where('number', $number)->exists() && $tries < 3) {
            // dodaj kratki random sufiks
            $number = $base . '-' . Str::upper(Str::random(2));
            $tries++;
        }
        // Ako i dalje postoji (ekstremno rijetko), fallback na ULID (i dalje čuva CODE prefiks)
        if (WorkOrder::where('number', $number)->exists()) {
            $number = $code . '-' . Str::ulid();
        }

        return $number;
    }
}
