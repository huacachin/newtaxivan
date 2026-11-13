<?php

namespace App\Services;

use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pagos tipo DEUDA contra la deuda mensual (debt_days).
 *
 * El pago se descuenta del mes pendiente más antiguo al más reciente, sin
 * importar en qué mes se pague. Cada parte queda como un detalle
 * "payment:{id}" del mes que la recibió: editar o eliminar el pago devuelve
 * exactamente lo que descontó.
 */
class DebtPaymentAllocator
{
    /**
     * Solo cuentan las deudas desde este mes. Las de 2017-2023 son saldos del
     * sistema antiguo que nunca se cobraron (en 2024-2025 no quedó ninguna).
     */
    public const PAYABLE_FROM = '2026-01-01';

    private const MONTH_LABELS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    /**
     * Meses con deuda pendiente de la placa, del más antiguo al más reciente.
     *
     * Con $paymentId, lo que ese pago ya descontó vuelve a contar como
     * pendiente (al editarlo se devuelve antes de repartirlo de nuevo).
     *
     * @return list<array{id: int, date: string, label: string, pending: float}>
     */
    public function pendingMonths(string $plate, ?int $paymentId = null, bool $lock = false): array
    {
        if (trim($plate) === '') {
            return [];
        }

        $rows = $this->debtRows($plate)
            ->where('date', '>=', self::PAYABLE_FROM)
            ->orderBy('date')->orderBy('id')
            ->when($lock, fn (Builder $q) => $q->lockForUpdate())
            ->get(['id', 'date', 'total', 'exonerated', 'amortized']);

        $own = $paymentId
            ? DB::table('debt_days_detail')->where('detail', self::marker($paymentId))
                ->selectRaw('debt_days_id, SUM(amortized) as amo')->groupBy('debt_days_id')
                ->pluck('amo', 'debt_days_id')
            : collect();

        $months = [];
        foreach ($rows as $row) {
            $pending = self::cents($row->total) - self::cents($row->exonerated) - self::cents($row->amortized)
                + self::cents($own[$row->id] ?? 0);

            if ($pending > 0) {
                $date = Carbon::parse($row->date)->toDateString();
                $months[] = ['id' => (int) $row->id, 'date' => $date, 'label' => self::monthLabel($date), 'pending' => self::fromCents($pending)];
            }
        }

        return $months;
    }

    /**
     * Reparte el monto del mes más antiguo al más reciente.
     *
     * @param  list<array{id: int, date: string, label: string, pending: float}>  $months
     * @return list<array{id: int, date: string, label: string, pending: float, amount: float, left: float}>
     */
    public static function allocate(array $months, float $amount): array
    {
        $rest = self::cents($amount);
        $parts = [];

        foreach ($months as $month) {
            if ($rest <= 0) {
                break;
            }

            $pending = self::cents($month['pending']);
            $use = min($rest, $pending);
            if ($use <= 0) {
                continue;
            }

            $rest -= $use;
            $parts[] = $month + ['amount' => self::fromCents($use), 'left' => self::fromCents($pending - $use)];
        }

        return $parts;
    }

    /**
     * Reparto validado del pago, con las filas bloqueadas hasta el fin de la
     * transacción. Debe llamarse dentro de DB::transaction().
     *
     * @return list<array{id: int, date: string, label: string, pending: float, amount: float, left: float}>
     *
     * @throws ValidationException
     */
    public function plan(string $plate, float $amount): array
    {
        $months = $this->pendingMonths($plate, lock: true);
        $total = self::total($months);

        if ($total <= 0) {
            throw ValidationException::withMessages(['amount' => 'Esta placa no tiene deuda pendiente.']);
        }
        if (self::cents($amount) > self::cents($total)) {
            throw ValidationException::withMessages(['amount' => 'El monto no puede exceder la deuda pendiente (S/ '.number_format($total, 2).').']);
        }

        return self::allocate($months, $amount);
    }

