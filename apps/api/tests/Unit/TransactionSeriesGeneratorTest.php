<?php

namespace Tests\Unit;

use App\Services\TransactionSeriesGenerator;
use Tests\TestCase;

class TransactionSeriesGeneratorTest extends TestCase
{
    public function test_installments_splits_total_into_equal_shares_one_month_apart_with_remainder_on_last_row(): void
    {
        $rows = (new TransactionSeriesGenerator)->installments('Canapé', -10000, '2026-08-01', 3, true);

        $this->assertCount(3, $rows);
        $this->assertSame([-3333, -3333, -3334], array_column($rows, 'amount_cents'));
        $this->assertSame(-10000, array_sum(array_column($rows, 'amount_cents')));
        $this->assertSame(['2026-08-01', '2026-09-01', '2026-10-01'], array_column($rows, 'date'));
        $this->assertSame('Canapé (1/3)', $rows[0]['label']);
        $this->assertTrue($rows[0]['reconciled']);
        $this->assertFalse($rows[1]['reconciled']);
        $this->assertSame($rows[0]['series_id'], $rows[1]['series_id']);
    }

    public function test_recurring_repeats_the_full_amount_for_each_occurrence_at_the_given_frequency(): void
    {
        $rows = (new TransactionSeriesGenerator)->recurring('Netflix', -1599, '2026-08-01', 3, 'monthly', true);

        $this->assertCount(3, $rows);
        $this->assertSame([-1599, -1599, -1599], array_column($rows, 'amount_cents'));
        $this->assertSame('2026-10-01', $rows[2]['date']);
        $this->assertFalse($rows[1]['reconciled']);
    }
}
