<?php

namespace Tests\Unit;

use App\Models\Payment;
use Tests\TestCase;

/**
 * En la lista de Pagos va en amarillo el pago de un mes de hace dos o más:
 * registrado en noviembre, septiembre o antes sí; octubre no (sin tocar la base).
 */
class PaymentMonthsBackTest extends TestCase
{
    private function payment(string $registered, ?string $paid): Payment
    {
        return new Payment(['date_register' => $registered, 'date_payment' => $paid]);
    }

    public function test_paying_two_or_more_months_back_is_highlighted(): void
    {
        $this->assertTrue($this->payment('2026-11-13', '2026-09-30')->paysOldMonth()); // septiembre
        $this->assertTrue($this->payment('2026-11-01', '2026-09-01')->paysOldMonth());
        $this->assertTrue($this->payment('2026-11-13', '2026-07-31')->paysOldMonth()); // julio y antes

        $this->assertSame(2, $this->payment('2026-11-13', '2026-09-30')->monthsBack());
        $this->assertSame(4, $this->payment('2026-11-13', '2026-07-31')->monthsBack());
    }

    public function test_the_previous_and_current_month_are_not_highlighted(): void
    {
        $this->assertFalse($this->payment('2026-11-13', '2026-10-31')->paysOldMonth()); // octubre
        $this->assertFalse($this->payment('2026-11-01', '2026-10-01')->paysOldMonth());
        $this->assertFalse($this->payment('2026-11-13', '2026-11-12')->paysOldMonth()); // retraso del mes
        $this->assertFalse($this->payment('2026-11-13', '2026-11-13')->paysOldMonth()); // pago del día
    }

    public function test_months_are_counted_across_the_year_change(): void
    {
        $this->assertSame(1, $this->payment('2027-01-05', '2026-12-31')->monthsBack());
        $this->assertFalse($this->payment('2027-01-05', '2026-12-31')->paysOldMonth()); // diciembre en enero: mes anterior
        $this->assertTrue($this->payment('2027-01-05', '2026-11-30')->paysOldMonth()); // noviembre en enero
        $this->assertTrue($this->payment('2027-02-10', '2026-12-31')->paysOldMonth());
    }

    public function test_a_payment_without_dates_is_not_highlighted(): void
    {
        $this->assertSame(0, $this->payment('2026-11-13', null)->monthsBack());
        $this->assertFalse($this->payment('2026-11-13', null)->paysOldMonth());
    }
}
