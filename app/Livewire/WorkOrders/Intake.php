<?php

namespace App\Livewire\WorkOrders;

use App\Models\Gear;
use App\Models\User;
use Livewire\Component;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Location;

use App\Models\WorkOrder;
use Illuminate\Support\Str;
use App\Enums\WorkOrderStatus;
use App\Services\QrCodeService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Models\Intake as IntakeModel;

class Intake extends Component
{
    public ?int $modelId = null;
    public bool $formLoaded = false;
    public bool $canAcceptEstimate = false;

    // Customer
    #[Validate('required|string|min:2')]
    public string $customer_name = '';

    #[Validate('nullable|string|max:50')]
    public ?string $customer_phone = null;

    // Gear (osnovna polja)
    #[Validate('required|in:bike,e-bike,scooter,ski,snowboard,other')]
    public string $gear_category = 'bike';

    #[Validate('required|string|min:2|max:80')]
    public string $gear_brand = '';

    #[Validate('nullable|string|max:100')]
    public ?string $gear_model = null;

    #[Validate('nullable|string|max:80')]
    public ?string $gear_serial = null;

    #[Validate('nullable|string|max:500')]
    public ?string $gear_notes = null;

    // Gear (specifična polja → idu u attributes JSON)
    // bike
    #[Validate('nullable|string|max:10|required_if:gear_category,bike')]
    public ?string $bike_wheel_size = null;   // 26, 27.5, 29

    #[Validate('nullable|string|max:10')]
    public ?string $bike_frame_size = null;   // npr. M, L, 54cm

    // E-BIKE (specifično za električne bicikle)
    #[Validate('nullable|string|max:60|required_if:gear_category,e-bike')]
    public ?string $ebike_motor_brand = null;

    #[Validate('nullable|string|max:60')]
    public ?string $ebike_motor_model = null;

    #[Validate('nullable|integer|between:50,2000')]
    public ?int $ebike_battery_capacity_wh = null;

    #[Validate('nullable|string|max:80')]
    public ?string $ebike_battery_serial = null;

    // SCOOTER (e-trotinet)
    #[Validate('nullable|integer|between:100,1200|required_if:gear_category,scooter')]
    public ?int $scooter_battery_wh = null;

    #[Validate('nullable|string|max:60')]
    public ?string $scooter_brand = null;

    #[Validate('nullable|string|max:60')]
    public ?string $scooter_model = null;

    // ski
    #[Validate('nullable|integer|between:100,210|required_if:gear_category,ski')]
    public ?int $ski_length_cm = null;

    #[Validate('nullable|string|max:60')]
    public ?string $ski_binding = null;

    // snowboard
    #[Validate('nullable|integer|between:90,200|required_if:gear_category,snowboard')]
    public ?int $snow_length_cm = null;

    #[Validate('nullable|string|max:40')]
    public ?string $snow_stance = null;       // Goofy/Regular

    // Assignment
    #[Validate('nullable|integer|exists:users,id')]
    public ?int $assigned_user_id = null;

    public ?int $locId = null;
    public string $qrSvgPublicPath = '';
    public int $workOrderId;



    public function startErpEstimate(): mixed
    {
        // radi samo na EDIT-u (postoji WO)
        if (! $this->modelId) {
            session()->flash('error', 'ERP: Nalog još nije kreiran.');
            return null;
        }

        $wo = WorkOrder::select('id')->find($this->modelId);
        if (! $wo) {
            session()->flash('error', 'ERP: Nalog nije pronađen.');
            return null;
        }

        // pronađi pripadajući intake preko veze na intakes.converted_work_order_id
        $intakeId = IntakeModel::where('converted_work_order_id', $wo->id)->value('id');

        if (! $intakeId) {
            // ako nalog nije nastao iz prijema, blokiraj ERP (preporuka: kreirati minimalni Intake prije ERP-a)
            session()->flash('error', 'ERP: Prijem (intake) za ovaj nalog nije pronađen.');
            return null;
        }

        $base   = rtrim(config('services.erp.base_url'), '/');   // npr. https://.../mock-erp
        $token  = config('services.erp.token');
        $return = rtrim(config('services.erp.redirect'), '/');   // npr. https://tvojapp.com

        // gdje će ERP da nas vrati
        $redirectUrl = $return . "/intakes/{$intakeId}/estimate/return";

        try {
            $resp = \Illuminate\Support\Facades\Http::withToken($token)
                ->acceptJson()
                ->post($base . '/api/v1/sessions.php', [
                    'intake_id'    => $intakeId,
                    'redirect_url' => $redirectUrl,
                ]);

            if (! $resp->ok() || empty($resp['session_url'])) {
                session()->flash('error', 'ERP greška: HTTP ' . $resp->status() . ' — ' . mb_strimwidth($resp->body(), 0, 200, '…'));
                return null;
            }

            // skok u ERP checkout/session
            return $this->redirect($resp['session_url'], navigate: false);
        } catch (\Throwable $e) {
            session()->flash('error', 'ERP exception: ' . $e->getMessage());
            return null;
        }
    }


