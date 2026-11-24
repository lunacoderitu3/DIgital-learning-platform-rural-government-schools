<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FeeDiscountItem;
use App\Models\SessionYear;
use App\Models\SubscriptionBill;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ExpenseService
{
    /**
     * Total Subscription Paid Amount for the school during a session year.
     */
    public function subscriptionAmount(SessionYear $sessionYear): float
    {
        $startDate = $sessionYear->getRawOriginal('start_date');
        $endDate = $sessionYear->getRawOriginal('end_date');

        $query = SubscriptionBill::whereHas('transaction', function ($q) {
            $q->where('payment_status', 'succeed');
        })
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (Auth::user() && Auth::user()->school_id) {
            $query->where('school_id', Auth::user()->school_id);
        }

        return round((float) $query->sum('amount'), 2);
    }

    /**
     * Total Fees Discount Amount for a session year.
     */
    public function feesDiscountAmount($sessionYearId): float
    {
        $query = FeeDiscountItem::whereHas('fee_discount', function ($query) use ($sessionYearId) {
            if (Auth::user() && Auth::user()->school_id) {
                $query->where('school_id', Auth::user()->school_id);
            }
            $query->whereHas('fee', function ($q) use ($sessionYearId) {
                $q->where('session_year_id', $sessionYearId);
            });
        });

        return round((float) $query->sum('discount_amount'), 2);
    }

    /**
     * Manual expense recorded in the expense table for a session year.
     */
    public function manualExpense($sessionYearId): float
    {
        return round((float) Expense::owner()
            ->where('session_year_id', $sessionYearId)
            ->sum('amount'), 2);
    }

    /**
     * The four dashboard card totals.
     */
    public function summary(SessionYear $sessionYear): array
    {
        $subscription = $this->subscriptionAmount($sessionYear);
        $discount = $this->feesDiscountAmount($sessionYear->id);
        $manual = $this->manualExpense($sessionYear->id);

        return [
            'subscription_amount' => $subscription,
            'fees_discount_amount' => $discount,
            'manual_expense_amount' => $manual,
            'total_expense' => round($subscription + $discount + $manual, 2),
        ];
    }

    /**
     * Month-by-month breakdown for the bar chart.
     */
    public function monthlyBreakdown(SessionYear $sessionYear): array
    {
        $sessionYearId = $sessionYear->id;
        $months = sessionYearWiseMonthYear($sessionYear);
        $startDate = $sessionYear->getRawOriginal('start_date');
        $endDate = $sessionYear->getRawOriginal('end_date');

        $subscriptionQuery = SubscriptionBill::whereHas('transaction', function ($q) {
            $q->where('payment_status', 'succeed');
        })->whereBetween('created_at', [$startDate, $endDate]);

        if (Auth::user() && Auth::user()->school_id) {
            $subscriptionQuery->where('school_id', Auth::user()->school_id);
        }
        $subscription = $this->groupedByMonth($subscriptionQuery, 'created_at');

        $discountQuery = FeeDiscountItem::whereHas('fee_discount', function ($query) use ($sessionYearId) {
            if (Auth::user() && Auth::user()->school_id) {
                $query->where('school_id', Auth::user()->school_id);
            }
            $query->whereHas('fee', function ($q) use ($sessionYearId) {
                $q->where('session_year_id', $sessionYearId);
            });
        });
        $discount = $this->groupedByMonth($discountQuery, 'created_at', 'discount_amount');

        $manual = $this->groupedByMonth(Expense::owner()->where('session_year_id', $sessionYearId), 'date');

        $labels = $subscriptionData = $discountData = $manualData = [];
        foreach ($months as $key => $label) {
            $labels[] = Carbon::createFromFormat('n_Y', $key)->format('M');
            $subscriptionData[] = round($subscription[$key] ?? 0, 2);
            $discountData[] = round($discount[$key] ?? 0, 2);
            $manualData[] = round($manual[$key] ?? 0, 2);
        }

        return [
            'labels' => $labels,
            'subscription' => $subscriptionData,
            'discount' => $discountData,
            'manual' => $manualData,
        ];
    }

    /**
     * Series + labels for the expense distribution donut chart.
     */
    public function distribution(SessionYear $sessionYear): array
    {
        $summary = $this->summary($sessionYear);

        return [
            'labels' => [trans('total_subscription_paid_amount'), trans('total_fees_relief_amount'), trans('manual_expense_amount')],
            'series' => [$summary['subscription_amount'], $summary['fees_discount_amount'], $summary['manual_expense_amount']],
            'total' => $summary['total_expense'],
        ];
    }

    /**
     * Sum a query's amount grouped by "month_year".
     */
    private function groupedByMonth(Builder $query, string $dateColumn, string $sumColumn = 'amount'): array
    {
        return $query->select(
            DB::raw("CONCAT(MONTH($dateColumn), '_', YEAR($dateColumn)) as month_year"),
            DB::raw("SUM($sumColumn) as total")
        )
            ->groupBy(DB::raw("CONCAT(MONTH($dateColumn), '_', YEAR($dateColumn))"))
            ->pluck('total', 'month_year')
            ->toArray();
    }
}
