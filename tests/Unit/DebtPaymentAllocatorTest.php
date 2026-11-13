<?php

namespace Tests\Unit;

use App\Services\DebtPaymentAllocator;
use PHPUnit\Framework\TestCase;

/**
 * Un pago DEUDA se descuenta del mes pendiente más antiguo al más reciente,
 * sin importar en qué mes se pague (sin tocar la base).
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

    public function test_a_payment_goes_to_the_oldest_month_first(): void
    {
        $parts = DebtPaymentAllocator::allocate($this->cer399(), 200);

        $this->assertSame([[1, 35.0, 0.0], [2, 135.0, 0.0], [3, 30.0, 10.0]],
            array_map(fn ($p) => [$p['id'], $p['amount'], $p['left']], $parts));
        $this->assertSame('2026-06-30', DebtPaymentAllocator::coverDate($parts));
    }

    public function test_paying_in_december_finishes_october_before_november(): void
    {
        $months = [
            ['id' => 10, 'date' => '2026-10-01', 'label' => 'Oct 2026', 'pending' => 50.0],
            ['id' => 11, 'date' => '2026-11-01', 'label' => 'Nov 2026', 'pending' => 90.0],
        ];

        $parts = DebtPaymentAllocator::allocate($months, 80);

        $this->assertSame([[10, 50.0, 0.0], [11, 30.0, 60.0]], array_map(fn ($p) => [$p['id'], $p['amount'], $p['left']], $parts));
        $this->assertSame('2026-10-31', DebtPaymentAllocator::coverDate($parts));
    }

    public function test_the_whole_debt_and_cents_add_up_exactly(): void
    {
        $months = [
            ['id' => 1, 'date' => '2026-09-01', 'label' => 'Sep 2026', 'pending' => 0.1],
            ['id' => 2, 'date' => '2026-10-01', 'label' => 'Oct 2026', 'pending' => 0.2],
        ];

        $this->assertSame(0.3, DebtPaymentAllocator::total($months));
        $this->assertSame([0.1, 0.2], array_column(DebtPaymentAllocator::allocate($months, 0.3), 'amount'));
        $this->assertSame(645.0, DebtPaymentAllocator::total($this->cer399()));
        $this->assertCount(5, DebtPaymentAllocator::allocate($this->cer399(), 645));
    }

    public function test_months_without_debt_receive_nothing(): void
    {
        $this->assertSame([], DebtPaymentAllocator::allocate([], 50));
        $this->assertSame([], DebtPaymentAllocator::allocate($this->cer399(), 0));
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