    protected function loadModel(int $id): void
    {
        $wo = WorkOrder::with(['customer', 'gear', 'location', 'intake.gear'])->findOrFail($id);

        $this->workOrderId = $wo->id;

        $this->locId          = $wo->location_id;
        $this->customer_name  = (string)$wo->customer?->name;
        $this->customer_phone = $wo->customer?->phone;

        // Gear → direktno mapiranje
        $this->gear_category = (string)($wo->gear?->category ?? 'bike');
        $this->gear_brand    = (string)($wo->gear?->brand ?? '');
        $this->gear_model    = $wo->gear?->model;
        $this->gear_serial   = $wo->gear?->serial_number;
        $this->gear_notes    = $wo->gear?->notes;

        // Attributes → po kategoriji
        $attr = (array)($wo->gear?->attributes ?? []);
        if ($this->gear_category === 'bike') {
            $this->bike_wheel_size = $attr['wheel_size'] ?? null;
            $this->bike_frame_size = $attr['frame_size'] ?? null;
        } elseif ($this->gear_category === 'e-bike') {
            $this->bike_wheel_size          = $attr['wheel_size'] ?? null;
            $this->bike_frame_size          = $attr['frame_size'] ?? null;
            $this->ebike_motor_brand        = $attr['motor_brand'] ?? null;
            $this->ebike_motor_model        = $attr['motor_model'] ?? null;
            $this->ebike_battery_capacity_wh = isset($attr['battery_capacity_wh']) ? (int)$attr['battery_capacity_wh'] : null;
            $this->ebike_battery_serial     = $attr['battery_serial'] ?? null;
        } elseif ($this->gear_category === 'scooter') {
            $this->scooter_brand = $attr['brand'] ?? null;
            $this->scooter_model = $attr['model'] ?? null;
            $this->scooter_battery_wh = isset($attr['battery_wh']) ? (int)$attr['battery_wh'] : null;
        } elseif ($this->gear_category === 'ski') {
            $this->ski_length_cm = isset($attr['length_cm']) ? (int)$attr['length_cm'] : null;
            $this->ski_binding   = $attr['binding'] ?? null;
        } elseif ($this->gear_category === 'snowboard') {
            $this->snow_length_cm = isset($attr['length_cm']) ? (int)$attr['length_cm'] : null;
            $this->snow_stance    = $attr['stance'] ?? null;
        }

        $this->assigned_user_id = $wo->assigned_user_id;

        // ✅ Generisanje QR koda na kraju
        $qr = app(QrCodeService::class);
        $this->qrSvgPublicPath = $qr->makeForPublicToken(
            $wo->public_track_url,
            $wo->public_token
        );
    }

