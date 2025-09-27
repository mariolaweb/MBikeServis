<div class="mx-auto space-y-8 max-w-7xl sm:px-6 lg:px-8">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-lg font-semibold">Radni nalozi</h1>

        @if ($currentLocation)
            <div class="text-sm">
                Poslovnica:
                <span class="rounded bg-blue-500 px-2 py-0.5 text-xs font-semibold text-white">
                    {{ $currentLocation->code }}
                </span>
                <span class="pl-2 text-gray-700">{{ $currentLocation->name }} ({{ $currentLocation->city }})</span>
            </div>
        @else
            <div class="text-sm text-yellow-700">
                Poslovnica nije izabrana.
                @if ($isAdminOwner)
                    <span class="ml-2">Izaberi lokaciju na Dashboardu.</span>
                @endif
            </div>
        @endif
    </div>

    {{-- Quick actions --}}
    <div class="flex flex-wrap items-center gap-2">
        @php
            // owner/admin: treba izabrana lokacija; ostali uvijek mogu (imaju user->location_id)
            $canOpenIntake = !$isAdminOwner || (bool) $currentLocation;
        @endphp

        <a href="{{ $canOpenIntake ? route('workorders-create') : '#' }}"
            class="{{ $canOpenIntake ? '' : 'opacity-50 cursor-not-allowed pointer-events-none' }} rounded border px-3 py-1.5 text-sm hover:bg-gray-50"
            @unless ($canOpenIntake) aria-disabled="true" title="Prvo izaberi poslovnicu na Dashboardu" @endunless>
            + Prijem bicikla
        </a>

        @if ($isServiser)
            <div class="flex items-center gap-2 ml-auto">
                <label class="text-xs text-gray-600">Prikaži:</label>
                <select wire:model.live="scope" class="px-2 py-1 text-sm border rounded">
                    <option value="poslovnica">Svi u poslovnici</option>
                    <option value="moji">Moji nalozi</option>
                </select>
            </div>
        @endif
    </div>

    {{-- PRIJEMI NA ČEKANJU (samo menadžer + admin/vlasnik) --}}
    @if ($isAdminOwner || $isManager)
        <div class="overflow-hidden bg-white border rounded-xl">
            <div class="px-4 py-3 text-sm font-semibold border-b bg-gray-50">Prijemi (bez radnog naloga)</div>
            @if (!$currentLocation)
                <div class="p-6 text-sm text-gray-600">Izaberi poslovnicu da vidiš prijeme.</div>
            @else
                <table class="min-w-full text-sm">
                    <thead class="text-left bg-gray-50">
                        <tr>
                            <th class="px-4 py-2">Datum</th>
                            <th class="px-4 py-2">Mušterija</th>
                            <th class="px-4 py-2">Telefon</th>
                            <th class="px-4 py-2">Bicikl</th>
                            <th class="w-40 px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($intakes as $it)
                            <tr class="border-t">
                                <td class="px-4 py-2 whitespace-nowrap">{{ $it->created_at->format('d.m.Y H:i') }}</td>
                                <td class="px-4 py-2">{{ $it->customer->name }}</td>
                                <td class="px-4 py-2">{{ $it->customer->phone }}</td>
                                <td class="px-4 py-2">
                                    {{ $it->bike->brand }}@if ($it->bike->model)
                                        — {{ $it->bike->model }}
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    @hasanyrole('master-admin|vlasnik|menadzer')
                                        {{-- 🔧 CHANGED: umjesto direktne konverzije, otvaramo modal za izbor servisera --}}
                                        <button type="button" wire:click.prevent="openAssignModal({{ $it->id }})"
                                            wire:loading.attr="disabled"
                                            class="rounded border px-3 py-1.5 text-xs hover:bg-gray-50">
                                            Dodijeli servisera & Kreiraj nalog
                                        </button>
                                    @endhasanyrole
                                </td>
                            </tr>

                            @hasanyrole('master-admin|vlasnik|menadzer')
    <button type="button"
            wire:click="startEstimate({{ $it->id }})"
            class="ml-2 bg-blue-500 text-white rounded border px-3 py-1.5 text-xs">
        Napravi predračun
    </button>
