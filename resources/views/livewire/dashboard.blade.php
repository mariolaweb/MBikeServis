<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

        <div class="flex flex-wrap items-center gap-3 mb-4">
            <h1 class="text-xl font-bold">
                {{ $user->name }}
                @if ($roleLabel)
                    <span class="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-sm font-medium">
                        {{ $roleLabel }}
                    </span>
                @endif
            </h1>

            @if ($currentLocation)
                <div class="text-sm">
                    Poslovnica:
                    <span class="rounded bg-blue-500 px-2 py-0.5 text-xs font-semibold text-white">
                        {{ $currentLocation->code }}
                    </span>
                    <span class="pl-2 text-gray-700">
                        {{ $currentLocation->name }} ({{ $currentLocation->city }})
                    </span>
                </div>
            @else
                <div class="text-sm text-yellow-700">Poslovnica nije izabrana.</div>
            @endif
        </div>

        <div class="p-4 overflow-hidden bg-white shadow-xl sm:rounded-lg md:p-10">
            @role('master-admin|vlasnik')
                <p class="mb-3 text-sm text-gray-600">Ovo vidi samo Master Admin i Vlasnik</p>
            @endrole
            @role('menadzer')
                <p class="mb-3 text-sm text-gray-600">Ovo vidi samo Manager</p>
            @endrole
            @role('serviser')
                <p class="mb-3 text-sm text-gray-600">Ovo vidi samo Serviser</p>
            @endrole


            {{-- Button za prijem bicikla --}}
            <div class="flex flex-wrap gap-2 py-4">

                @php
                    // Dugme je aktivno:
                    // - za menadžera/servisera (uvijek imaju user->location_id)
                    // - za owner/admin samo ako je u sessionu postavljena lokacija
                    $canOpenIntake = !$isAdminOwner || ($isAdminOwner && $selectedLocId);
                @endphp

                <a href="{{ $canOpenIntake ? route('workorders-create') : '#' }}"
                    class="{{ $canOpenIntake ? '' : 'opacity-50 cursor-not-allowed pointer-events-none' }} rounded border px-3 py-1.5 text-sm hover:bg-gray-50"
                    @unless ($canOpenIntake) aria-disabled="true" title="Prvo izaberi poslovnicu" @endunless>
                    + Prijem bicikla
                </a>

            </div>

            @if ($isAdminOwner)
                <form method="GET" action="{{ url()->current() }}" class="inline-flex items-center gap-2">
                    <select name="location" class="px-2 py-1 border rounded">
                        <option value="" disabled {{ $currentLocation ? '' : 'selected' }}>
                            — Izaberi poslovnicu —
                        </option>
                        @foreach ($activeLocations as $loc)
                            <option value="{{ $loc->id }}"
                                {{ (string) $selectedLocId === (string) $loc->id ? 'selected' : '' }}>
                                {{ $loc->code }} — {{ $loc->name }} ({{ $loc->city }})
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded border px-3 py-1.5 text-sm hover:bg-gray-50">
                        Primijeni
                    </button>
                </form>
            @endif
        </div>

    </div>
</div>
