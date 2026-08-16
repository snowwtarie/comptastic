<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

class TransactionSeriesGenerator
{
    /**
     * Splits $totalAmountCents into $count equal monthly installments.
     * Integer cents can't always divide evenly — the rounding remainder is
     * absorbed by the last installment so the series always sums exactly to
     * the requested total (the frontend prototype uses float division and
     * doesn't have this problem; the API must, since it stores integers).
     *
     * @return array<int, array{date: string, label: string, amount_cents: int, reconciled: bool, series_id: string, series_kind: string, series_index: int}>
     */
    public function installments(string $label, int $totalAmountCents, string $startDate, int $count, bool $firstReconciled): array
    {
        $per = intdiv($totalAmountCents, $count);
        $remainder = $totalAmountCents - ($per * $count);
        $seriesId = (string) Str::uuid();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'date' => Carbon::parse($startDate)->addMonthsNoOverflow($i)->toDateString(),
                'label' => sprintf('%s (%d/%d)', $label, $i + 1, $count),
                'amount_cents' => $per + ($i === $count - 1 ? $remainder : 0),
                'reconciled' => $i === 0 && $firstReconciled,
                'series_id' => $seriesId,
                'series_kind' => 'installment',
                'series_index' => $i + 1,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{date: string, label: string, amount_cents: int, reconciled: bool, series_id: string, series_kind: string, series_index: int}>
     */
    public function recurring(string $label, int $amountCents, string $startDate, int $count, string $frequency, bool $firstReconciled): array
    {
        $seriesId = (string) Str::uuid();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $date = match ($frequency) {
                'weekly' => Carbon::parse($startDate)->addWeeks($i),
                'yearly' => Carbon::parse($startDate)->addYearsNoOverflow($i),
                default => Carbon::parse($startDate)->addMonthsNoOverflow($i),
            };

            $rows[] = [
                'date' => $date->toDateString(),
                'label' => $label,
                'amount_cents' => $amountCents,
                'reconciled' => $i === 0 && $firstReconciled,
                'series_id' => $seriesId,
                'series_kind' => 'recurring',
                'series_index' => $i + 1,
            ];
        }

        return $rows;
    }
}
