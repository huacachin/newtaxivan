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
    public string $action = 'updated';
    public string $userId = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    public ?array $detail = null;

    protected $paginationTheme = 'bootstrap';

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
        $this->action = 'updated';
        $this->dateFrom = $today;
        $this->dateTo = $today;
        $this->resetPage();
    }

    public function showDetail(int $id): void
    {
        $log = ActivityLog::find($id);
        if (!$log) return;

        $this->detail = $log->toArray();
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
