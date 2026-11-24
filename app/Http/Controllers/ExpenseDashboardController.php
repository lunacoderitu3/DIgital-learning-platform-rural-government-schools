<?php

namespace App\Http\Controllers;

use App\Repositories\Expense\ExpenseInterface;
use App\Repositories\SessionYear\SessionYearInterface;
use App\Services\CachingService;
use App\Services\ExpenseService;
use App\Services\ResponseService;
use Throwable;

class ExpenseDashboardController extends Controller
{
    private ExpenseInterface $expense;
    private SessionYearInterface $sessionYear;
    private CachingService $cache;
    private ExpenseService $expenseService;

    public function __construct(ExpenseInterface $expense, SessionYearInterface $sessionYear, CachingService $cache, ExpenseService $expenseService)
    {
        $this->expense = $expense;
        $this->sessionYear = $sessionYear;
        $this->cache = $cache;
        $this->expenseService = $expenseService;
    }

    public function index()
    {
        ResponseService::noFeatureThenRedirect('Finance Management');
        ResponseService::noAnyPermissionThenRedirect(['expense-create', 'expense-list']);

        // Rely on the global session year from the header (no per-page selector).
        $sessionYear = $this->cache->getSessionYear();

        $summary = $this->expenseService->summary($sessionYear);
        $monthly = $this->expenseService->monthlyBreakdown($sessionYear);
        $distribution = $this->expenseService->distribution($sessionYear);

        $recentExpenses = $this->expense->builder()->with('category')
            ->where('session_year_id', $sessionYear->id)
            ->orderBy('date', 'DESC')
            ->take(5)
            ->get();

        $currencySymbol = $this->cache->getSchoolSettings('currency_symbol') ?? '';

        return view('expense.dashboard', compact('summary', 'monthly', 'distribution', 'recentExpenses', 'currencySymbol'));
    }

    public function monthly($session_year_id = null)
    {
        ResponseService::noFeatureThenSendJson('Finance Management');
        ResponseService::noAnyPermissionThenSendJson(['expense-create', 'expense-list']);

        try {
            if ($session_year_id == 'undefined' || empty($session_year_id)) {
                $session_year_id = $this->cache->getSessionYear()->id;
            }
            $sessionYear = $this->sessionYear->builder()->findOrFail($session_year_id);
            $data = $this->expenseService->monthlyBreakdown($sessionYear);
            ResponseService::successResponse('Data Fetched Successfully', $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "Expense Dashboard Controller -> Monthly Method");
            ResponseService::errorResponse();
        }
    }

    public function distribution($session_year_id = null)
    {
        ResponseService::noFeatureThenSendJson('Finance Management');
        ResponseService::noAnyPermissionThenSendJson(['expense-create', 'expense-list']);

        try {
            if ($session_year_id == 'undefined' || empty($session_year_id)) {
                $session_year_id = $this->cache->getSessionYear()->id;
            }
            $sessionYear = $this->sessionYear->builder()->findOrFail($session_year_id);
            $data = $this->expenseService->distribution($sessionYear);
            ResponseService::successResponse('Data Fetched Successfully', $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "Expense Dashboard Controller -> Distribution Method");
            ResponseService::errorResponse();
        }
    }
}
