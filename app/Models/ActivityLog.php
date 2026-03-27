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
}