@endhasanyrole


                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                    Nema prijema na čekanju.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                @if (session('error'))
  <div class="px-4 py-2 text-sm text-red-600">{{ session('error') }}</div>
@endif

                <div class="px-4 py-3">
                    {{ method_exists($intakes, 'links') ? $intakes->withQueryString()->links() : '' }}
                </div>
            @endif
        </div>
    @endif


    {{-- RADNI NALOZI (svi vide; Uredi/dodjela/ukloni dodjelu) --}}
    <div class="overflow-hidden bg-white border rounded-xl">
        <div class="px-4 py-3 text-sm font-semibold border-b bg-gray-50">Radni nalozi</div>

            <table class="min-w-full text-sm">
            <thead class="text-left bg-gray-50">
                <tr>
                    <th class="px-4 py-2">Broj</th>
                    <th class="px-4 py-2">Mušterija</th>
                    <th class="px-4 py-2">Bicikl</th>
                    <th class="px-4 py-2">Serviser</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="w-40 px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($workOrders as $wo)
                    <tr class="border-t">
                        <td class="px-4 py-2 whitespace-nowrap">
                            {{ $wo->number }}
                            @if (is_null($wo->assigned_user_id))
                                <span
                                    class="ml-2 rounded bg-yellow-100 px-2 py-0.5 align-middle text-[10px] font-semibold text-yellow-800">Bez
                                    servisera</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            {{ $wo->customer?->name }}
                            <div class="text-xs text-gray-500">{{ $wo->customer?->phone }}</div>
                        </td>
                        <td class="px-4 py-2">
                            {{ $wo->bike?->brand }}@if ($wo->bike?->model)
                                — {{ $wo->bike->model }}
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $wo->assignedUser?->name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $wo->status?->label() ?? '—' }}</td>
                        <td class="px-4 py-2 text-right">
                            @hasanyrole('master-admin|vlasnik|menadzer')
                                @if (is_null($wo->assigned_user_id))
                                    {{-- Ako nema servisera: jedan jasan CTA --}}
                                    <a href="{{ route('workorders-edit', ['workorder' => $wo->id]) }}"
                                        class="rounded bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                        Dodijeli servisera
                                    </a>
                                @else
                                    {{-- Ako ima servisera: standardne akcije --}}
                                    <a href="{{ route('workorders-edit', ['workorder' => $wo->id]) }}"
                                        class="rounded border px-3 py-1.5 text-xs hover:bg-gray-50">
                                        Uredi
                                    </a>
                                @endif
                            @endhasanyrole
                        </td>

                    </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">Nema naloga za prikaz.</td>
                        </tr>
                    @endforelse
            </tbody>
            </table>

            <div class="px-4 py-3">
                {{ $workOrders->withQueryString()->links() }}
            </div>
    </div>

        {{-- ✨ ADDED: Modal za dodjelu servisera & kreiranje naloga iz prijema --}}
        @if ($showAssignModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                <div class="w-full max-w-md p-6 bg-white shadow-xl rounded-xl">
                    <div class="mb-4 text-sm font-semibold">Dodijeli servisera i kreiraj nalog</div>

                    @if (empty($technicians))
                        <div class="p-3 mb-4 text-sm text-yellow-800 rounded bg-yellow-50">
                            Nema dostupnih servisera za ovu poslovnicu.
                        </div>
                    @else
                        <label class="block text-xs text-gray-600">Serviser</label>
                        <select wire:model="technicianId" class="w-full px-3 py-2 mt-1 border rounded">
                            <option value="">— Odaberi servisera —</option>
                            @foreach ($technicians as $tech)
                                <option value="{{ $tech['id'] }}">{{ $tech['name'] }}</option>
                            @endforeach
                        </select>
                        @error('technician')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    @endif

                    <div class="flex items-center justify-end gap-2 mt-6">
                        <button type="button" wire:click="$set('showAssignModal', false)"
                            class="rounded border px-3 py-1.5 text-xs hover:bg-gray-50">
                            Odustani
                        </button>
                        <button type="button" wire:click="convertIntake({{ $intakeIdForAssign }})"
                            wire:loading.attr="disabled"
                            class="rounded bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                            @if (empty($technicians)) disabled @endif>
                            Potvrdi
                        </button>
                    </div>
                </div>
            </div>
        @endif

</div>


