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
           class="rounded border px-3 py-1.5 text-sm hover:bg-gray-50 {{ $canOpenIntake ? '' : 'opacity-50 cursor-not-allowed pointer-events-none' }}"
           @unless($canOpenIntake) aria-disabled="true" title="Prvo izaberi poslovnicu na Dashboardu" @endunless>
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
                                    {{ $it->bike->brand }}@if ($it->bike->model) — {{ $it->bike->model }} @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    @hasanyrole('master-admin|vlasnik|menadzer')
                                        <button type="button"
                                                wire:click.prevent="convertIntake({{ $it->id }})"
                                                wire:loading.attr="disabled"
                                                class="rounded border px-3 py-1.5 text-xs hover:bg-gray-50">
                                            Kreiraj nalog
                                        </button>
                                    @endhasanyrole
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                    Nema prijema na čekanju.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3">
                    {{ method_exists($intakes, 'links') ? $intakes->withQueryString()->links() : '' }}
                </div>
            @endif
        </div>
    @endif

    {{-- Nalozi bez dodijeljenog servisera --}}
    <div class="border-t">
        <div class="px-4 py-3 text-sm font-semibold bg-gray-50">Nalozi bez dodijeljenog servisera</div>

        <table class="min-w-full text-sm">
            <thead class="text-left bg-gray-50">
                <tr>
                    <th class="px-4 py-2">Datum</th>
                    <th class="px-4 py-2">Mušterija</th>
                    <th class="px-4 py-2">Bicikl</th>
                    <th class="w-40 px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($pendingWo as $wo)
                    <tr class="border-t" wire:key="pending-wo-{{ $wo->id }}">
                        <td class="px-4 py-2 whitespace-nowrap">{{ $wo->created_at->format('d.m.Y H:i') }}</td>
                        <td class="px-4 py-2">
                            {{ $wo->customer?->name }}
                            <div class="text-xs text-gray-500">{{ $wo->customer?->phone }}</div>
                        </td>
                        <td class="px-4 py-2">
                            {{ $wo->bike?->brand }}@if($wo->bike?->model) — {{ $wo->bike->model }} @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('workorders-edit', ['workorder' => $wo->id]) }}"
                               class="rounded border px-3 py-1.5 text-xs hover:bg-gray-50">
                                Dodijeli servisera
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">Nema naloga bez dodjele.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-4 py-3">
            {{ method_exists($pendingWo, 'links') ? $pendingWo->withQueryString()->links() : '' }}
        </div>
    </div>

    {{-- RADNI NALOZI (svi vide; Uredi dugme za menadžer/admin/owner) --}}
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
                        <td class="px-4 py-2 whitespace-nowrap">{{ $wo->number }}</td>
                        <td class="px-4 py-2">
                            {{ $wo->customer?->name }}
                            <div class="text-xs text-gray-500">{{ $wo->customer?->phone }}</div>
                        </td>
                        <td class="px-4 py-2">
                            {{ $wo->bike?->brand }}@if ($wo->bike?->model) — {{ $wo->bike->model }} @endif
                        </td>
                        <td class="px-4 py-2">{{ $wo->assignedUser?->name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $wo->status?->label() ?? '—' }}</td>
                        <td class="px-4 py-2 text-right">
                            @hasanyrole('master-admin|vlasnik|menadzer')
                                <a href="{{ route('workorders-edit', ['workorder' => $wo->id]) }}"
                                   class="rounded border px-3 py-1.5 text-xs hover:bg-gray-50">
                                    Uredi
                                </a>
                                @if ($wo->assigned_user_id)
                                    <button type="button"
                                            wire:click="unassignWo({{ $wo->id }})"
                                            class="ml-2 rounded border px-3 py-1.5 text-xs hover:bg-gray-50">
                                        Ukloni dodjelu
                                    </button>
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
</div>
