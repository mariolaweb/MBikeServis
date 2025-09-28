<?php

namespace App\Livewire\WorkOrders;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

use App\Models\User;
use App\Models\Gear;
use App\Models\Customer;
use App\Models\Location;
use App\Models\WorkOrder;
use App\Models\Intake as IntakeModel;
use App\Enums\WorkOrderStatus;

class Intake extends Component
{
    public ?int $modelId = null;
    public bool $formLoaded = false;

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

    protected function loadModel(int $id): void
    {
        $wo = WorkOrder::with(['customer', 'gear', 'location'])->findOrFail($id);

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
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $user = Auth::user();
        $isAdminOwner = $user?->hasAnyRole(['master-admin', 'vlasnik']) ?? false;

        if ($this->modelId === null) {
            $routeId = request()->route('workorder');
            $this->modelId = $routeId ? (int)$routeId : null;
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
        ));
    }

    public function save()
    {
        $this->validate();

        $user = Auth::user();
        $canReassignOnEdit = $user?->hasAnyRole(['master-admin', 'vlasnik', 'menadzer']) ?? false;
        $editing = (bool)$this->modelId;

        if ($editing) {
            $wo = WorkOrder::with(['customer', 'gear'])->findOrFail($this->modelId);
            $locId = Location::where('id', $wo->location_id)->where('is_active', true)->value('id')
                ?? abort(422, 'Nepostojeća ili neaktivna poslovnica.');
        } else {
            $locId = Location::where('id', $this->locId)->where('is_active', true)->value('id')
                ?? abort(422, 'Nepostojeća ili neaktivna poslovnica.');
            $wo = null;
        }

        // Validacija da je serviser iz iste poslovnice
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
        ];

        $assignPayload = [
            'assigned_user_id'    => $this->assigned_user_id ?: null,
            'assigned_at'         => $this->assigned_user_id ? now() : null,
            'assigned_by_user_id' => $this->assigned_user_id ? $user->id : null,
        ];

        return DB::transaction(function () use ($editing, $canReassignOnEdit, $locId, $user, $customerData, $assignPayload, $wo) {

            // -------- CREATE --------
            if (!$editing) {
                // Customer (upsert po telefonu)
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

                // Gear
                $gear = Gear::create([
                    'customer_id'   => $customer->id,
                    'category'      => $this->gear_category,
                    'brand'         => $this->gear_brand,
                    'model'         => $this->gear_model,
                    'serial_number' => $this->gear_serial,
                    'attributes'    => $this->buildAttributesArray(),
                    'notes'         => $this->gear_notes,
                ]);

                // Intake
                $intake = IntakeModel::create([
                    'location_id'         => $locId,
                    'received_by_user_id' => $user->id,
                    'customer_id'         => $customer->id,
                    'gear_id'             => $gear->id,
                ]);

                // Ako je serviser izabran → odmah WO
                if (!empty($assignPayload['assigned_user_id'])) {
                    $number = $this->makeWorkOrderNumber($locId);

                    $woNew = WorkOrder::create([
                        'intake_id'   => $intake->id,
                        'location_id' => $locId,
                        'customer_id' => $customer->id,
                        'gear_id'     => $gear->id,
                        'status'      => WorkOrderStatus::RECEIVED->value,
                        'number'      => $number,
                    ] + $assignPayload);

                    $intake->update([
                        'converted_at'         => now(),
                        'converted_by_user_id' => $user->id,
                    ]);

                    session()->flash('ok', 'Prijem evidentiran i nalog kreiran.');
                    return redirect()->route('workorders-edit', ['workorder' => $woNew->id]);
                }

                session()->flash('ok', 'Prijem evidentiran – bez dodijeljenog servisera.');
                return redirect()->route('workorders-board');
            }

            // -------- UPDATE --------
            // Customer
            $wo->customer->update([
                'name'  => $customerData['name'],
                'phone' => $customerData['phone'],
            ]);

            // Gear (osiguraj da postoji; ako ne, kreiraj pa poveži)
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

            // Re-assign serviser (samo za menadžer/admin/owner)
            if ($canReassignOnEdit) {
                $prev = $wo->assigned_user_id;
                $new  = $assignPayload['assigned_user_id'];

                if ($prev && !$new) {
                    $wo->update([
                        'assigned_user_id'    => null,
                        'assigned_at'         => null,
                        'assigned_by_user_id' => null,
                        'status'              => WorkOrderStatus::RECEIVED->value,
                    ]);
                } elseif (!$prev && $new) {
                    $wo->update($assignPayload);
                } elseif ($prev && $new && $prev != $new) {
                    $wo->update($assignPayload);
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
}
