<div class="px-2 py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

        <div class="flex flex-wrap items-center gap-3 mb-4">
            <h1 class="text-xl font-bold">
                {{ $user->name }}
                @if ($roleLabel)
                    <span
                        class="ml-2 inline-flex items-center rounded-md bg-red-600 px-3 py-0.5 text-sm font-medium capitalize text-white">
                        {{ $roleLabel }}
                    </span>
                @endif
            </h1>


        </div>

        <div class="p-4 overflow-hidden bg-white shadow sm:rounded-lg md:p-10">
            @role('master-admin|vlasnik')
                <p class="mb-3 w-fit rounded bg-yellow-400 px-3 py-0.5 text-sm text-gray-800">Ovo vide samo Master Admin i
                    Vlasnik</p>
            @endrole
            @role('menadzer')
                <p class="mb-3 w-fit rounded bg-orange-500 px-3 py-0.5 text-sm text-white">Ovo vidi samo Manager</p>
            @endrole
            @role('serviser')
                <p class="mb-3 w-fit rounded bg-green-600 px-3 py-0.5 text-sm text-white">Ovo vidi samo Serviser</p>
            @endrole

            <div class="mt-5">
                @if ($currentLocation)
                    <div class="text-sm">
                        Izabrana poslovnica:
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


            {{-- Button za prijem bicikla --}}
            <div class="flex flex-wrap gap-2 py-4">

                @php
                    // Dugme je aktivno:
                    // - za menadžera/servisera (uvijek imaju user->location_id)
                    // - za owner/admin samo ako je u sessionu postavljena lokacija
                    $canOpenIntake = !$isAdminOwner || ($isAdminOwner && $selectedLocId);
                @endphp

                <a href="{{ $canOpenIntake ? route('workorders-create') : '#' }}"
                    class="{{ $canOpenIntake ? '' : 'opacity-50 cursor-not-allowed pointer-events-none' }} rounded-md border px-3 py-1.5 text-sm hover:bg-gray-800 hover:text-white"
                    @unless ($canOpenIntake) aria-disabled="true" title="Prvo izaberi poslovnicu" @endunless>
                    + Prijem bicikla
                </a>

            </div>

            {{-- @if ($isAdminOwner)
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
            @endif --}}
        </div>


        <!-- 1) JEDAN x-data iznad tabova + filtera -->
        <div x-data="{
            active: '{{ $selectedLocId ? $selectedLocId : 'all' }}',
            period: '{{ request('period', 'today') }}',
            from: '{{ request('from') }}',
            to: '{{ request('to') }}'
        }">

            @role('master-admin|vlasnik')
                <!-- Hidden GET form (tabs submituju ovdje) -->
                <form x-ref="locForm" method="GET" action="{{ url()->current() }}">
                    <input type="hidden" name="location">
                    <input type="hidden" name="period">
                    <input type="hidden" name="from">
                    <input type="hidden" name="to">
                    @foreach (request()->except(['location', 'period', 'from', 'to']) as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                </form>

                <!-- Tabs -->
                <div class="w-full">
                    <div role="tablist" class="grid grid-cols-4 gap-2 mt-10 md:grid-cols-7">
                        <!-- Sve poslovnice -->
                        <button type="button" role="tab" :aria-selected="active === 'all'"
                            @click="
                              active = 'all';
                              $refs.locForm.location.value = '';
                              $refs.locForm.period.value   = period;
                              $refs.locForm.from.value     = from;
                              $refs.locForm.to.value       = to;
                              $refs.locForm.submit();
                            "
                            class="w-full px-3 py-2 text-sm font-medium transition rounded-md focus:outline-none focus:ring-2 focus:ring-blue-300"
                            :class="active === 'all'
                                ?
                                'bg-blue-600 text-white' :
                                'bg-white text-gray-800 hover:bg-gray-800 hover:text-white'">
                            Sve poslovnice
                        </button>

                        <!-- Dinamički tabs iz baze -->
                        @foreach ($activeLocations as $loc)
                            <button type="button" role="tab" :aria-selected="active === '{{ $loc->id }}'"
                                @click="
                                  active = '{{ $loc->id }}';
                                  $refs.locForm.location.value = '{{ $loc->id }}';
                                  $refs.locForm.period.value   = period;
                                  $refs.locForm.from.value     = from;
                                  $refs.locForm.to.value       = to;
                                  $refs.locForm.submit();
                                "
                                class="inline-flex justify-center w-full px-3 py-2 text-sm font-medium transition rounded-md focus:outline-none focus:ring-2 focus:ring-blue-300"
                                :class="active === '{{ $loc->id }}'
                                    ?
                                    'bg-blue-600 text-white' :
                                    'bg-white text-gray-800 hover:bg-gray-800 hover:text-white'">
                                {{ $loc->city }} <span class="hidden ml-2 sm:flex">({{ $loc->code }})</span>
                            </button>
                        @endforeach
                    </div>

                    <!-- Filter perioda (KORISTI ISTI x-data) -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 p-4 mt-6 bg-white border rounded-lg shadow-sm">
                        <div>
                            <h2 class="text-base font-semibold text-gray-800">Pregled po periodu</h2>
                            <p class="text-sm text-gray-500">Odaberi vremenski raspon</p>
                        </div>

                        <form x-ref="periodForm" method="GET" action="{{ url()->current() }}"
                            class="flex flex-wrap items-center gap-3">
                            @if (request()->filled('location'))
                                <input type="hidden" name="location" value="{{ request('location') }}">
                            @endif

                            <select name="period" x-model="period"
                                @change="if (period !== 'custom') { $refs.periodForm.submit() }"
                                class="text-sm border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500">
                                <option value="today">Danas</option>
                                <option value="7days">Posljednjih 7 dana</option>
                                <option value="month">Ovaj mjesec</option>
                                <option value="custom">Prilagođeni raspon</option>
                            </select>

                            <div x-show="period === 'custom'" x-cloak class="flex items-center gap-2">
                                <input type="date" name="from" x-model="from"
                                    class="text-sm border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500">
                                <span class="text-sm text-gray-500">do</span>
                                <input type="date" name="to" x-model="to"
                                    class="text-sm border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500">

                                <button type="submit"
                                    class="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-blue-700">
                                    Primijeni
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Content -->
                    <div class="mt-6">
                        <div x-show="active === 'all'" x-cloak>

                            <!-- Sažetak (vizual samo, bez logike) -->
                            <div class="mt-6 bg-white border rounded-lg shadow-sm">
                                <div class="flex items-center justify-between px-4 py-3">
                                    <h3 class="text-base font-semibold text-gray-900">Sažetak</h3>
                                    <!-- (opciono mjesto za ukupno, ikonu, itd.) -->
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th
                                                    class="px-4 py-3 text-xs font-semibold tracking-wider text-left text-gray-600 uppercase">
                                                    Status</th>
                                                <th
                                                    class="px-4 py-3 text-xs font-semibold tracking-wider text-right text-gray-600 uppercase">
                                                    Broj</th>
                                                <th
                                                    class="px-4 py-3 text-xs font-semibold tracking-wider text-right text-gray-600 uppercase">
                                                    Akcija</th>
                                            </tr>
                                        </thead>

                                        <tbody class="bg-white divide-y divide-gray-100">

                                            <!-- 1) UKUPNO ZAPRIMLJENO (najupečatljivije) -->
                                            <tr class="border-l-4 border-slate-700 bg-slate-50 hover:bg-slate-100">
                                                <td class="px-4 py-4">
                                                    <div class="flex items-center gap-2">
                                                        <span class="h-2.5 w-2.5 rounded-full bg-slate-700"></span>
                                                        <span class="text-sm font-semibold text-slate-900">Ukupno
                                                            zaprimljeno</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-right">
                                                    <span class="text-2xl font-extrabold text-slate-900">{{ $summary['totalReceived'] }}</span>
                                                </td>
                                                <td class="px-4 py-4 text-right">
                                                    <a href="#"
                                                        class="text-sm font-medium text-blue-700 hover:underline">Otvori
                                                        listu</a>
                                                </td>
                                            </tr>

                                            <!-- 2) Čeka dodjelu servisera (narandžasta tačka) -->
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-2">
                                                        <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                                                        <span class="text-sm font-medium text-gray-900">Čeka dodjelu
                                                            servisera</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm font-semibold text-right text-gray-900">{{ $summary['waitingAssignment'] }}</td>
                                                <td class="px-4 py-3 text-right">
                                                    <a href="#"
                                                        class="text-sm font-medium text-blue-700 hover:underline">Otvori
                                                        listu</a>
                                                </td>
                                            </tr>

                                            <!-- 3) Dodijeljen serviser (bez radnog naloga) – plava tačka -->
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-2">
                                                        <span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                                                        <span class="text-sm font-medium text-gray-900">Dodijeljen serviser
                                                            (bez radnog naloga)</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm font-semibold text-right text-gray-900">{{ $summary['assignedNoWo'] }}</td>
                                                <td class="px-4 py-3 text-right">
                                                    <a href="#"
                                                        class="text-sm font-medium text-blue-700 hover:underline">Otvori
                                                        listu</a>
                                                </td>
                                            </tr>

                                            <!-- 4) Otvoreni radni nalozi -->
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-2">
                                                        <span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span>
                                                        <span class="text-sm font-medium text-gray-900">Otvoreni radni
                                                            nalozi</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm font-semibold text-right text-gray-900">{{ $summary['woOpen'] }}</td>
                                                <td class="px-4 py-3 text-right">
                                                    <a href="#"
                                                        class="text-sm font-medium text-blue-700 hover:underline">Otvori
                                                        listu</a>
                                                </td>
                                            </tr>

                                            <!-- 5) Završeni -->
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-2">
                                                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-600"></span>
                                                        <span class="text-sm font-medium text-gray-900">Završeni</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm font-semibold text-right text-gray-900">{{ $summary['completed'] }}</td>
                                                <td class="px-4 py-3 text-right">
                                                    <a href="#"
                                                        class="text-sm font-medium text-blue-700 hover:underline">Otvori
                                                        listu</a>
                                                </td>
                                            </tr>

                                            <!-- 6) Otkazani (opciono) -->
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-2">
                                                        <span class="h-2.5 w-2.5 rounded-full bg-rose-600"></span>
                                                        <span class="text-sm font-medium text-gray-900">Otkazani</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm font-semibold text-right text-gray-900">{{ $summary['cancelled'] }}</td>
                                                <td class="px-4 py-3 text-right">
                                                    <a href="#"
                                                        class="text-sm font-medium text-blue-700 hover:underline">Otvori
                                                        listu</a>
                                                </td>
                                            </tr>

                                        </tbody>
                                    </table>
                                </div>
                            </div>


                        </div>


                        @foreach ($activeLocations as $loc)
                            <div x-show="active === '{{ $loc->id }}'" x-cloak>
                                <div class="p-4 bg-white border rounded-lg shadow-sm">
                                    <div class="mt-6 bg-white border rounded-lg shadow-sm">
                                        <div class="flex items-center justify-between px-4 py-3">
                                            <div>
                                                <h3 class="text-base font-semibold text-gray-900">Statusi —
                                                    {{ $loc->code }} / {{ $loc->name }}</h3>
                                                <p class="text-sm text-gray-500">Pregled za odabrani period</p>
                                            </div>
                                        </div>

                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th
                                                            class="px-4 py-3 text-xs font-semibold tracking-wider text-left text-gray-600 uppercase">
                                                            Status</th>
                                                        <th
                                                            class="px-4 py-3 text-xs font-semibold tracking-wider text-right text-gray-600 uppercase">
                                                            Broj</th>
                                                        <th
                                                            class="px-4 py-3 text-xs font-semibold tracking-wider text-right text-gray-600 uppercase">
                                                            Akcije</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-100">
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-4 py-3"><span
                                                                class="inline-flex items-center gap-2"><span
                                                                    class="w-2 h-2 rounded-full bg-amber-500"></span><span
                                                                    class="text-sm font-medium text-gray-900">Bez
                                                                    dodijeljenog servisera</span></span></td>
                                                        <td
                                                            class="px-4 py-3 text-sm font-semibold text-right text-gray-900">
                                                            —</td>
                                                        <td class="px-4 py-3 text-right"><a href="#"
                                                                class="text-sm font-medium text-blue-600 hover:underline">Otvori
                                                                listu</a></td>
                                                    </tr>
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-4 py-3"><span
                                                                class="inline-flex items-center gap-2"><span
                                                                    class="w-2 h-2 bg-indigo-500 rounded-full"></span><span
                                                                    class="text-sm font-medium text-gray-900">Dodijeljen
                                                                    serviser (bez naloga)</span></span></td>
                                                        <td
                                                            class="px-4 py-3 text-sm font-semibold text-right text-gray-900">
                                                            —</td>
                                                        <td class="px-4 py-3 text-right"><a href="#"
                                                                class="text-sm font-medium text-blue-600 hover:underline">Otvori
                                                                listu</a></td>
                                                    </tr>
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-4 py-3"><span
                                                                class="inline-flex items-center gap-2"><span
                                                                    class="w-2 h-2 rounded-full bg-sky-500"></span><span
                                                                    class="text-sm font-medium text-gray-900">Radni nalozi
                                                                    u toku</span></span></td>
                                                        <td
                                                            class="px-4 py-3 text-sm font-semibold text-right text-gray-900">
                                                            —</td>
                                                        <td class="px-4 py-3 text-right"><a href="#"
                                                                class="text-sm font-medium text-blue-600 hover:underline">Otvori
                                                                listu</a></td>
                                                    </tr>
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-4 py-3"><span
                                                                class="inline-flex items-center gap-2"><span
                                                                    class="w-2 h-2 rounded-full bg-emerald-600"></span><span
                                                                    class="text-sm font-medium text-gray-900">Završeno</span></span>
                                                        </td>
                                                        <td
                                                            class="px-4 py-3 text-sm font-semibold text-right text-gray-900">
                                                            —</td>
                                                        <td class="px-4 py-3 text-right"><a href="#"
                                                                class="text-sm font-medium text-blue-600 hover:underline">Otvori
                                                                listu</a></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endrole

        </div>

    </div>
