<div class="px-2 mx-auto mt-8 space-y-8 max-w-7xl sm:px-6 lg:px-8">
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
                @php $isAdminOwner = $user?->hasAnyRole(['master-admin','vlasnik']) ?? false; @endphp
                @if ($isAdminOwner)
                    <span class="ml-2">Izaberi lokaciju na Dashboardu.</span>
                @endif
            </div>
        @endif
    </div>

    @if (session('ok'))
        <div class="px-3 py-2 text-green-800 rounded-lg bg-green-50">
            {{ session('ok') }}
        </div>
    @endif

    {{-- Quick actions --}}
    <div class="flex flex-wrap items-center gap-2">
        @php
            $isAdminOwner = $user?->hasAnyRole(['master-admin','vlasnik']) ?? false;
            $isServiser   = $user?->hasRole('serviser') ?? false;
            // owner/admin: treba izabrana lokacija; ostali imaju user->location_id
            $canOpenIntake = !$isAdminOwner || (bool) $currentLocation;
        @endphp

        <a href="{{ $canOpenIntake ? route('workorders-create') : '#' }}"
           class="{{ $canOpenIntake ? '' : 'opacity-50 cursor-not-allowed pointer-events-none' }} rounded border px-3 py-1.5 text-sm hover:bg-gray-50"
           @unless ($canOpenIntake) aria-disabled="true" title="Prvo izaberi poslovnicu na Dashboardu" @endunless>
            + Kreiraj nalog
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

    {{-- RADNI NALOZI --}}
    <div class="overflow-hidden bg-white border rounded-xl">
        <div class="px-4 py-3 text-sm font-semibold border-b bg-gray-50">Radni nalozi</div>

        <table class="min-w-full text-sm">
            <thead class="text-left bg-gray-50">
                <tr>
                    <th class="px-4 py-2">Broj</th>
                    <th class="px-4 py-2">Mušterija</th>
                    <th class="px-4 py-2">Serviser</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="w-40 px-4 py-2">Akcija</th>
                </tr>
            </thead>
            <tbody>
                @forelse($workOrders as $wo)
                    <tr class="border-t">
                        <td class="px-4 py-2 whitespace-nowrap">
                            {{ $wo->number }}
                        </td>
                        <td class="px-4 py-2">
                            {{ $wo->customer?->name }}
                            <div class="text-xs text-gray-500">{{ $wo->customer?->phone }}</div>
                        </td>

                        {{-- Serviser: ime ili žuti badge --}}
                        <td class="px-4 py-2">
                            @if ($wo->assignedUser?->name)
                                {{ $wo->assignedUser->name }}
                            @else
                                <span class="rounded bg-yellow-100 px-2 py-0.5 align-middle text-xs font-semibold text-yellow-800">
                                    Bez servisera
                                </span>
                            @endif
                        </td>

                        <td class="px-4 py-2">
                            {{ method_exists($wo->status, 'label') ? $wo->status->label() : ($wo->status ?? '—') }}
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('workorders-edit', ['workorder' => $wo->id]) }}"
                               class="rounded {{ is_null($wo->assigned_user_id) ? 'bg-blue-600 text-white hover:bg-blue-700' : 'border hover:bg-gray-50' }} px-3 py-1.5 text-xs font-semibold">
                                {{ is_null($wo->assigned_user_id) ? 'Dodijeli servisera' : 'Uredi' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">Nema naloga za prikaz.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-4 py-3">
            {{ $workOrders->withQueryString()->links() }}
        </div>
    </div>
</div>
