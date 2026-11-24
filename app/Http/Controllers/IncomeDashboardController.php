<?php

namespace App\Http\Controllers;

use App\Repositories\Income\IncomeInterface;
use App\Repositories\SessionYear\SessionYearInterface;
use App\Services\CachingService;
use App\Services\IncomeService;
use App\Services\ResponseService;
use Throwable;

class IncomeDashboardController extends Controller
{
    private IncomeInterface $income;
    private SessionYearInterface $sessionYear;
    private CachingService $cache;
    private IncomeService $incomeService;

    public function __construct(IncomeInterface $income, SessionYearInterface $sessionYear, CachingService $cache, IncomeService $incomeService)
    {
        $this->income = $income;
        $this->sessionYear = $sessionYear;
        $this->cache = $cache;
        $this->incomeService = $incomeService;
    }

    public function index()
    {
        ResponseService::noFeatureThenRedirect('Finance Management');
        ResponseService::noAnyPermissionThenRedirect(['income-create', 'income-list']);

        // Rely on the global session year from the header (no per-page selector).
        $sessionYear = $this->cache->getSessionYear();

        $summary = $this->incomeService->summary($sessionYear->id);
        $monthly = $this->incomeService->monthlyBreakdown($sessionYear);
        $distribution = $this->incomeService->distribution($sessionYear->id);

        $recentIncomes = $this->income->builder()->with('category')
            ->where('session_year_id', $sessionYear->id)
            ->orderBy('date', 'DESC')
            ->take(5)
            ->get();

        $currencySymbol = $this->cache->getSchoolSettings('currency_symbol') ?? '';

        return view('income.dashboard', compact('summary', 'monthly', 'distribution', 'recentIncomes', 'currencySymbol'));
    }

    public function monthly($session_year_id = null)
    {
        ResponseService::noFeatureThenSendJson('Finance Management');
        ResponseService::noAnyPermissionThenSendJson(['income-create', 'income-list']);

        try {
            if ($session_year_id == 'undefined' || empty($session_year_id)) {
                $session_year_id = $this->cache->getSessionYear()->id;
            }
            $sessionYear = $this->sessionYear->builder()->findOrFail($session_year_id);
            $data = $this->incomeService->monthlyBreakdown($sessionYear);
            ResponseService::successResponse('Data Fetched Successfully', $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "Income Dashboard Controller -> Monthly Method");
            ResponseService::errorResponse();
        }
    }

    public function distribution($session_year_id = null)
    {
        ResponseService::noFeatureThenSendJson('Finance Management');
        ResponseService::noAnyPermissionThenSendJson(['income-create', 'income-list']);

        try {
            if ($session_year_id == 'undefined' || empty($session_year_id)) {
                $session_year_id = $this->cache->getSessionYear()->id;
            }
            $data = $this->incomeService->distribution($session_year_id);
            ResponseService::successResponse('Data Fetched Successfully', $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "Income Dashboard Controller -> Distribution Method");
            ResponseService::errorResponse();
        }
    }
}