    //Resetovanje svih polja kada se prebacujemo iz jednog radnog naloga u drugi
    protected function resetFormForNewModel(?int $newId): void
    {
        // resetuj sve što je vezano za prethodni nalog
        $this->reset([
            'formLoaded',
            // Customer
            'customer_name',
            'customer_phone',
            // Gear osnovno
            'gear_category',
            'gear_brand',
            'gear_model',
            'gear_serial',
            'gear_notes',
            // Gear atributi
            'bike_wheel_size',
            'bike_frame_size',
            'ebike_motor_brand',
            'ebike_motor_model',
            'ebike_battery_capacity_wh',
            'ebike_battery_serial',
            'scooter_brand',
            'scooter_model',
            'scooter_battery_wh',
            'ski_length_cm',
            'ski_binding',
            'snow_length_cm',
            'snow_stance',
            // Assignment
            'assigned_user_id',
            // Lokacija
            'locId',
        ]);

        $this->modelId   = $newId;
        $this->formLoaded = false;

        if ($this->modelId) {
            // svježe učitaj iz baze
            $this->loadModel($this->modelId);
            $this->formLoaded = true;
        }
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $user = Auth::user();
        $isAdminOwner = $user?->hasAnyRole(['master-admin', 'vlasnik']) ?? false;

        // if ($this->modelId === null) {
        //     $routeId = request()->route('workorder');
        //     $this->modelId = $routeId ? (int)$routeId : null;
        // }

        // Izmjena da nam se nova ponuda ne prikazuje i u drugom wo
        $currentRouteId = request()->route('workorder');
        $currentRouteId = $currentRouteId ? (int)$currentRouteId : null;

        if ($currentRouteId !== $this->modelId) {
            // promjena naloga → reset ključnih polja i učitaj novi model
            $this->resetFormForNewModel($currentRouteId);
        }
        $editing = (bool)$this->modelId;

        $location = null;
        $locId    = null;

        if ($editing) {
            $wo = WorkOrder::with('location:id,code,name,city')->findOrFail($this->modelId);
            $location = $wo->location;
            $locId    = $wo->location_id;

            if (!$this->formLoaded) {
                $this->loadModel($this->modelId);
                $this->formLoaded = true;
            }
        } else {
            $rawLocId = $isAdminOwner ? session('current_location') : $user?->location_id;

            $location = $rawLocId
                ? Location::select('id', 'code', 'name', 'city')->where('is_active', true)->find($rawLocId)
                : null;

            $locId = $location?->id;
        }

        $this->locId = $locId;

        $canAssign = $user?->hasAnyRole(['master-admin', 'vlasnik', 'menadzer']) ?? false;

        $this->canAcceptEstimate = $user?->hasAnyRole(['master-admin', 'vlasnik', 'menadzer', 'serviser']) ?? false;

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
            'editing',
            // 'canAcceptEstimate',
        ));
    }

    public function save()
    {
        $this->validate();

        $user = Auth::user();
        $canReassignOnEdit = $user?->hasAnyRole(['master-admin', 'vlasnik', 'menadzer']) ?? false;
        $editing = (bool) $this->modelId;

        if ($editing) {
            $wo = WorkOrder::with(['customer', 'gear'])->findOrFail($this->modelId);

            $locId = Location::where('id', $wo->location_id)
                ->where('is_active', true)
                ->value('id') ?? abort(422, 'Nepostojeća ili neaktivna poslovnica.');
        } else {
            $wo = null;

            $locId = Location::where('id', $this->locId)
                ->where('is_active', true)
                ->value('id') ?? abort(422, 'Nepostojeća ili neaktivna poslovnica.');
        }

        // Validacija da je serviser iz iste poslovnice (ako je izabran)
        if ($this->assigned_user_id) {
            $techOk = User::role('serviser')
                ->where('id', $this->assigned_user_id)
                ->where('location_id', $locId)
                ->exists();

            if (!$techOk) {
                return $this->addError('assigned_user_id', 'Serviser nije iz ove poslovnice.');
            }
        }

        $customerData = [
            'name'  => $this->customer_name,
            'phone' => $this->customer_phone,
            // email/city/address/notes možeš dodati kad ih imaš u formi
        ];

        return DB::transaction(function () use ($editing, $canReassignOnEdit, $locId, $user, $customerData, $wo) {

            /* ========== CREATE ========== */
            if (!$editing) {
                // 1) Kupac (upsert po telefonu)
                if (!empty($customerData['phone'])) {
                    $customer = Customer::firstOrCreate(
                        ['phone' => $customerData['phone']],
                        ['name'  => $customerData['name']]
                    );
                    if (!$customer->wasRecentlyCreated && $customer->name !== $customerData['name']) {
                        $customer->update(['name' => $customerData['name']]);
                    }
                } else {
                    $customer = Customer::create([
                        'name'  => $customerData['name'],
                        'phone' => null,
                    ]);
                }

                // 2) Gear
                $gear = Gear::create([
                    'customer_id'   => $customer->id,
                    'category'      => $this->gear_category,
                    'brand'         => $this->gear_brand,
                    'model'         => $this->gear_model,
                    'serial_number' => $this->gear_serial,
                    'attributes'    => $this->buildAttributesArray(),
                    'notes'         => $this->gear_notes,
                ]);

                // 3) Intake (postojeće kolone)
                $intake = IntakeModel::create([
                    'location_id' => $locId,
                    'customer_id' => $customer->id,
                    'gear_id'     => $gear->id,
                    'created_by'  => $user?->id,
                ]);

                // 4) Ako je serviser izabran → odmah WO
                if (!empty($this->assigned_user_id)) {
                    $number = $this->makeWorkOrderNumber($locId);

                    $woNew = WorkOrder::create([
                        'number'           => $number,
                        'location_id'      => $locId,
                        'customer_id'      => $customer->id,
                        'gear_id'          => $gear->id,
                        'assigned_user_id' => $this->assigned_user_id,
                        'status'           => WorkOrderStatus::RECEIVED->value,
                        'created_by'       => $user?->id,
                        'public_token'     => (string) Str::ulid(),   // ✅ DODANO
                    ]);

                    // Veži intake → WO
                    $intake->update([
                        'converted_work_order_id' => $woNew->id,
                        'converted_at'            => now(),
                    ]);

                    session()->flash('ok', 'Prijem evidentiran i nalog kreiran.');
                    return redirect()->route('workorders-edit', ['workorder' => $woNew->id]);
                }

                // 5) Bez servisera: samo prijem
                session()->flash('ok', 'Prijem evidentiran – bez dodijeljenog servisera.');
                return redirect()->route('workorders-board');
            }

            /* ========== UPDATE ========== */

            // 1) Kupac
            $wo->customer->update([
                'name'  => $customerData['name'],
                'phone' => $customerData['phone'],
            ]);

            // 2) Gear (kreiraj ako nedostaje)
            if (!$wo->gear) {
                $gear = Gear::create([
                    'customer_id'   => $wo->customer_id,
                    'category'      => $this->gear_category,
                    'brand'         => $this->gear_brand,
                    'model'         => $this->gear_model,
                    'serial_number' => $this->gear_serial,
                    'attributes'    => $this->buildAttributesArray(),
                    'notes'         => $this->gear_notes,
                ]);
                $wo->update(['gear_id' => $gear->id]);
            } else {
                $wo->gear->update([
                    'category'      => $this->gear_category,
                    'brand'         => $this->gear_brand,
                    'model'         => $this->gear_model,
                    'serial_number' => $this->gear_serial,
                    'attributes'    => $this->buildAttributesArray(),
                    'notes'         => $this->gear_notes,
                ]);
            }

            // 3) Backfill veze Intake ↔ WO ako nedostaje
            //    (npr. WO je nekad kreiran bez linka, ili je link izgubljen)
            $hasLinkedIntake = IntakeModel::where('converted_work_order_id', $wo->id)->exists();
            if (! $hasLinkedIntake) {
                $candidate = IntakeModel::query()
                    ->where('location_id', $wo->location_id)
                    ->where('customer_id', $wo->customer_id)
                    ->where('gear_id', $wo->gear_id)           // bitno da je isti komad opreme
                    ->orderByDesc('created_at')
                    ->first();

                if ($candidate) {
                    $candidate->update([
                        'converted_work_order_id' => $wo->id,
                        'converted_at'            => $candidate->converted_at ?? now(),
                    ]);
                }
            }

            // 4) Re-assign serviser (samo menadžer/admin/owner)
            if ($canReassignOnEdit) {
                $prev = $wo->assigned_user_id;
                $new  = $this->assigned_user_id ?: null;

                if ($prev && !$new) {
                    $wo->update([
                        'assigned_user_id' => null,
                        'status'           => WorkOrderStatus::RECEIVED->value,
                    ]);
                } elseif (!$prev && $new) {
                    $wo->update(['assigned_user_id' => $new]);
                } elseif ($prev && $new && $prev != $new) {
                    $wo->update(['assigned_user_id' => $new]);
                }
            }

            session()->flash('ok', 'Nalog sačuvan.');
            return redirect()->route('workorders-edit', ['workorder' => $wo->id]);
        });
    }

    // Metoda koja briše stare vrijednosti atributa kada promijeniš kategoriju
    public function updatedGearCategory(string $value): void
    {
        // Očisti sve kategorijske atribute da ne “curi” stara vrijednost u novu sekciju
        $this->reset([
            // bike
            'bike_wheel_size',
            'bike_frame_size',
            // e-bike
            'ebike_motor_brand',
            'ebike_motor_model',
            'ebike_battery_capacity_wh',
            'ebike_battery_serial',
            // scooter
            'scooter_brand',
            'scooter_model',
            'scooter_battery_wh',
            // ski
            'ski_length_cm',
            'ski_binding',
            // snowboard
            'snow_length_cm',
            'snow_stance',
        ]);

        // Po želji: resetuj validation errors vezane za stare fieldove
        $this->resetErrorBag();
        $this->resetValidation();
    }

    private function buildAttributesArray(): array
    {
        return match ($this->gear_category) {
            'bike' => $this->filterNulls([
                'wheel_size' => $this->bike_wheel_size,
                'frame_size' => $this->bike_frame_size,
                // možeš naknadno dodati i is_electric flag ako poželiš
            ]),
            'e-bike' => $this->filterNulls([
                'wheel_size'        => $this->bike_wheel_size,     // ostavimo iste “bike” metrike ako želiš
                'frame_size'        => $this->bike_frame_size,
                'motor_brand'       => $this->ebike_motor_brand,
                'motor_model'       => $this->ebike_motor_model,
                'battery_capacity_wh' => $this->ebike_battery_capacity_wh,
                'battery_serial'    => $this->ebike_battery_serial,
            ]),
            'scooter' => $this->filterNulls([
                'brand'             => $this->scooter_brand,
                'model'             => $this->scooter_model,
                'battery_wh'        => $this->scooter_battery_wh,
            ]),
            'ski' => $this->filterNulls([
                'length_cm' => $this->ski_length_cm,
                'binding'   => $this->ski_binding,
            ]),
            'snowboard' => $this->filterNulls([
                'length_cm' => $this->snow_length_cm,
                'stance'    => $this->snow_stance,
            ]),
            'other' => [],
            default => [],
        };
    }


    private function filterNulls(array $data): array
    {
        return array_filter($data, static fn($v) => !is_null($v) && $v !== '');
    }

    private function makeWorkOrderNumber(int $locationId): string
    {
        $code = Location::where('id', $locationId)->value('code') ?? 'WO';
        $base = $code . '-' . now()->format('Ymd-His');
        $number = $base;

        $tries = 0;
        while (WorkOrder::where('number', $number)->exists() && $tries < 3) {
            $number = $base . '-' . Str::upper(Str::random(2));
            $tries++;
        }
        if (WorkOrder::where('number', $number)->exists()) {
            $number = $code . '-' . Str::ulid();
        }

        return $number;
    }

    public function acceptEstimate(): void
    {
        if (! $this->modelId) return;

        $user = Auth::user();
        if (! ($user?->hasAnyRole(['master-admin', 'vlasnik', 'menadzer', 'serviser']) ?? false)) return;

        DB::transaction(function () {
            // Učitaj WO (aktivne stavke su nam korisne, ali nisu blokada)
            $wo = WorkOrder::with(['items' => fn($q) => $q->active()])->findOrFail($this->modelId);

            // ➜ UZMI NAJNOVIJI PENDING estimate za OVAJ WO (ne bilo koji 'latest')
            $est = \App\Models\Estimate::where('work_order_id', $wo->id)
                ->where('status', 'pending')
                ->orderByDesc('received_at')
                ->with('items')
                ->first();

            // Nema šta prihvatiti?
            if (! $est || $est->items->isEmpty()) {
                return;
            }

            // ➜ APPEND: svaku stavku dodaj kao novi red u wo_items
            foreach ($est->items as $row) {
                $wo->items()->create([
                    'sku'        => $row->sku,
                    'name'       => $row->name,
                    'kind'       => null,              // npr. 'part'/'service' ako uvedeš tip
                    'qty'        => $row->qty,
                    'unit_price' => $row->unit_price,
                    'added_by'   => Auth::id(),
                    // line_total se automatski računa u WoItem::booted()
                ]);

                /* ️⃣ Ako želiš MERGE umjesto novog reda (po SKU+unit_price),
               zamijeni blok iznad ovim:

            $existing = null;
            if (!empty($row->sku)) {
                $existing = $wo->items()->active()
                    ->where('sku', $row->sku)
                    ->where('unit_price', $row->unit_price)
                    ->first();
            } else {
                $existing = $wo->items()->active()
                    ->whereNull('sku')
                    ->where('name', $row->name)
                    ->where('unit_price', $row->unit_price)
                    ->first();
            }

            if ($existing) {
                $existing->qty = (float)$existing->qty + (float)$row->qty;
                $existing->save(); // line_total će se preračunati u saving hook-u
            } else {
                $wo->items()->create([
                    'sku'        => $row->sku,
                    'name'       => $row->name,
                    'kind'       => null,
                    'qty'        => $row->qty,
                    'unit_price' => $row->unit_price,
                    'added_by'   => Auth::id(),
                ]);
            }
            */
            }

            // Označi estimate prihvaćenim
            $est->update([
                'accepted_by' => Auth::id(),
                'accepted_at' => now(),
                'status'      => 'accepted',
            ]);

            // (opciono) update status WO
            $wo->update(['status' => WorkOrderStatus::IN_PROGRESS]);
        });

        $this->dispatch('toast', type: 'success', message: 'Ponuda prihvaćena – stavke dodane u nalog.');
    }

    public function declineEstimate(): void
    {
        if (! $this->modelId) return;

        $user = Auth::user();
        if (! ($user?->hasAnyRole(['master-admin', 'vlasnik', 'menadzer', 'serviser']) ?? false)) return;

        // Gađaj BAŠ pending estimate za ovaj WO (ne latest bilo koji)
        $est = \App\Models\Estimate::where('work_order_id', $this->modelId)
            ->where('status', 'pending')
            ->orderByDesc('received_at')
            ->first();

        if (! $est) return;

        $est->update([
            'accepted_by' => null,
            'accepted_at' => null,
            'status'      => 'declined',
        ]);

        $this->dispatch('toast', type: 'info', message: 'Ponuda odbijena.');
    }

    // kada na već postojeće dijelove u nalogu dodajemo nove, pa dok ih ne prihvatimo
    public function getPendingEstimateProperty()
    {
        if (! $this->modelId) return null;

        return Estimate::where('work_order_id', $this->modelId)
            ->where('status', 'pending')
            ->orderByDesc('received_at')
            ->with('items')
            ->first();
    }

    public function getDisplayItemsProperty()
    {
        // Ako nismo u editu ili nije učitan WO – nema ništa
        if (! $this->modelId ?? null) {
            return collect();
        }

        // Učitaj svježe relacije potrebne za prikaz
        $wo = WorkOrder::with([
            'woItems' => fn($q) => $q->active(),   // samo aktivne wo stavke
            'latestEstimate.items',
        ])->find($this->modelId);

        if (! $wo) {
            return collect();
        }

        // 1) Ako postoje wo_items → oni imaju prioritet
        if ($wo->woItems->isNotEmpty()) {
            return $wo->woItems->map(fn($i) => [
                'type'       => 'wo',
                'sku'        => $i->sku,
                'name'       => $i->name,
                'qty'        => (float)$i->qty,
                'unit_price' => (float)$i->unit_price,
                'line_total' => (float)$i->line_total,
            ]);
        }

        // 2) Inače prikaži estimate_items iz najnovijeg estimate-a
        $est = $wo->latestEstimate;
        if ($est && $est->items->isNotEmpty()) {
            return $est->items->map(fn($i) => [
                'type'       => 'estimate',
                'sku'        => $i->sku,
                'name'       => $i->name,
                'qty'        => (float)$i->qty,
                'unit_price' => (float)$i->unit_price,
                'line_total' => (float)$i->line_total,
            ]);
        }

        return collect();
    }
}
