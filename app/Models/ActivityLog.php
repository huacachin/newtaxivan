<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_name', 'user_role', 'action', 'module',
        'record_id', 'old_data', 'new_data', 'changed_fields',
        'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_data'       => 'array',
            'new_data'       => 'array',
            'changed_fields' => 'array',
            'created_at'     => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByDateRange($query, ?string $from, ?string $to)
    {
        return $query
            ->when($from, fn($q) => $q->where('created_at', '>=', $from . ' 00:00:00'))
            ->when($to, fn($q) => $q->where('created_at', '<=', $to . ' 23:59:59'));
    }

    /**
     * Modulos cuyos registros NO se eliminan fisicamente — solo cambian
     * status active → inactive. El audit log queda como 'updated' pero
     * funcionalmente es una eliminacion.
     */
    public const SOFT_DELETE_MODULES = ['Conductores', 'Propietarios'];

    /**
     * Detecta si este log es realmente una eliminacion soft (cambio de
     * status active → inactive en modulos que no se borran fisicamente).
     */
    public function isSoftDelete(): bool
    {
        if (!in_array($this->module, self::SOFT_DELETE_MODULES, true)) return false;
        if ($this->action !== 'updated') return false;
        if (!is_array($this->changed_fields) || !in_array('status', $this->changed_fields, true)) return false;

        return ($this->old_data['status'] ?? null) === 'active'
            && ($this->new_data['status'] ?? null) === 'inactive';
    }

    /**
     * Scope con las condiciones SQL del soft-delete. Pensado para combinar
     * con OR/whereNot dentro de un where() closure.
     */
    public function scopeSoftDeleteCriteria($query)
    {
        return $query
            ->where('action', 'updated')
            ->whereIn('module', self::SOFT_DELETE_MODULES)
            ->whereJsonContains('changed_fields', 'status')
            ->where('old_data->status', 'active')
            ->where('new_data->status', 'inactive');
    }
}
