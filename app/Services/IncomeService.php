<?php

namespace App\Services;

use App\Models\CompulsoryFee;
use App\Models\Income;
use App\Models\OptionalFee;
use App\Models\SessionYear;
use App\Models\TransportationPayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Central place for all Income dashboard calculations and the
 * auto-generated reference number. Keeping the math here avoids
 * duplicating fee/transport/manual logic across controllers.
 */
class IncomeService
{
    /**
     * Academic fee collection for a session year (compulsory + optional fees).
     */
    // public function feeIncome($sessionYearId): float
    // {
    //     // Neither compulsory_fees nor optional_fees store session_year_id, so both
    //     // are filtered through fees_paid -> fees.session_year_id.
    //     $compulsory = (float) CompulsoryFee::owner()
    //         ->whereHas('fees_paid.fees', function ($query) use ($sessionYearId) {
    //             $query->where('session_year_id', $sessionYearId);
    //         })
    //         ->sum('amount');

    //     $optional = (float) OptionalFee::owner()
    //         ->whereHas('fees_paid.fees', function ($query) use ($sessionYearId) {
    //             $query->where('session_year_id', $sessionYearId);
    //         })
    //         ->sum('amount');

    //     return round($compulsory + $optional, 2);
    // }
    public function feeIncome($sessionYearId): float
    {
        $compulsory = (float) CompulsoryFee::owner()
            ->whereNull('deleted_at')
            ->where('status', CompulsoryFee::CHEQUE_SUCCESS) // Only Success status
            ->whereHas('fees_paid.fees', function ($query) use ($sessionYearId) {
                $query->where('session_year_id', $sessionYearId);
            })
            ->sum('amount');

        $optional = (float) OptionalFee::owner()
            ->whereNull('deleted_at')
            ->where('status', OptionalFee::CHEQUE_SUCCESS) // Only Success status
            ->whereHas('fees_paid.fees', function ($query) use ($sessionYearId) {
                $query->where('session_year_id', $sessionYearId);
            })
            ->sum('amount');

        return round($compulsory + $optional, 2);
    }
    /**
     * Transportation fee collection for a session year (paid payments only).
     */
    public function transportIncome($sessionYearId): float
    {
        return round((float) TransportationPayment::where('session_year_id', $sessionYearId)
            ->where('status', 'paid')
            ->sum('amount'), 2);
    }

    /**
     * Manual income recorded in the income table for a session year.
     */
    public function manualIncome($sessionYearId): float
    {
        return round((float) Income::owner()
            ->where('session_year_id', $sessionYearId)
            ->sum('amount'), 2);
    }

    /**
     * The four dashboard card totals.
     */
    public function summary($sessionYearId): array
    {
        $fee = $this->feeIncome($sessionYearId);
        $transport = $this->transportIncome($sessionYearId);
        $manual = $this->manualIncome($sessionYearId);

        return [
            'fee_income' => $fee,
            'transportation_income' => $transport,
            'manual_income' => $manual,
            'total_income' => round($fee + $transport + $manual, 2),
        ];
    }

    /**
     * Month-by-month breakdown (fee / transport / manual) for the bar chart,
     * aligned to the session year's month axis. One grouped query per source.
     */
    // public function monthlyBreakdown(SessionYear $sessionYear): array
    // {
    //     $sessionYearId = $sessionYear->id;
    //     $months = sessionYearWiseMonthYear($sessionYear); // ['4_2026' => 'April 2026', ...]

    //     $fee = $this->groupedByMonth(
    //         CompulsoryFee::owner()->whereHas('fees_paid.fees', function ($query) use ($sessionYearId) {
    //             $query->where('session_year_id', $sessionYearId);
    //         }),
    //         'date'
    //     );
    //     $optional = $this->groupedByMonth(
    //         OptionalFee::owner()->whereHas('fees_paid.fees', function ($query) use ($sessionYearId) {
    //             $query->where('session_year_id', $sessionYearId);
    //         }),
    //         'date'
    //     );
    //     $transport = $this->groupedByMonth(
    //         TransportationPayment::where('session_year_id', $sessionYearId)->where('status', 'paid'),
    //         'paid_at'
    //     );
    //     $manual = $this->groupedByMonth(Income::owner()->where('session_year_id', $sessionYearId), 'date');

