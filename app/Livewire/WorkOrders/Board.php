<?php

namespace App\Livewire\WorkOrders;

use App\Models\Location;
use App\Models\WorkOrder;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

class Board extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    // URL filteri
    #[Url(as: 'scope')]  public string $scope = 'poslovnica';  // 'poslovnica' | 'moji'
    #[Url(as: 'status')] public ?string $status = null;        // opcionalni filter statusa

    #[Layout('layouts.app')]
    public function render()
    {
        $user         = Auth::user();
        $isAdminOwner = $user?->hasAnyRole(['master-admin', 'vlasnik']) ?? false;
        $isServiser   = $user?->hasRole('serviser') ?? false;

        // Lokacija: owner/admin iz sessiona; ostali iz usera
        $locId = $isAdminOwner ? session('current_location') : ($user?->location_id ?? null);
        if ($locId) {
            $locId = Location::where('id', $locId)->where('is_active', true)->value('id');
        }

        $currentLocation = $locId
            ? Location::select('id','code','name','city')->find($locId)
            : null;

        // --- RADNI NALOZI (WO) ---
        $woQuery = WorkOrder::query()
            ->with([
                'customer:id,name,phone',
                'gear:id,brand,model,customer_id',
                'assignedUser:id,name',
            ]);

        // Filtriraj po lokaciji
        if ($locId) {
            $woQuery->where('location_id', $locId);
        } else {
            // owner/admin bez aktivne lokacije -> prikaži prazno
            if ($isAdminOwner) {
                $woQuery->whereRaw('1=0');
            } else {
                $woQuery->where('location_id', -1);
            }
        }

        // Filter statusa (ako je zadat)
        if ($this->status) {
            $woQuery->where('status', $this->status);
        }

        // Serviser: moji vs poslovnica
        if ($isServiser && $this->scope === 'moji') {
            $woQuery->where('assigned_user_id', $user->id);
        }

        // Prvo nalozi bez servisera, zatim ostali; unutar toga najnoviji prvi
        $woQuery->orderByRaw('assigned_user_id IS NULL DESC')
                ->latest('created_at');

        $workOrders = $woQuery->paginate(10, ['*'], 'wo_page');

        return view('livewire.work-orders.board', compact(
            'user',
            'currentLocation',
            'workOrders',
        ));
    }

    // Reset paginacije na promjenu filtera
    public function updatedScope(): void
    {
        $this->resetPage('wo_page');
    }

    public function updatedStatus(): void
    {
        $this->resetPage('wo_page');
    }
}
