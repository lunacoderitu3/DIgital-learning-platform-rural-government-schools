<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\SessionYear;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Builds the session-wise Financial Summary (Total Income / Total Expense / Net Result).
 *
 * Income is reused from IncomeService (fee + transport + income module) so the
 * numbers always match the Income Dashboard. Expense is the sum of all Expense
 * module records for the session. Every figure is a DB-level SUM (no records are
 * loaded into memory) and always returns 0 instead of null.
 */
class FinancialSummaryService
{
    private IncomeService $incomeService;

    public function __construct(IncomeService $incomeService)
    {
        $this->incomeService = $incomeService;
    }

    /**
     * Total of all Expense module records for a session year.
     * Queried directly (not via the owner scope, which is locked to the current
     * session year) so any session can be aggregated.
     */
    public function expenseTotal($sessionYearId): float
    {
        $schoolId = Auth::user()->school_id;

        return round((float) Expense::where('school_id', $schoolId)
            ->where('session_year_id', $sessionYearId)
            ->sum('amount'), 2);
    }

    /**
     * One summary row for a single session year.
     */
    public function rowForSession(SessionYear $sessionYear): array
    {
        $income = $this->incomeService->summary($sessionYear->id);
        $totalIncome = (float) $income['total_income'];
        $totalExpense = $this->expenseTotal($sessionYear->id);

        return [
            'session_year_id' => $sessionYear->id,
            'session_year' => $sessionYear->name,
            'fee_income' => (float) $income['fee_income'],
            'transport_income' => (float) $income['transportation_income'],
            'income_module_income' => (float) $income['manual_income'],
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_result' => round($totalIncome - $totalExpense, 2),
        ];
    }

    /**
     * Build rows + grand totals for the given session years.
     *
     * @param Collection<int, SessionYear> $sessionYears
     * @return array{rows: array<int, array>, totals: array{income: float, expense: float, net: float}}
     */
    public function build(Collection $sessionYears): array
    {
        $rows = [];
        $income = $expense = $net = 0.0;

        foreach ($sessionYears as $sessionYear) {
            $row = $this->rowForSession($sessionYear);
            $rows[] = $row;
            $income += $row['total_income'];
            $expense += $row['total_expense'];
            $net += $row['net_result'];
        }

        return [
            'rows' => $rows,
            'totals' => [
                'income' => round($income, 2),
                'expense' => round($expense, 2),
                'net' => round($net, 2),
            ],
        ];
    }
}
