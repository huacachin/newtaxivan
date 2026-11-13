<?php

namespace Tests\Unit;

use App\Services\DebtPaymentAllocator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Un pago DEUDA es de un solo mes, el que se elige (cualquiera pendiente), y no
 * puede pasar lo que debe ese mes: dos meses son dos pagos (sin tocar la base).
 */
class DebtPaymentAllocatorTest extends TestCase
{
    /** CER399 al 13/11/2026: jun 35, jul 135, ago 40, sep 120, oct 315 */
    private function cer399(): array
    {
        return [
            ['id' => 1, 'date' => '2026-06-01', 'label' => 'Jun 2026', 'pending' => 35.0],
            ['id' => 2, 'date' => '2026-07-01', 'label' => 'Jul 2026', 'pending' => 135.0],
            ['id' => 3, 'date' => '2026-08-01', 'label' => 'Ago 2026', 'pending' => 40.0],
            ['id' => 4, 'date' => '2026-09-01', 'label' => 'Sep 2026', 'pending' => 120.0],
            ['id' => 5, 'date' => '2026-10-01', 'label' => 'Oct 2026', 'pending' => 315.0],
        ];
    }

    /** @return array<string, list<string>> */
    private function errorsOf(callable $pay): array
    {
        try {
            $pay();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('Se esperaba un error de validación.');
    }

    public function test_a_payment_only_pays_the_chosen_month(): void
    {
        $parts = DebtPaymentAllocator::forMonth($this->cer399(), 3, 40);

        $this->assertSame([[3, 40.0, 0.0]], array_map(fn ($p) => [$p['id'], $p['amount'], $p['left']], $parts));
        $this->assertSame('2026-08-31', DebtPaymentAllocator::coverDate($parts));
    }

    public function test_any_pending_month_can_be_paid_and_partially(): void
    {
        $october = DebtPaymentAllocator::forMonth($this->cer399(), 5, 100); // octubre antes que junio
        $this->assertSame([[5, 100.0, 215.0]], array_map(fn ($p) => [$p['id'], $p['amount'], $p['left']], $october));
        $this->assertSame('2026-10-31', DebtPaymentAllocator::coverDate($october));

        $july = DebtPaymentAllocator::forMonth($this->cer399(), 2, 135);
        $this->assertSame(0.0, $july[0]['left']);
    }

    public function test_the_amount_cannot_pass_what_the_month_owes(): void
    {
        $errors = $this->errorsOf(fn () => DebtPaymentAllocator::forMonth($this->cer399(), 3, 60)); // ago debe 40

        $this->assertSame(['amount'], array_keys($errors));
        $this->assertStringContainsString('Ago 2026 (S/ 40.00)', $errors['amount'][0]);
        $this->assertStringContainsString('pagos aparte', $errors['amount'][0]);
    }

    public function test_a_month_must_be_chosen_among_the_pending_ones(): void
    {
        $this->assertSame(['debt_month_id'], array_keys($this->errorsOf(fn () => DebtPaymentAllocator::forMonth($this->cer399(), null, 40))));
        $this->assertSame(['debt_month_id'], array_keys($this->errorsOf(fn () => DebtPaymentAllocator::forMonth($this->cer399(), 99, 40))));
        $this->assertSame(['Esta placa no tiene deuda pendiente.'], $this->errorsOf(fn () => DebtPaymentAllocator::forMonth([], 1, 40))['amount']);
        $this->assertSame(['El monto debe ser mayor a 0.'], $this->errorsOf(fn () => DebtPaymentAllocator::forMonth($this->cer399(), 1, 0))['amount']);
    }

    public function test_cents_are_compared_exactly(): void
    {
        $months = [['id' => 1, 'date' => '2026-09-01', 'label' => 'Sep 2026', 'pending' => 0.3]];

        $this->assertSame(0.3, DebtPaymentAllocator::forMonth($months, 1, 0.1 + 0.2)[0]['amount']);
        $this->assertSame(['amount'], array_keys($this->errorsOf(fn () => DebtPaymentAllocator::forMonth($months, 1, 0.31))));
        $this->assertSame(645.0, DebtPaymentAllocator::total($this->cer399()));
    }

    public function test_payment_markers_and_labels(): void
    {
        $this->assertSame('payment:123', DebtPaymentAllocator::marker(123));
        $this->assertSame(123, DebtPaymentAllocator::paymentIdFrom('payment:123'));
        $this->assertNull(DebtPaymentAllocator::paymentIdFrom('Cancelado 09/10/2026'));
        $this->assertNull(DebtPaymentAllocator::paymentIdFrom('payment:12 extra'));
        $this->assertNull(DebtPaymentAllocator::paymentIdFrom(null));
        $this->assertSame('Sep 2026', DebtPaymentAllocator::monthLabel('2026-09-01'));
    }
}
