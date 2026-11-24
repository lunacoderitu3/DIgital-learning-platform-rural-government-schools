<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncomeCategoryRequest;
use App\Repositories\IncomeCategory\IncomeCategoryInterface;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\ResponseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class IncomeCategoryController extends Controller
{
    private IncomeCategoryInterface $incomeCategory;
    private CachingService $cache;

    public function __construct(IncomeCategoryInterface $incomeCategory, CachingService $cache)
    {
        $this->incomeCategory = $incomeCategory;
        $this->cache = $cache;
    }

    public function index()
    {
        ResponseService::noFeatureThenRedirect('Finance Management');
        ResponseService::noAnyPermissionThenRedirect(['income-category-create', 'income-category-list']);

        return view('income.category');
    }

    public function create()
    {
        ResponseService::noFeatureThenRedirect('Finance Management');
        ResponseService::noPermissionThenRedirect('income-category-create');
    }

    public function store(IncomeCategoryRequest $request)
    {
        ResponseService::noFeatureThenSendJson('Finance Management');
        ResponseService::noPermissionThenSendJson('income-category-create');

        try {
            DB::beginTransaction();
            $this->incomeCategory->create([
                'name' => $request->name,
                'description' => $request->description,
                'created_by' => Auth::id(),
            ]);
            DB::commit();
            ResponseService::successResponse('Data Stored Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Income Category Controller -> Store Method");
            ResponseService::errorResponse();
        }
    }

    public function show()
    {
        ResponseService::noFeatureThenRedirect('Finance Management');
        ResponseService::noPermissionThenRedirect('income-category-list');

        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');
        $showDeleted = request('show_deleted');

        $sql = $this->incomeCategory->builder()
            ->where(function ($query) use ($search) {
                $query->when($search, function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('name', 'LIKE', "%$search%")
                            ->orWhere('description', 'LIKE', "%$search%");
                    });
                });
            })->when(!empty($showDeleted), function ($q) {
                $q->onlyTrashed();
            });

        $total = $sql->count();

        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        if ($offset >= $total && $total > 0) {
            $lastPage = floor(($total - 1) / $limit) * $limit;
            $offset = $lastPage;
        }
        $res = $sql->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = $offset + 1;

        foreach ($res as $row) {
            $operate = '';
            if (empty($showDeleted)) {
                $operate .= BootstrapTableService::editButton(route('income-category.update', $row->id));
                $operate .= BootstrapTableService::deleteButton(route('income-category.destroy', $row->id));
            } else {
                $operate .= BootstrapTableService::restoreButton(route('income-category.restore', $row->id));
                $operate .= BootstrapTableService::trashButton(route('income-category.trash', $row->id));
            }

            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function edit()
    {
        ResponseService::noFeatureThenRedirect('Finance Management');
        ResponseService::noPermissionThenRedirect('income-category-edit');
    }

    public function update(IncomeCategoryRequest $request, $id)
    {
        ResponseService::noFeatureThenRedirect('Finance Management');
        ResponseService::noPermissionThenSendJson('income-category-edit');

        try {
            DB::beginTransaction();
            $this->incomeCategory->update($id, [
                'name' => $request->name,
                'description' => $request->description,
                'updated_by' => Auth::id(),
            ]);
            DB::commit();
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Income Category Controller -> Update Method");
            ResponseService::errorResponse();
        }
    }

    public function destroy($id)
    {
        ResponseService::noFeatureThenRedirect('Finance Management');
        ResponseService::noPermissionThenSendJson('income-category-delete');

        try {
            DB::beginTransaction();

            $category = $this->incomeCategory->findById($id);
            if ($category->incomes()->exists()) {
                ResponseService::errorResponse('cannot_delete_because_data_is_associated_with_other_data');
            }

            $this->incomeCategory->deleteById($id);

            DB::commit();
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Income Category Controller -> Delete Method");
            ResponseService::errorResponse();
        }
    }

    public function restore($id)
    {
        ResponseService::noFeatureThenRedirect('Finance Management');
        ResponseService::noPermissionThenSendJson('income-category-delete');

        try {
            DB::beginTransaction();
            $this->incomeCategory->restoreById($id);
            DB::commit();
            ResponseService::successResponse('Data Restored Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Income Category Controller -> Restore Method");
            ResponseService::errorResponse();
        }
    }

    public function trash($id)
    {
        ResponseService::noFeatureThenRedirect('Finance Management');
        ResponseService::noPermissionThenSendJson('income-category-delete');

        try {
            DB::beginTransaction();
            $category = $this->incomeCategory->findOnlyTrashedById($id);
            if ($category->incomes()->exists()) {
                ResponseService::errorResponse('cannot_delete_because_data_is_associated_with_other_data');
            } else {
                $this->incomeCategory->permanentlyDeleteById($id);
            }
            DB::commit();
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Income Category Controller -> Trash Method");
            ResponseService::errorResponse();
        }
    }
}
