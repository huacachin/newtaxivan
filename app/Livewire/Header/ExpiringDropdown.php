<?php

namespace App\Livewire\Header;

use App\Models\Driver;
use App\Models\Owner;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Dropdown de vencimientos del header.
 *
 * Encapsula la lectura (con cache de 60s) de alertas de Vehicle + Owner + Driver
 * y se re-renderiza cuando recibe el evento 'expiration-saved' (dispatched
 * desde las vistas de edición de un solo campo).
 */
class ExpiringDropdown extends Component
{
    public array $alerts = [];
    public int $count = 0;

    public function mount(): void
    {
        $this->loadAlerts();
    }

    #[On('expiration-saved')]
    public function refresh(): void
    {
        Cache::forget('header_expiring_alerts');
        $this->loadAlerts();
    }

    protected function loadAlerts(): void
    {
        $all = Cache::remember('header_expiring_alerts', 60, function () {
            $today = now(config('app.timezone', 'America/Lima'))->toDateString();

            $vehicleAlerts = Vehicle::query()
                ->select('id','plate','soat_date','technical_review','certificate_date','status','termination_date')
                ->where('status', 'active')
                ->where(function ($w) use ($today) {
                    $w->whereNull('termination_date')
                      ->orWhere('termination_date', '>', $today);
                })
                ->get()
                ->flatMap(fn ($v) => $v->expiringAlerts());

            $ownerAlerts = Owner::query()
                ->select('id','name','document_expiration_date','status')
                ->where('status', 'active')
                ->whereNotNull('document_expiration_date')
                ->get()
                ->flatMap(fn ($o) => $o->expiringAlerts());

            $driverAlerts = Driver::query()
                ->select('id','name','document_expiration_date',
                         'license_revalidation_date','cartilla_informativa_expiration_date',
                         'credential_expiration_date','status')
                ->where('status', 'active')
                ->get()
                ->flatMap(fn ($d) => $d->expiringAlerts());

            return $vehicleAlerts
                ->concat($ownerAlerts)
                ->concat($driverAlerts)
                ->sortBy('days')
                ->values()
                ->all();
        });

        $this->alerts = array_slice($all, 0, 10);
        $this->count  = count($all);
    }

    public function render()
    {
        return view('livewire.header.expiring-dropdown');
    }
}