    /**
     * Descuenta cada parte de su mes y deja el detalle "payment:{id}".
     *
     * @param  list<array{id: int, amount: float}>  $parts
     */
    public function record(int $paymentId, array $parts, string $paidOn): void
    {
        $now = now();

        foreach ($parts as $part) {
            DB::table('debt_days')->where('id', $part['id'])->increment('amortized', $part['amount'], ['updated_at' => $now]);

            DB::table('debt_days_detail')->insert([
                'debt_days_id' => $part['id'],
                'exonerated' => 0,
                'amortized' => $part['amount'],
                'detail' => self::marker($paymentId),
                'user_id' => Auth::id(),
                'date' => $paidOn,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** Devuelve a cada mes lo que el pago descontó y borra sus detalles. */
    public function revert(int $paymentId): void
    {
        $details = DB::table('debt_days_detail')->where('detail', self::marker($paymentId))->lockForUpdate()->get(['id', 'debt_days_id', 'amortized']);

        foreach ($details as $detail) {
            DB::table('debt_days')->where('id', $detail->debt_days_id)->update([
                'amortized' => DB::raw('GREATEST(COALESCE(amortized, 0) - '.number_format((float) $detail->amortized, 2, '.', '').', 0)'),
                'updated_at' => now(),
            ]);
        }

        DB::table('debt_days_detail')->whereIn('id', $details->pluck('id'))->delete();
    }

    /**
     * Si el día ya quedó contado en la deuda de su mes (marcado X/X1), un
     * RETRASO lo cobraría dos veces: ese pago va como DEUDA.
     */
    public function closedDayConflict(string $plate, string $date): ?string
    {
        if (trim($plate) === '' || trim($date) === '') {
            return null;
        }

        $day = Carbon::parse($date);
        $row = $this->debtRows($plate)->whereYear('date', $day->year)->whereMonth('date', $day->month)->first(['d'.$day->day.' as mark', 'date']);

        if (! $row || ! in_array($row->mark, ['X', 'X1'], true)) {
            return null;
        }

        return 'El '.$day->format('d/m').' ya está en la deuda de '.self::monthLabel($row->date).': regístralo como Deuda.';
    }

    /**
     * Fecha de pago de un pago DEUDA: fin del mes más antiguo que cubre
     * (como antes, indica de qué deuda es; el reparto completo está en los detalles).
     *
     * @param  list<array{date: string}>  $parts
     */
    public static function coverDate(array $parts): string
    {
        return Carbon::parse($parts[0]['date'])->endOfMonth()->toDateString();
    }

    /** @param  list<array{pending: float}>  $months */
    public static function total(array $months): float
    {
        return self::fromCents(array_sum(array_map(fn (array $m) => self::cents($m['pending']), $months)));
    }

    public static function marker(int $paymentId): string
    {
        return 'payment:'.$paymentId;
    }

    /** Id del pago de un detalle "payment:{id}" (null si es una exoneración manual). */
    public static function paymentIdFrom(?string $detail): ?int
    {
        return preg_match('/^payment:(\d+)$/', (string) $detail, $m) ? (int) $m[1] : null;
    }

    public static function monthLabel(string $date): string
    {
        $d = Carbon::parse($date);

        return self::MONTH_LABELS[$d->month - 1].' '.$d->year;
    }

    /** Filas de deuda de la placa: por su vehículo o, sin él, por la placa guardada. */
    private function debtRows(string $plate): Builder
    {
        $needle = str_replace('-', '', strtoupper(trim($plate)));
        $vehicleId = Vehicle::whereRaw('REPLACE(UPPER(TRIM(plate)),"-","") = ?', [$needle])->value('id');

        return DB::table('debt_days')->where(function (Builder $q) use ($needle, $vehicleId) {
            $q->whereRaw('REPLACE(UPPER(TRIM(legacy_plate)),"-","") = ?', [$needle]);
            if ($vehicleId) {
                $q->orWhere('vehicle_id', $vehicleId);
            }
        });
    }

    private static function cents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private static function fromCents(int $cents): float
    {
        return $cents / 100.0;
    }
}
