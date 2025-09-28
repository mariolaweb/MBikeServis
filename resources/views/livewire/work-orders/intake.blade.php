<div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div class="text-lg font-semibold">
            Prijem opreme
        </div>

        @if (session('ok'))
            <div class="px-3 py-2 mb-4 text-green-800 rounded-lg bg-green-50">
                {{ session('ok') }}
            </div>
        @endif

        @if ($location)
            <div class="text-sm">
                Poslovnica:
                <span class="rounded bg-blue-500 px-2 py-0.5 text-xs font-semibold text-white">
                    {{ $location->code }}
                </span>
                <span class="pl-2 text-gray-700">
                    {{ $location->name }} ({{ $location->city }})
                </span>
            </div>
        @else
            <div class="text-sm text-yellow-700">
                Poslovnica nije izabrana.
                @if ($isAdminOwner)
                    <span class="ml-2">Izaberi poslovnicu na Dashboardu.</span>
                @endif
            </div>
        @endif
    </div>

    {{-- Forma --}}
    @php $canSubmit = (bool) $location; @endphp
    <form wire:submit.prevent="{{ $canSubmit ? 'save' : '' }}" class="grid gap-6 p-6 bg-white border rounded-2xl">
        <input type="hidden" wire:model="locId">

        {{-- Mušterija --}}
        <div>
            <div class="mb-2 text-sm font-semibold text-gray-700">Mušterija</div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs text-gray-600">Ime i prezime *</label>
                    <input type="text" wire:model.defer="customer_name" required
                        class="w-full px-3 py-2 mt-1 border rounded">
                    @error('customer_name')
                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-600">Telefon</label>
                    <input type="text" wire:model.defer="customer_phone"
                        class="w-full px-3 py-2 mt-1 border rounded">
                    @error('customer_phone')
                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Kategorija --}}
        {{-- Kategorije (pills) --}}
        <div class="space-y-2">
            <div class="block text-xs text-gray-600">Kategorija *</div>
            <div class="flex flex-wrap gap-2">
                @php
                $cats = [
                        'bike' => 'Bicikl',
                        'e-bike' => 'E-bicikl',
                        'scooter' => 'Trotinet',
                        'ski' => 'Skije',
                        'snowboard' => 'Snowboard',
                        'other' => 'Ostalo',
                ]; @endphp

                @foreach ($cats as $val => $label)
                    <label
                        class="{{ $gear_category === $val ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:border-blue-400' }} inline-flex cursor-pointer items-center rounded-full border px-3 py-1 text-sm transition">
                        <input type="radio" class="sr-only" name="gear_category" value="{{ $val }}"
                            wire:model.live="gear_category" {{-- ← LIVE! odmah šalje promjenu --}} />
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            @error('gear_category')
                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
            @enderror
        </div>


        {{-- Oprema (osnovna polja) --}}
        <div>
            <div class="mb-2 text-sm font-semibold text-gray-700">Oprema</div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs text-gray-600">Brend *</label>
                    <input type="text" wire:model="gear_brand" required
                        class="w-full px-3 py-2 mt-1 border rounded">
                    @error('gear_brand')
                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-600">Model</label>
                    <input type="text" wire:model.defer="gear_model" class="w-full px-3 py-2 mt-1 border rounded">
                    @error('gear_model')
                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-600">Serijski broj</label>
                    <input type="text" wire:model.defer="gear_serial" class="w-full px-3 py-2 mt-1 border rounded">
                    @error('gear_serial')
                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                    @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs text-gray-600">Napomena / Opis</label>
                    <textarea wire:model.defer="gear_notes" rows="2" class="w-full px-3 py-2 mt-1 border rounded"></textarea>
                    @error('gear_notes')
                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Specifična polja po kategoriji (idu u gears.attributes) --}}
        @if ($gear_category === 'bike')
            <div>
                <div class="mb-2 text-sm font-semibold text-gray-700">Bicikl — dodatno</div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs text-gray-600">Veličina točka *</label>
                        <select wire:model.defer="bike_wheel_size" class="w-full px-3 py-2 mt-1 border rounded"
                            required>
                            <option value="">—</option>
                            <option value="26">26"</option>
                            <option value="27.5">27.5"</option>
                            <option value="29">29"</option>
                        </select>
                        @error('bike_wheel_size')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Veličina rama</label>
                        <input type="text" wire:model.defer="bike_frame_size"
                            class="w-full px-3 py-2 mt-1 border rounded" placeholder="npr. M, L, 54cm">
                        @error('bike_frame_size')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        @elseif ($gear_category === 'e-bike')
            <div>
                <div class="mb-2 text-sm font-semibold text-gray-700">E-bicikl — dodatno</div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs text-gray-600">Veličina točka</label>
                        <select wire:model.defer="bike_wheel_size" class="w-full px-3 py-2 mt-1 border rounded">
                            <option value="">—</option>
                            <option value="26">26"</option>
                            <option value="27.5">27.5"</option>
                            <option value="29">29"</option>
                        </select>
                        @error('bike_wheel_size')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Veličina rama</label>
                        <input type="text" wire:model.defer="bike_frame_size"
                            class="w-full px-3 py-2 mt-1 border rounded">
                        @error('bike_frame_size')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Motor — brend *</label>
                        <input type="text" wire:model.defer="ebike_motor_brand"
                            class="w-full px-3 py-2 mt-1 border rounded" required>
                        @error('ebike_motor_brand')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Motor — model</label>
                        <input type="text" wire:model.defer="ebike_motor_model"
                            class="w-full px-3 py-2 mt-1 border rounded">
                        @error('ebike_motor_model')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Baterija (Wh)</label>
                        <input type="number" wire:model.defer="ebike_battery_capacity_wh"
                            class="w-full px-3 py-2 mt-1 border rounded" min="50" max="2000">
                        @error('ebike_battery_capacity_wh')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Serijski broj baterije</label>
                        <input type="text" wire:model.defer="ebike_battery_serial"
                            class="w-full px-3 py-2 mt-1 border rounded">
                        @error('ebike_battery_serial')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        @elseif ($gear_category === 'scooter')
            <div>
                <div class="mb-2 text-sm font-semibold text-gray-700">Trotinet — dodatno</div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs text-gray-600">Brend</label>
                        <input type="text" wire:model.defer="scooter_brand"
                            class="w-full px-3 py-2 mt-1 border rounded">
                        @error('scooter_brand')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Model</label>
                        <input type="text" wire:model.defer="scooter_model"
                            class="w-full px-3 py-2 mt-1 border rounded">
                        @error('scooter_model')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Baterija (Wh) *</label>
                        <input type="number" wire:model.defer="scooter_battery_wh"
                            class="w-full px-3 py-2 mt-1 border rounded" min="100" max="1200" required>
                        @error('scooter_battery_wh')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        @elseif ($gear_category === 'ski')
            <div>
                <div class="mb-2 text-sm font-semibold text-gray-700">Skije — dodatno</div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs text-gray-600">Dužina (cm) *</label>
                        <input type="number" wire:model.defer="ski_length_cm"
                            class="w-full px-3 py-2 mt-1 border rounded" min="100" max="210" required>
                        @error('ski_length_cm')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Vezovi</label>
                        <input type="text" wire:model.defer="ski_binding"
                            class="w-full px-3 py-2 mt-1 border rounded">
                        @error('ski_binding')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        @elseif ($gear_category === 'snowboard')
            <div>
                <div class="mb-2 text-sm font-semibold text-gray-700">Snowboard — dodatno</div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs text-gray-600">Dužina (cm) *</label>
                        <input type="number" wire:model.defer="snow_length_cm"
                            class="w-full px-3 py-2 mt-1 border rounded" min="90" max="200" required>
                        @error('snow_length_cm')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Stance</label>
                        <input type="text" wire:model.defer="snow_stance"
                            class="w-full px-3 py-2 mt-1 border rounded" placeholder="npr. Goofy / Regular">
                        @error('snow_stance')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        @elseif ($gear_category === 'other')
            <div class="text-sm text-gray-600">
                Za ovu kategoriju nema posebnih atributa. Koristi polje “Napomena / Opis” iznad za detalje (npr.
                specifični mehanizam).
            </div>
        @endif

        {{-- Dodjela servisera (opciono) --}}
        @php
            $isAssigned = $editing && !empty($this->assigned_user_id);
        @endphp
        @if ($location)
            <div>
                <div class="mb-2 text-sm font-semibold text-gray-700">Dodjela servisera (opciono)</div>
                <select wire:model="assigned_user_id" class="w-full px-3 py-2 border rounded">
                    @if ($isAssigned)
                        <option value="">— Ukloni postojećeg —</option>
                    @else
                        <option value="">— Bez dodjele —</option>
                    @endif
                    @foreach ($technicians as $tech)
                        <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                    @endforeach
                </select>
                <small class="text-xs text-gray-500">
                    @if ($isAssigned)
                        Odabirom "Ukloni postojećeg" nalog ostaje bez servisera i status se vraća na "Primljen".
                    @else
                        Možeš ostaviti bez dodjele i dodijeliti kasnije.
                    @endif
                </small>
                @error('assigned_user_id')
                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                @enderror
            </div>
        @endif


        {{-- Kontakt ERP --}}
        <div class="w-full mx-auto md:w-2/3 lg:w-1/2">
            @if ($editing)
                <div class="flex items-center justify-between pt-4 mt-2 border-t">
                    <div class="text-xs text-gray-500">
                        Test ERP integracije: otvori predračun za ovaj prijem.
                    </div>
                    <button type="button" wire:click="startErpEstimate" wire:loading.attr="disabled"
                        class="rounded bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                        Predračun u ERP-u
                    </button>
                </div>

                @if (session('error'))
                    <div class="px-3 py-2 mt-3 text-sm text-red-700 rounded bg-red-50">{{ session('error') }}</div>
                @endif
            @endif

        </div>


        <div class="flex justify-end">
            <button type="{{ $canSubmit ? 'submit' : 'button' }}" @disabled(!$canSubmit)
                class="px-4 py-2 text-white bg-blue-600 rounded-xl hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                {{ $canSubmit ? 'Sačuvaj' : 'Izaberi poslovnicu' }}
            </button>
        </div>
    </form>
</div>