    //     $labels = $feeData = $transportData = $manualData = [];
    //     foreach ($months as $key => $label) {
    //         $labels[] = Carbon::createFromFormat('n_Y', $key)->format('M'); // short month for axis
    //         $feeData[] = round(($fee[$key] ?? 0) + ($optional[$key] ?? 0), 2);
    //         $transportData[] = round($transport[$key] ?? 0, 2);
    //         $manualData[] = round($manual[$key] ?? 0, 2);
    //     }

    //     return [
    //         'labels' => $labels,
    //         'fee' => $feeData,
    //         'transport' => $transportData,
    //         'manual' => $manualData,
    //     ];
    // }
    /**
     * Month-by-month breakdown (fee / transport / manual) for the bar chart,
     * aligned to the session year's month axis.
     * ONLY successful transactions (status = 'Success').
     */
    public function monthlyBreakdown(SessionYear $sessionYear): array
    {
        $sessionYearId = $sessionYear->id;
        $months = sessionYearWiseMonthYear($sessionYear);

        // Sum compulsory fees ONLY for status = 'Success'
        $fee = $this->groupedByMonth(
            CompulsoryFee::owner()
                ->whereNull('deleted_at')
                ->where('status', CompulsoryFee::CHEQUE_SUCCESS)
                ->whereHas('fees_paid.fees', function ($query) use ($sessionYearId) {
                    $query->where('session_year_id', $sessionYearId);
                }),
            'date'
        );

        // Sum optional fees ONLY for status = 'Success'
        $optional = $this->groupedByMonth(
            OptionalFee::owner()
                ->whereNull('deleted_at')
                ->where('status', OptionalFee::CHEQUE_SUCCESS)
                ->whereHas('fees_paid.fees', function ($query) use ($sessionYearId) {
                    $query->where('session_year_id', $sessionYearId);
                }),
            'date'
        );

        $transport = $this->groupedByMonth(
            TransportationPayment::where('session_year_id', $sessionYearId)->where('status', 'paid'),
            'paid_at'
        );

        $manual = $this->groupedByMonth(Income::owner()->where('session_year_id', $sessionYearId), 'date');

        $labels = $feeData = $transportData = $manualData = [];
        foreach ($months as $key => $label) {
            $labels[] = Carbon::createFromFormat('n_Y', $key)->format('M');
            $feeData[] = round(($fee[$key] ?? 0) + ($optional[$key] ?? 0), 2);
            $transportData[] = round($transport[$key] ?? 0, 2);
            $manualData[] = round($manual[$key] ?? 0, 2);
        }

        return [
            'labels' => $labels,
            'fee' => $feeData,
            'transport' => $transportData,
            'manual' => $manualData,
        ];
    }
    /**
     * Series + labels for the income distribution donut chart.
     */
    public function distribution($sessionYearId): array
    {
        $summary = $this->summary($sessionYearId);

        return [
            'labels' => [trans('fee_income'), trans('transportation_income'), trans('manual_income')],
            'series' => [$summary['fee_income'], $summary['transportation_income'], $summary['manual_income']],
            'total' => $summary['total_income'],
        ];
    }

    /**
     * Generate the next reference number for a session year: INC-YYYY-0001.
     * Year comes from the selected session year's start date; the sequence
     * resets per session year.
     */
    public function nextReferenceNo($sessionYearId): string
    {
        $sessionYear = SessionYear::find($sessionYearId);
        $year = $sessionYear
            ? Carbon::parse($sessionYear->getRawOriginal('start_date'))->format('Y')
            : date('Y');

        $prefix = 'INC-' . $year . '-';
        $start = strlen($prefix) + 1;

        $last = Income::owner()
            ->where('session_year_id', $sessionYearId)
            ->where('reference_no', 'LIKE', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING(reference_no, ' . $start . ') AS UNSIGNED) DESC')
            ->value('reference_no');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Sum a query's amount grouped by "month_year" (e.g. "4_2026").
     */
    private function groupedByMonth(Builder $query, string $dateColumn): array
    {
        return $query->select(
            DB::raw("CONCAT(MONTH($dateColumn), '_', YEAR($dateColumn)) as month_year"),
            DB::raw('SUM(amount) as total')
        )
            ->groupBy(DB::raw("CONCAT(MONTH($dateColumn), '_', YEAR($dateColumn))"))
            ->pluck('total', 'month_year')
            ->toArray();
    }
}
