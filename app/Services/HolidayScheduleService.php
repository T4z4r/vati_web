<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\Loan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class HolidayScheduleService
{
    public function forward(CarbonInterface $date, array $holidays): CarbonImmutable
    {
        $date = CarbonImmutable::parse($date->toDateString());
        while (isset($holidays[$date->toDateString()])) {
            $date = $date->addWeek();
        }

        return $date;
    }

    public function correct(Loan $loan, bool $includePaid = false): int
    {
        return DB::transaction(function () use ($loan, $includePaid) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $holidays = Holiday::pluck('name', 'date')->all();
            $previous = null;
            $changed = 0;
            foreach ($loan->installments()->orderBy('installment_number')->lockForUpdate()->get() as $installment) {
                $date = CarbonImmutable::parse($installment->due_date);
                if ($includePaid || (float) $installment->total_paid <= 0 && ! in_array($installment->status, ['paid', 'waived'])) {
                    while ($previous && $date->lte($previous)) {
                        $date = $date->addWeek();
                    }
                    $date = $this->forward($date, $holidays);
                    if (! $date->isSameDay($installment->due_date)) {
                        $installment->update(['due_date' => $date]);
                        $changed++;
                    }
                }
                $previous = $date;
            }
            if ($changed) {
                $loan->update([
                    'first_payment_date' => $loan->installments()->orderBy('installment_number')->value('due_date'),
                    'maturity_date' => $loan->installments()->orderByDesc('installment_number')->value('due_date'),
                ]);
            }

            return $changed;
        });
    }

    public function importTanzania(int $year): void
    {
        // Fixed dates: Bank of Tanzania public holiday calendar.
        $dates = [
            '01-01' => "New Year's Day", '01-12' => 'Zanzibar Revolution Day',
            '04-07' => 'Karume Day', '04-26' => 'Union Day', '05-01' => "Workers' Day",
            '07-07' => 'Saba Saba', '08-08' => 'Nane Nane', '10-14' => 'Nyerere Day',
            '12-09' => 'Independence Day', '12-25' => 'Christmas Day', '12-26' => 'Boxing Day',
        ];
        foreach ($dates as $day => $name) {
            Holiday::firstOrCreate(['date' => "$year-$day"], ['name' => $name]);
        }
        $easter = CarbonImmutable::create($year, 3, 21)->addDays(easter_days($year, CAL_EASTER_ALWAYS_GREGORIAN));
        foreach ([-2 => 'Good Friday', 1 => 'Easter Monday'] as $offset => $name) {
            Holiday::firstOrCreate(['date' => $easter->addDays($offset)->toDateString()], ['name' => $name]);
        }
    }
}
