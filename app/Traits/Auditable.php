<?php

namespace App\Traits;

use App\Models\ActivityLog;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->logActivity('created', null, $model->getAuditAttributes());
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();
            $excluded = $model->getAuditExclude();

            // Filtrar campos excluidos y timestamps
            $changed = array_diff_key($dirty, array_flip($excluded), array_flip(['created_at', 'updated_at']));
            if (empty($changed)) return;

            $oldValues = [];
            $newValues = [];
            foreach ($changed as $field => $newVal) {
                $oldValues[$field] = $model->getOriginal($field);
                $newValues[$field] = $newVal;
            }

            $model->logActivity('updated', $model->getOriginalAuditAttributes(), $model->getAuditAttributes(), array_keys($changed));
        });

        static::deleted(function ($model) {
            $model->logActivity('deleted', $model->getAuditAttributes(), null);
        });
    }

    protected function logActivity(string $action, ?array $oldData, ?array $newData, ?array $changedFields = null): void
    {
        try {
            $user = auth()->user();
            $request = request();

            ActivityLog::create([
                'user_id'        => $user?->id,
                'user_name'      => $user?->name,
                'user_role'      => $user?->roles?->first()?->name,
                'action'         => $action,
                'module'         => $this->getAuditModule(),
                'record_id'      => $this->getKey(),
                'old_data'       => $oldData,
                'new_data'       => $newData,
                'changed_fields' => $changedFields,
                'ip_address'     => $request->ip(),
                'user_agent'     => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // No interrumpir la operación original
            logger()->error('Auditable log failed: ' . $e->getMessage());
        }
    }

    protected function getAuditAttributes(): array
    {
        $excluded = $this->getAuditExclude();
        return array_diff_key($this->attributesToArray(), array_flip($excluded));
    }

    protected function getOriginalAuditAttributes(): array
    {
        $excluded = $this->getAuditExclude();
        $original = $this->getOriginal();
        return array_diff_key($original, array_flip($excluded));
    }

    protected function getAuditModule(): string
    {
        return $this->auditModule ?? class_basename($this);
    }

    protected function getAuditExclude(): array
    {
        return $this->auditExclude ?? [];
    }
}
