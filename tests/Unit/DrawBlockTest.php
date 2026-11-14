<?php

namespace Tests\Unit;

use App\Models\DrawBlock;
use App\Models\DrawBlockException;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Bloqueos de Sorteos: todo el mes, rango de fechas, días específicos o hasta
 * desbloquearlos, con días sueltos desbloqueados. Usa la app de Laravel por
 * los casts y las traducciones, sin tocar la base.
 */
class DrawBlockTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  array<int, string>  $unblockedDays
     */
    private function block(array $attributes, array $unblockedDays = []): DrawBlock
    {
        $block = new DrawBlock($attributes);
        $block->setRelation('exceptions', collect(array_map(fn (string $d) => new DrawBlockException(['date' => $d, 'reason' => 'x']), $unblockedDays)));

        return $block;
    }

    public function test_a_month_block_covers_the_whole_month_except_the_unblocked_days(): void
    {
        $month = $this->block(['kind' => DrawBlock::KIND_MONTH, 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30'], ['2026-11-07']);

        $this->assertTrue($month->appliesOn('2026-11-02'));
        $this->assertTrue($month->appliesOn('2026-11-30'));
        $this->assertFalse($month->appliesOn('2026-11-07'));   // sábado desbloqueado
        $this->assertTrue($month->coversDate('2026-11-07'));   // sigue en el periodo
        $this->assertFalse($month->appliesOn('2026-12-01'));
        $this->assertFalse($month->appliesOn('2026-10-31'));
        $this->assertSame('Todo noviembre 2026', $month->periodLabel());
    }

    public function test_a_range_block_covers_from_the_first_to_the_last_day(): void
    {
        $range = $this->block(['kind' => DrawBlock::KIND_RANGE, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-14']);

        $this->assertFalse($range->appliesOn('2026-11-07'));
        $this->assertTrue($range->appliesOn('2026-11-09'));
        $this->assertTrue($range->appliesOn('2026-11-14'));
        $this->assertFalse($range->appliesOn('2026-11-16'));
        $this->assertSame('Del 09/11 al 14/11/2026', $range->periodLabel());
    }

    public function test_a_suspension_also_blocks_the_sunday_in_between(): void
    {
        $suspension = $this->block(['kind' => DrawBlock::KIND_SUSPENSION, 'dates' => ['2026-11-07', '2026-11-09', '2026-11-10'],
            'starts_on' => '2026-11-07', 'ends_on' => '2026-11-10']);

        $this->assertTrue($suspension->appliesOn('2026-11-07'));
        $this->assertTrue($suspension->appliesOn('2026-11-08')); // domingo en medio: tampoco entra a ese sorteo
        $this->assertTrue($suspension->appliesOn('2026-11-10'));
        $this->assertSame('Sáb 07/11, Lun 09/11, Mar 10/11', $suspension->periodLabel());
        $this->assertSame('Suspensión por sanciones', $suspension->kindName());
        $this->assertArrayNotHasKey(DrawBlock::KIND_SUSPENSION, DrawBlock::KIND_LABELS); // no se elige en el formulario
    }

    public function test_a_dates_block_only_covers_those_days(): void
    {
        $dates = $this->block(['kind' => DrawBlock::KIND_DATES, 'dates' => ['2026-11-04', '2026-11-07', '2026-11-12'],
            'starts_on' => '2026-11-04', 'ends_on' => '2026-11-12']);

        $this->assertTrue($dates->appliesOn('2026-11-04'));
        $this->assertTrue($dates->appliesOn('2026-11-07'));
        $this->assertFalse($dates->appliesOn('2026-11-05'));  // en medio, pero no marcado
        $this->assertFalse($dates->appliesOn('2026-11-11'));
        $this->assertSame('Mié 04/11, Sáb 07/11, Jue 12/11', $dates->periodLabel());
    }

    public function test_a_dates_block_covers_a_sunday_only_between_a_blocked_saturday_and_monday(): void
    {
        // BCM016 "Veedor": días sueltos del 11/11 al 01/12 (sáb 14 libre, lun 16 bloqueado; sáb 21 bloqueado, lun 23 libre)
        $veedor = $this->block(['kind' => DrawBlock::KIND_DATES, 'starts_on' => '2026-11-11', 'ends_on' => '2026-12-01',
            'dates' => ['2026-11-11', '2026-11-12', '2026-11-16', '2026-11-17', '2026-11-20', '2026-11-21', '2026-11-25', '2026-11-26', '2026-11-30', '2026-12-01']]);

        $this->assertTrue($veedor->appliesOn('2026-11-16'));
        $this->assertFalse($veedor->appliesOn('2026-11-15')); // sábado libre: el domingo también
        $this->assertFalse($veedor->appliesOn('2026-11-22')); // lunes libre: el domingo también
        $this->assertFalse($veedor->appliesOn('2026-11-29'));

        // Sábado y lunes bloqueados: el domingo de en medio también, salvo que lo desbloqueen ese día
        $aroundSunday = $this->block(['kind' => DrawBlock::KIND_DATES, 'dates' => ['2026-11-14', '2026-11-16'],
            'starts_on' => '2026-11-14', 'ends_on' => '2026-11-16']);
        $this->assertTrue($aroundSunday->appliesOn('2026-11-15'));
        $unblocked = $this->block(['kind' => DrawBlock::KIND_DATES, 'dates' => ['2026-11-14', '2026-11-16'],
            'starts_on' => '2026-11-14', 'ends_on' => '2026-11-16'], ['2026-11-15']);
        $this->assertTrue($unblocked->coversDate('2026-11-15'));
        $this->assertFalse($unblocked->appliesOn('2026-11-15'));

        $saturdayOnly = $this->block(['kind' => DrawBlock::KIND_DATES, 'dates' => ['2026-11-14'],
            'starts_on' => '2026-11-14', 'ends_on' => '2026-11-14']);
        $this->assertFalse($saturdayOnly->appliesOn('2026-11-15')); // el domingo ya no está en el periodo
    }

    public function test_a_range_covers_every_sunday_of_its_period(): void
    {
        $range = $this->block(['kind' => DrawBlock::KIND_RANGE, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-28']);

        $this->assertTrue($range->appliesOn('2026-11-15'));
        $this->assertTrue($range->appliesOn('2026-11-22'));
        $this->assertFalse($range->appliesOn('2026-11-29'));
    }

    public function test_an_indefinite_block_applies_every_day_until_lifted(): void
    {
        Carbon::setTestNow('2027-03-01');
        $indefinite = $this->block(['kind' => DrawBlock::KIND_INDEFINITE, 'starts_on' => '2026-10-31', 'ends_on' => null], ['2026-11-07']);

        $this->assertTrue($indefinite->isIndefinite());
        $this->assertFalse($indefinite->appliesOn('2026-10-30'));
        $this->assertTrue($indefinite->appliesOn('2026-11-02'));
        $this->assertFalse($indefinite->appliesOn('2026-11-07'));
        $this->assertTrue($indefinite->appliesOn('2027-05-15'));
        $this->assertSame('Desde el 31/10/2026, hasta desbloquearla', $indefinite->periodLabel());
        $this->assertSame(DrawBlock::STATUS_ACTIVE, $indefinite->status());
    }

    public function test_the_grid_tooltip_shows_the_escaped_reason_without_the_period_days(): void
    {
        $dates = $this->block(['kind' => DrawBlock::KIND_DATES, 'dates' => ['2026-11-11', '2026-11-16'], 'starts_on' => '2026-11-11', 'ends_on' => '2026-11-16',
            'reason' => 'No pagó <script>alert(1)</script>']);

        $html = $dates->tooltipHtml();
        $this->assertStringContainsString('<b class="dr-tip-reason">No pagó &lt;script&gt;alert(1)&lt;/script&gt;</b>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('11/11', $html); // los días del periodo confunden en la celda de un día
        $this->assertStringContainsString('Bloqueado por —', $html); // sin usuario cargado
    }

    public function test_a_block_is_served_when_its_period_ends_or_lifted_when_unblocked(): void
    {
        $range = $this->block(['kind' => DrawBlock::KIND_RANGE, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-14']);

        Carbon::setTestNow('2026-11-14 20:00');
        $this->assertSame(DrawBlock::STATUS_ACTIVE, $range->status());

        Carbon::setTestNow('2026-11-15 07:00');
        $this->assertSame(DrawBlock::STATUS_EXPIRED, $range->status());

        $range->unblocked_at = '2026-11-10 09:00';
        $this->assertSame(DrawBlock::STATUS_LIFTED, $range->status());
    }
}
