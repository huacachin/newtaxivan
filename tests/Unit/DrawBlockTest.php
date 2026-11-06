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
