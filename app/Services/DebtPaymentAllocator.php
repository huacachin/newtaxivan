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
 * Un pago DEUDA es de UN solo mes, el que elige quien cobra (cualquier mes
 * pendiente), y no puede pasar lo que debe ese mes: dos meses son dos pagos.
 * Lo pagado queda como un detalle "payment:{id}" de ese mes: editar o
 * eliminar el pago devuelve exactamente lo que descontó.
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
     * El pago de un solo mes: el mes tiene que tener deuda y el monto no puede
     * pasar lo que debe. Devuelve una lista con esa única parte (la reciben
     * record() y coverDate()).
     *
     * @param  list<array{id: int, date: string, label: string, pending: float}>  $months
     * @return list<array{id: int, date: string, label: string, pending: float, amount: float, left: float}>
     *
     * @throws ValidationException
     */
    public static function forMonth(array $months, ?int $debtDayId, float $amount): array
    {
        if (! $months) {
            throw ValidationException::withMessages(['amount' => 'Esta placa no tiene deuda pendiente.']);
        }

        $month = collect($months)->firstWhere('id', $debtDayId);
        if (! $month) {
            throw ValidationException::withMessages(['debt_month_id' => 'Elige el mes que paga.']);
        }

        $use = self::cents($amount);
        $pending = self::cents($month['pending']);
        if ($use <= 0) {
            throw ValidationException::withMessages(['amount' => 'El monto debe ser mayor a 0.']);
        }
        if ($use > $pending) {
            throw ValidationException::withMessages([
                'amount' => 'El monto no puede pasar lo que debe '.$month['label'].' (S/ '.number_format(self::fromCents($pending), 2).'). Los otros meses van en pagos aparte.',
            ]);
        }

        return [$month + ['amount' => self::fromCents($use), 'left' => self::fromCents($pending - $use)]];
    }

    /**
     * Pago validado de un mes, con las filas bloqueadas hasta el fin de la
     * transacción. Debe llamarse dentro de DB::transaction().
     *
     * @return list<array{id: int, date: string, label: string, pending: float, amount: float, left: float}>
     *
     * @throws ValidationException
     */
    public function planMonth(string $plate, ?int $debtDayId, float $amount): array
    {
        return self::forMonth($this->pendingMonths($plate, lock: true), $debtDayId, $amount);
    }

    /** Mes que cubre un pago DEUDA ya registrado (el primero de sus detalles). */
    public static function paidMonthId(int $paymentId): ?int
    {
        $id = DB::table('debt_days_detail')->where('detail', self::marker($paymentId))->orderBy('id')->value('debt_days_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Descuenta lo pagado de su mes y deja el detalle "payment:{id}".
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
     * Fecha de pago de un pago DEUDA: fin del mes que paga (indica de qué
     * deuda es; lo descontado está en su detalle).
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
