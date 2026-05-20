<?php

namespace App\Livewire\AuditLogs;

use App\Models\ActivityLog;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $module = '';
    public string $action = 'deleted';
    public string $userId = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    public ?array $detail = null;

    protected $paginationTheme = 'bootstrap';

    /**
     * Mapeo módulo auditable → rutas posibles.
     *   edit:  ruta a la vista de edición del registro (acepta el id)
     *   index: ruta al listado del modulo (fallback)
     */
    protected const MODULE_ROUTES = [
        'Vehículos'           => ['edit' => 'settings.vehicles.edit',     'index' => 'settings.vehicles.index'],
        'Conductores'         => ['edit' => 'settings.drivers.edit',      'index' => 'settings.drivers.index'],
        'Propietarios'        => ['edit' => 'settings.owners.edit',       'index' => 'settings.owners.index'],
        'Salidas'             => ['edit' => 'departures.edit',            'index' => 'departures.index'],
        'Pagos'               => ['edit' => 'payments.edit',              'index' => 'payments.index'],
        'Ingresos'            => ['edit' => 'cash.incomes.edit',          'index' => 'cash.incomes'],
        'Egresos'             => ['edit' => 'cash.expenses.edit',         'index' => 'cash.expenses'],
        'Sucursales'          => ['edit' => 'settings.headquarters.edit', 'index' => 'settings.headquarters.index'],
        'Usuarios'            => ['edit' => 'settings.users.edit',        'index' => 'settings.users.index'],
        'Conceptos'           => ['edit' => 'settings.concepts.edit',     'index' => 'settings.concepts.index'],
        'Detalle Deuda'       => [                                         'index' => 'debts.monthly'],
        'Deuda por Días'      => [                                         'index' => 'debts.debt-per-days'],
        'Costo por Placa'     => [                                         'index' => 'settings.cost-per-plate.index'],
        'Costo por Placa Día' => [                                         'index' => 'settings.cost-per-plate.index'],
    ];

    /**
     * Mapeo de mismatches columna DB → propiedad Livewire para resaltar
     * correctamente los campos en la vista Edit cuando llegamos desde auditoria.
     * Solo declarar entradas donde el nombre de la columna difiere del
     * nombre de la propiedad publica del componente Livewire. Por defecto se
     * asume 1:1 (no hace falta declararlo).
     */
    protected const FIELD_PROP_MAP = [
        'Vehículos' => ['headquarters' => 'headquarter'],
        'Pagos'     => ['type'         => 'type_form'],
        'Egresos'   => ['kind'         => 'expenseKind'],
    ];

    /**
     * Devuelve la URL destino para un log de auditoria:
     * - Si la acción es 'deleted' o el módulo no tiene ruta edit → ruta index.
     * - En cualquier otro caso, ruta edit con el record_id.
     * - Cuando action='updated' y hay changed_fields, anexa ?highlight=...
     *   traduciendo columna DB → prop Livewire via FIELD_PROP_MAP.
     * - null si el módulo no está mapeado (no muestra el boton).
     */
    public function targetUrlFor(ActivityLog $log): ?string
    {
        $routes = self::MODULE_ROUTES[$log->module] ?? null;
        if (!$routes) return null;

        $isDeleted = $log->action === 'deleted';

        if (!$isDeleted && isset($routes['edit']) && $log->record_id) {
            try {
                $url = route($routes['edit'], $log->record_id);

                if ($log->action === 'updated' && !empty($log->changed_fields)) {
                    $map = self::FIELD_PROP_MAP[$log->module] ?? [];
                    $props = array_map(
                        fn($col) => $map[$col] ?? $col,
                        $log->changed_fields
                    );
                    $url .= '?' . http_build_query(['highlight' => implode(',', $props)]);
                }

                return $url;
            } catch (\Throwable $e) {
                // fall through al index
            }
        }

        if (isset($routes['index'])) {
            try {
                return route($routes['index']);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    public function mount(): void
    {
        $today = now()->toDateString();
        $this->dateFrom = $today;
        $this->dateTo = $today;
    }

    public function search(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $today = now()->toDateString();
        $this->reset(['module', 'userId']);
        $this->action = 'deleted';
        $this->dateFrom = $today;
        $this->dateTo = $today;
        $this->resetPage();
    }

    public function showDetail(int $id): void
    {
        $log = ActivityLog::find($id);
        if (!$log) return;

        $detail = $log->toArray();

        // Resolver vehicle_id → placa
        $vehicleId = $detail['new_data']['vehicle_id'] ?? $detail['old_data']['vehicle_id'] ?? null;
        if ($vehicleId) {
            $plate = \Illuminate\Support\Facades\DB::table('vehicles')->where('id', $vehicleId)->value('plate');
            if ($plate) {
                foreach (['old_data', 'new_data'] as $key) {
                    if (isset($detail[$key]['vehicle_id'])) {
                        $detail[$key]['vehicle_id'] = $plate . ' - ' . $detail[$key]['vehicle_id'];
                    }
                }
                if (is_array($detail['changed_fields']) && in_array('vehicle_id', $detail['changed_fields'])) {
                    // mantener vehicle_id en changed_fields sin modificar
                }
            }
        }

        $this->detail = $detail;
        $this->dispatch('open-modal', ['name' => 'modalAuditDetail']);
    }

    public function closeDetail(): void
    {
        $this->detail = null;
        $this->dispatch('modal-close', ['name' => 'modalAuditDetail']);
    }

    public function render()
    {
        $logs = ActivityLog::query()
            ->when($this->module, fn($q) => $q->byModule($this->module))
            ->when($this->action, fn($q) => $q->byAction($this->action))
            ->when($this->userId, fn($q) => $q->byUser((int) $this->userId))
            ->when($this->dateFrom || $this->dateTo, fn($q) => $q->byDateRange($this->dateFrom ?: null, $this->dateTo ?: null))
            ->orderByDesc('created_at')
            ->paginate(20);

        $modules = ActivityLog::query()->select('module')->distinct()->orderBy('module')->pluck('module');
        $users = User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return view('livewire.audit-logs.index', compact('logs', 'modules', 'users'));
    }
}
