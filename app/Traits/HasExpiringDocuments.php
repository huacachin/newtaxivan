<?php

namespace App\Traits;

/**
 * Helper compartido entre Vehicle/Owner/Driver para calcular alertas de
 * documentos próximos a vencer. Cada modelo define sus campos en
 * expiringAlerts() y usa expirationAlert() para producir cada item.
 */
trait HasExpiringDocuments
{
    /**
     * Diferencia firmada en días entre HOY y $date.
     * <0 = vencido, 0 = hoy, >0 = faltan N días.
     */
    protected function dayDeltaForExpiration($date): ?int
    {
        if (!$date) return null;

        $tz = config('app.timezone', 'UTC');
        $d = $date instanceof \Carbon\Carbon
            ? $date->copy()->timezone($tz)->startOfDay()
            : \Carbon\Carbon::parse($date, $tz)->startOfDay();

        $today = \Carbon\Carbon::now($tz)->startOfDay();

        if ($d->equalTo($today))   return 0;
        if ($d->lessThan($today))  return -$d->diffInDays($today);
        return $today->diffInDays($d);
    }

    /**
     * Construye un alert (o null) para un campo de vencimiento dado.
     */
    protected function buildExpirationAlert(
        $date,
        string $kind,
        string $kindLabel,
        int $id,
        string $subject,
        string $abbr,
        string $fieldSlug,
        string $label
    ): ?array {
        if (!$date) return null;

        $days = $this->dayDeltaForExpiration($date);
        if ($days === null) return null;

        $due = $date instanceof \Carbon\Carbon
            ? $date->toDateString()
            : \Carbon\Carbon::parse($date)->toDateString();

        $base = [
            'id'         => $id,
            'kind'       => $kind,         // vehicle | owner | driver
            'kind_label' => $kindLabel,
            'subject'    => $subject,
            'abbr'       => $abbr,
            'field'      => $fieldSlug,
            'label'      => $label,
        ];

        if ($days < 0) {
            return $base + [
                'days'     => abs($days),
                'due_date' => $due,
                'color'    => 'danger',
                'status'   => 'expired',
                'message'  => "{$label} venció hace " . abs($days) . " día(s)",
            ];
        }

        if ($days === 0) {
            return $base + [
                'days'     => 0,
                'due_date' => $due,
                'color'    => 'danger',
                'status'   => 'today',
                'message'  => "{$label} vence hoy",
            ];
        }

        if ($days <= 10) {
            return $base + [
                'days'     => $days,
                'due_date' => $due,
                'color'    => $days <= 5 ? 'danger' : 'warning',
                'status'   => 'upcoming',
                'message'  => "{$label} vence en {$days} día(s)",
            ];
        }

        return null;
    }
}
