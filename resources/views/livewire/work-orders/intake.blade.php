<div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div class="text-lg font-semibold">Prijem bicikla</div>
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
    @php
        $canSubmit = (bool) $location;
    @endphp
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

        {{-- Bicikl --}}
        <div>
            <div class="mb-2 text-sm font-semibold text-gray-700">Bicikl</div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs text-gray-600">Brend *</label>
                    <input type="text" wire:model.defer="bike_brand" required
                        class="w-full px-3 py-2 mt-1 border rounded">
                    @error('bike_brand')
                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-600">Model</label>
                    <input type="text" wire:model.defer="bike_model" class="w-full px-3 py-2 mt-1 border rounded">
                    @error('bike_model')
                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                    @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs text-gray-600">Opis</label>
                    <textarea wire:model.defer="bike_description" rows="2" class="w-full px-3 py-2 mt-1 border rounded"></textarea>
                    @error('bike_description')
                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Dodjela servisera (opciono) --}}
        @php
            // $editing već šalješ u view; koristimo Livewire prop iz komponente
            $isAssigned = $editing && !empty($this->assigned_user_id);

        @endphp
        @if ($location)
            <div>
                <div class="mb-2 text-sm font-semibold text-gray-700">Dodjela servisera (opciono)</div>
                <select wire:model.defer="assigned_user_id" class="w-full px-3 py-2 border rounded">
                    @if ($isAssigned)
                        {{-- Edit + postoji serviser: jasno naznači da će ukloniti dodjelu --}}
                        <option value="">
                            — Ukloni postojećeg —
                        </option>
                    @else
                        {{-- Create ili već bez servisera --}}
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

        <div class="flex justify-end">
            <button type="{{ $canSubmit ? 'submit' : 'button' }}" @disabled(!$canSubmit)
                class="px-4 py-2 text-white bg-blue-600 rounded-xl hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                {{ $canSubmit ? 'Sačuvaj' : 'Izaberi poslovnicu' }}
            </button>

        </div>
    </form>


    <button type="button"
        wire:click="startEstimate({{ $intake->id }})"
        class="rounded border px-3 py-1.5 text-xs hover:bg-gray-50">
    Napravi predračun
</button>

@if (session('error'))
  <div class="mt-2 text-sm text-red-600">{{ session('error') }}</div>
@endif


</div>
