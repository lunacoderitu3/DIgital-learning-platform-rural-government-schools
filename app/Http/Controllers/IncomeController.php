<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncomeRequest;
use App\Repositories\Income\IncomeInterface;
use App\Repositories\IncomeCategory\IncomeCategoryInterface;
use App\Repositories\SessionYear\SessionYearInterface;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\IncomeService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class IncomeController extends Controller
{
    private IncomeInterface $income;
    private IncomeCategoryInterface $incomeCategory;
    private SessionYearInterface $sessionYear;
    private CachingService $cache;
    private IncomeService $incomeService;

    public function __construct(IncomeInterface $income, IncomeCategoryInterface $incomeCategory, SessionYearInterface $sessionYear, CachingService $cache, IncomeService $incomeService)
    {
        $this->income = $income;
        $this->incomeCategory = $incomeCategory;
        $this->sessionYear = $sessionYear;
        $this->cache = $cache;
        $this->incomeService = $incomeService;
    }

    public function index()
    {
        ResponseService::noFeatureThenRedirect('Finance Management');
        ResponseService::noAnyPermissionThenRedirect(['income-create', 'income-list']);

        $incomeCategory = $this->incomeCategory->builder()->where('status', 1)->pluck('name', 'id')->toArray();
        $sessionYears = $this->sessionYear->builder()->orderBy('id', 'DESC')->get();
        $currentSessionYear = $this->cache->getSessionYear();
        $months = sessionYearWiseMonthYear();

        return view('income.index', compact('incomeCategory', 'sessionYears', 'currentSessionYear', 'months'));
    }

    public function create()
    {
        ResponseService::noFeatureThenRedirect('Finance Management');
        ResponseService::noPermissionThenRedirect('income-create');
    }

    public function store(IncomeRequest $request)
    {
        ResponseService::noFeatureThenSendJson('Finance Management');
        ResponseService::noPermissionThenSendJson('income-create');

        try {
            DB::beginTransaction();
            $schoolSettings = $this->cache->getSchoolSettings();

            $referenceNo = $request->reference_no ?: $this->incomeService->nextReferenceNo($request->session_year_id);

            $this->income->create([
                'income_category_id' => $request->income_category_id,
                'title' => $request->title,
                'reference_no' => $referenceNo,
                'amount' => $request->amount,
                'date' => $request->date
                    ? Carbon::createFromFormat($schoolSettings['date_format'], $request->date)->format('Y-m-d')
                    : null,
                'description' => $request->description,
                'session_year_id' => $request->session_year_id,
                'created_by' => Auth::id(),
            ]);

            DB::commit();
            ResponseService::successResponse('Data Stored Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Income Controller -> Store Method");
            ResponseService::errorResponse();
        }
    }

    public function show($id)
    {
        ResponseService::noFeatureThenRedirect('Finance Management');
        ResponseService::noPermissionThenRedirect('income-list');

        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'date');
        $order = request('order', 'DESC');
        $search = request('search');
        $category_id = request('category_id');
        $month = request('month');
        $session_year_id = request('session_year_id') ?: $this->cache->getSessionYear()->id;

        $sql = $this->income->builder()->with(['category'])->where(function ($query) use ($search) {
            $query->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'LIKE', "%$search%")
                        ->orWhere('reference_no', 'LIKE', "%$search%")
                        ->orWhere('amount', 'LIKE', "%$search%")
                        ->orWhere('date', 'LIKE', "%$search%")
                        ->orWhere('description', 'LIKE', "%$search%")
                        ->orWhereHas('category', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%$search%");
                        });
                });
            });
        });

        if ($category_id) {
            $sql->where('income_category_id', $category_id);
        }

        if ($session_year_id) {
            $sql->where('session_year_id', $session_year_id);
        }

        if ($month) {
            $month_year = explode('_', $month);
            $sql->whereMonth('date', $month_year[0])->whereYear('date', $month_year[1]);
        }

        $total = $sql->count();
        if ($offset >= $total && $total > 0) {
            $lastPage = floor(($total - 1) / $limit) * $limit;
            $offset = $lastPage;
        }
        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = $offset + 1;

        foreach ($res as $row) {
            // $operate = BootstrapTableService::button('fa fa-eye', route('income.view', $row->id), ['btn-gradient-info'], ['title' => trans('view')]);
            $operate = BootstrapTableService::editButton(route('income.update', $row->id));
            $operate .= BootstrapTableService::deleteButton(route('income.destroy', $row->id));

            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function update(IncomeRequest $request, $id)
    {
        ResponseService::noFeatureThenRedirect('Finance Management');
        ResponseService::noPermissionThenSendJson('income-edit');

        try {
            DB::beginTransaction();
            $schoolSettings = $this->cache->getSchoolSettings();

            $referenceNo = $request->reference_no ?: $this->incomeService->nextReferenceNo($request->session_year_id);

            $this->income->update($id, [
                'income_category_id' => $request->income_category_id,
                'title' => $request->title,
                'reference_no' => $referenceNo,
                'amount' => $request->amount,
                'date' => $request->date
                    ? Carbon::createFromFormat($schoolSettings['date_format'], $request->date)->format('Y-m-d')
                    : null,
                'description' => $request->description,
                'session_year_id' => $request->session_year_id,
                'updated_by' => Auth::id(),
            ]);

            DB::commit();
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Income Controller -> Update Method");
            ResponseService::errorResponse();
        }
    }

    public function destroy($id)
    {
        ResponseService::noFeatureThenRedirect('Finance Management');
        ResponseService::noPermissionThenSendJson('income-delete');

        try {
            DB::beginTransaction();
            $this->income->deleteById($id);
            DB::commit();
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Income Controller -> Destroy Method");
            ResponseService::errorResponse();
        }
    }

    public function view($id)
    {
        ResponseService::noFeatureThenRedirect('Finance Management');
        ResponseService::noPermissionThenRedirect('income-list');

        $income = $this->income->builder()->with(['category', 'creator', 'updater', 'sessionYear'])->findOrFail($id);
        $currencySymbol = $this->cache->getSchoolSettings('currency_symbol') ?? '';

        return view('income.view', compact('income', 'currencySymbol'));
    }
}
