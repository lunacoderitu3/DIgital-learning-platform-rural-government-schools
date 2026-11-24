<?php

namespace App\Http\Controllers;

use App\Models\FeeDiscount;
use App\Models\FeesAdvance;
use App\Models\CompulsoryFee;
use App\Models\FeesClassType;
use App\Models\PromoteStudent;
use App\Repositories\ClassSchool\ClassSchoolInterface;
use App\Repositories\ClassSection\ClassSectionInterface;
use App\Repositories\CompulsoryFee\CompulsoryFeeInterface;
use App\Repositories\Fees\FeesInterface;
use App\Repositories\FeesClassType\FeesClassTypeInterface;
use App\Repositories\FeesInstallment\FeesInstallmentInterface;
use App\Repositories\FeesPaid\FeesPaidInterface;
use App\Repositories\FeesType\FeesTypeInterface;
use App\Repositories\Medium\MediumInterface;
use App\Repositories\OptionalFee\OptionalFeeInterface;
use App\Repositories\PaymentConfiguration\PaymentConfigurationInterface;
use App\Repositories\PaymentTransaction\PaymentTransactionInterface;
use App\Repositories\SchoolSetting\SchoolSettingInterface;
use App\Repositories\SessionYear\SessionYearInterface;
use App\Repositories\Student\StudentInterface;
use App\Repositories\SystemSetting\SystemSettingInterface;
use App\Repositories\User\UserInterface;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Storage;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\ResponseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;
use App\Services\UserService;
use App\Services\WhatsAppService;
use Redirect;
use App\Models\FeesPaid;
use App\Models\Fee;
use App\Models\Students;
use App\Models\OptionalFee;
use App\Models\PaymentTransaction;
use Google\Model;

class FeesController extends Controller
{
    private FeesInterface $fees;
    private SessionYearInterface $sessionYear;
    private FeesInstallmentInterface $feesInstallment;
    private SchoolSettingInterface $schoolSettings;
    private MediumInterface $medium;
    private FeesTypeInterface $feesType;
    private ClassSchoolInterface $classes;
    private FeesClassTypeInterface $feesClassType;
    private UserInterface $user;
    private FeesPaidInterface $feesPaid;
    private CompulsoryFeeInterface $compulsoryFee;
    private OptionalFeeInterface $optionalFee;
    private CachingService $cache;
    private PaymentConfigurationInterface $paymentConfigurations;
    private ClassSchoolInterface $class;
    private StudentInterface $student;
    private PaymentTransactionInterface $paymentTransaction;
    private SystemSettingInterface $systemSetting;
    private ClassSectionInterface $classSection;
    private UserService $userService;
    private WhatsAppService $whatsAppService;

    public function __construct(FeesInterface $fees, SessionYearInterface $sessionYear, FeesInstallmentInterface $feesInstallment, SchoolSettingInterface $schoolSettings, MediumInterface $medium, FeesTypeInterface $feesType, ClassSchoolInterface $classes, FeesClassTypeInterface $feesClassType, UserInterface $user, FeesPaidInterface $feesPaid, CompulsoryFeeInterface $compulsoryFee, OptionalFeeInterface $optionalFee, CachingService $cache, PaymentConfigurationInterface $paymentConfigurations, ClassSchoolInterface $classSchool, StudentInterface $student, PaymentTransactionInterface $paymentTransaction, SystemSettingInterface $systemSetting, ClassSectionInterface $classSection, UserService $userService, WhatsAppService $whatsAppService)
    {
        $this->fees = $fees;
        $this->sessionYear = $sessionYear;
        $this->feesInstallment = $feesInstallment;
        $this->schoolSettings = $schoolSettings;
        $this->medium = $medium;
        $this->feesType = $feesType;
        $this->classes = $classes;
        $this->feesClassType = $feesClassType;
        $this->user = $user;
        $this->feesPaid = $feesPaid;
        $this->compulsoryFee = $compulsoryFee;
        $this->optionalFee = $optionalFee;
        $this->cache = $cache;
        $this->paymentConfigurations = $paymentConfigurations;
        $this->class = $classSchool;
        $this->student = $student;
        $this->paymentTransaction = $paymentTransaction;
        $this->systemSetting = $systemSetting;
        $this->classSection = $classSection;
        $this->userService = $userService;
        $this->whatsAppService = $whatsAppService;
    }

    /* START : Fees Module */
    public function index()
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-list');
        $classes = $this->class->all(['*'], ['stream', 'medium', 'shift']);
        $feesTypeData = $this->feesType->all();
        $mediums = $this->medium->builder()->pluck('name', 'id');
        return view('fees.index', compact('classes', 'feesTypeData', 'mediums'));
    }

    public function store(Request $request)
    {

        ResponseService::noFeatureThenSendJson('Fees Management');
        ResponseService::noPermissionThenSendJson('fees-create');
        $request->validate([
            'include_fee_installments' => 'required|boolean',
            'due_date' => 'required|date',
            'due_charges_percentage' => 'required|numeric',
            'due_charges_amount' => 'required|numeric',
            'class_id' => 'required|array',
            'class_id.*' => 'required|numeric',
            'compulsory_fees_type' => 'required|array',
            'compulsory_fees_type.*' => 'required|array',
            'compulsory_fees_type.*.fees_type_id' => 'required|numeric',
            'compulsory_fees_type.*.amount' => 'required|numeric',
            'optional_fees_type.*' => 'required|array',
            'optional_fees_type.*.fees_type_id' => 'required|numeric',
            'optional_fees_type.*.amount' => 'required|numeric',
            'fees_installments' => 'required_if:include_fee_installments,1|array',
            'fees_installments.*.name' => 'required',
            'fees_installments.*.due_date' => 'required|date',
            'fees_installments.*.due_charges' => 'required|numeric'
        ]);
        try {

            if ($request->include_fee_installments) {
                $totalInstallments = collect($request->fees_installments)->sum('amount');
                $totalCompulsoryFees = collect($request->compulsory_fees_type)->sum('amount');

                if ((float) $totalInstallments !== (float) $totalCompulsoryFees) {
                    ResponseService::errorResponse('Total amount of Fees Installments is not equal to the total amount of Compulsory Fees');
                }
            }

            DB::beginTransaction();
            $sessionYear = $this->cache->getSessionYear();
            $classes = $this->class->builder()->whereIn("id", $request->class_id)->with('stream', 'medium')->get();

            $notifyUser = $this->student->builder()->where('session_year_id', $sessionYear->id)->whereHas('class_section', function ($q) use ($request) {
                $q->whereIn('class_id', $request->class_id);
            })->pluck('guardian_id');

            $title = 'Fees';
            $body = $request->name;
            $type = 'Fees';
            // send_notification($notifyUser, $title, $body, $type); // Send Notification

            foreach ($request->class_id as $class_id) {
                $class = $classes->first(function ($data) use ($class_id) {
                    return $data->id == $class_id;
                });
                $name = (!empty($request->name)) ? $request->name . " - " : "";
                $fees = $this->fees->create([
                    'name' => $name . $class->full_name,
                    'due_date' => $request->due_date,
                    'due_charges' => $request->due_charges_percentage,
                    'due_charges_amount' => $request->due_charges_amount,
                    'class_id' => $class_id,
                    'session_year_id' => $sessionYear->id,
                ]);

                $feeClassType = [];
                foreach ($request->compulsory_fees_type as $data) {
                    $feeClassType[] = array(
                        "fees_id" => $fees->id,
                        "class_id" => $class_id,
                        "fees_type_id" => $data['fees_type_id'],
                        "amount" => $data['amount'],
                        "optional" => 0,
                    );
                }

                if (!empty($request->optional_fees_type)) {
                    foreach ($request->optional_fees_type as $data) {
                        $feeClassType[] = array(
                            "fees_id" => $fees->id,
                            "class_id" => $class_id,
                            "fees_type_id" => $data['fees_type_id'],
                            "amount" => $data['amount'],
                            "optional" => 1,
                        );
                    }
                }

                if (count($feeClassType) > 0) {
                    $this->feesClassType->upsert($feeClassType, ['class_id', 'fees_type_id'], ['amount', 'optional']);
                }

                if ($request->include_fee_installments && count($request->fees_installments)) {
                    $installmentData = array();
                    foreach ($request->fees_installments as $data) {
                        $data = (object) $data;
                        $installmentData[] = array(
                            'name' => $data->name,
                            'due_date' => date('Y-m-d', strtotime($data->due_date)),
                            'due_charges_type' => $data->due_charges_type,
                            'due_charges' => $data->due_charges,
                            'fees_id' => $fees->id,
                            'session_year_id' => $sessionYear->id,
                            'installment_amount' => $data->amount
                        );
                    }
                    $this->feesInstallment->createBulk($installmentData);
                }
            }
            DB::commit();
            $students = $this->student->builder()
                ->whereHas('class_section', function ($q) use ($request) {
                    $q->whereIn('class_id', (array) $request->class_id);
                })
                ->where('session_year_id', $sessionYear->id)
                ->where(function ($q) {
                    $q->where('application_type', 'offline')
                        ->orWhere(function ($q) {
                            $q->where('application_type', 'online')
                                ->where('application_status', 1);
                        });
                })
                ->whereHas('user', function ($q) {
                    $q->where('status', 1);
                })
                ->with('user', 'class_section.class', 'class_section.section')
                ->get();
            $allPayloads = [];
            $title = 'Fees Added';
            $type = "fees";
            // Structured values for the WhatsApp fee_due template. Compulsory fees are the
            // same for every class in this request, so the total is a single shared amount.
            $currencySymbol = $this->cache->getSchoolSettings('currency_symbol') ?: '';
            $feeAmount = collect($request->compulsory_fees_type)->sum(fn($d) => (float) ($d['amount'] ?? 0));
            $dueDate = $request->due_date ? date('d-m-Y', strtotime($request->due_date)) : '';
            foreach ($students as $student) {

                // Guardian recipient (array because send_notification expects array of user IDs)
                $recipient = [$student->guardian_id];

                // Child info
                $childId = $student->id;
                $childName = trim(($student->user->full_name ?? ''));

                if ($childName === '') {
                    $childName = "Student #$childId";
                }

                // Notification text
                $body = "Dear {$childName}. New fees is added please pay the fees before due date.";

                // Custom payload. The structured keys feed the WhatsApp fee_due template
                // (parent_name/school_name are added centrally); push ignores extras.
                $customData = [
                    'child_id'     => $childId,
                    'student_name' => $childName,
                    'class'        => $student->class_section->full_name ?? '',
                    'amount'       => trim($currencySymbol . ' ' . number_format($feeAmount, 2)),
                    'due_date'     => $dueDate,
                    'school_id'    => Auth::user()->school_id ?? null,
                ];

                // Send push + WhatsApp (WhatsApp auto-dispatched inside buildPayloads)
                $payloads = buildPayloads($recipient, $title, $body, $type, $customData);
                $allPayloads = array_merge($allPayloads, $payloads);
            }

            sendBulk($allPayloads);
            ResponseService::successResponse('Data Stored Successfully');
        } catch (Throwable $e) {
            if (
                Str::contains($e->getMessage(), [
                    'does not exist',
                    'file_get_contents'
                ])
            ) {
                DB::commit();
                ResponseService::warningResponse("Data Stored successfully. But App push notification not send.");
            } else {
                DB::rollback();
                ResponseService::logErrorResponse($e, "FeesController -> Store Method");
                ResponseService::errorResponse();
            }
        }
    }

    public function show()
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-list');
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');
        $showDeleted = request('show_deleted');
        $session_year_id = $this->cache->getSessionYear()->id;
        $medium_id = request('medium_id');

        $sql = $this->fees->builder()->with('installments', 'class:id,name,stream_id,medium_id,shift_id', 'class.medium:id,name', 'class.shift:id,name', 'class.stream:id,name', 'fees_class_type.fees_type:id,name')
            ->where('session_year_id', $session_year_id)
            ->where(function ($q) use ($search) {
                $q->when($search, function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")
                        ->orwhere('name', 'LIKE', "%$search%")
                        ->orwhere('due_date', 'LIKE', "%$search%")
                        ->orwhere('due_charges', 'LIKE', "%$search%");
                });
            })
            ->when(!empty($showDeleted), function ($query) {
                $query->onlyTrashed();
            })->when($medium_id, function ($query) use ($medium_id) {
                $query->whereHas('class', function ($q) use ($medium_id) {
                    $q->where('medium_id', $medium_id);
                });
            });

        $total = $sql->count();
        if ($offset >= $total && $total > 0) {
            $lastPage = floor(($total - 1) / $limit) * $limit; // calculate last page offset
            $offset = $lastPage;
        }
        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = $offset + 1;
        foreach ($res as $row) {
            $operate = '';
            if ($showDeleted) {
                $operate .= BootstrapTableService::restoreButton(route('fees.restore', $row->id));
                $operate .= BootstrapTableService::trashButton(route('fees.trash', $row->id));
            } else {
                $operate .= BootstrapTableService::editButton(route('fees.edit', $row->id), false);
                $operate .= BootstrapTableService::deleteButton(route('fees.destroy', $row->id));
            }

            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['compulsory_fees'] = number_format($row->fees_class_type->filter(function ($data) {
                return $data->optional == 0;
            })->sum('amount'), 2);
            $tempRow['total_fees'] = number_format($row->fees_class_type->sum('amount'), 2);
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function edit($id)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-edit');
        $classes = $this->class->all(['*'], ['stream', 'medium', 'stream']);
        $feesTypeData = $this->feesType->all();

        $fees = $this->fees->builder()->with(['fees_class_type', 'installments', 'class.medium'])->withCount('fees_paid')->findOrFail($id);

        return view('fees.edit', compact('classes', 'feesTypeData', 'fees'));
    }

    public function update(Request $request, $id)
    {
        ResponseService::noFeatureThenSendJson('Fees Management');
        ResponseService::noPermissionThenSendJson('fees-edit');

        $request->validate([
            'include_fee_installments' => 'required|boolean',
            'due_date' => 'required|date',
            'due_charges_percentage' => 'required|numeric',
            'due_charges_amount' => 'required|numeric',
            'compulsory_fees_type' => 'required|array',
            'compulsory_fees_type.*' => 'required|array',
            'compulsory_fees_type.*.fees_type_id' => 'required|numeric',
            'compulsory_fees_type.*.amount' => 'required|numeric',
            'optional_fees_type.*' => 'required|array',
            'optional_fees_type.*.fees_type_id' => 'required|numeric',
            'optional_fees_type.*.amount' => 'required|numeric',
            'fees_installments' => 'nullable|array',
            'fees_installments.*.name' => 'required',
            'fees_installments.*.due_date' => 'required|date',
            'fees_installments.*.due_charges' => 'required|numeric'
        ]);


        if ($request->include_fee_installments) {
            $totalInstallments = collect($request->fees_installments)->sum('amount');
            $totalCompulsoryFees = collect($request->compulsory_fees_type)->sum('amount');

            if ((float) $totalInstallments !== (float) $totalCompulsoryFees) {
                return ResponseService::errorRedirectResponse(route('fees.edit', $id), 'Total amount of Fees Installments is not equal to the total amount of Compulsory Fees');
            }
        }

        try {
            DB::beginTransaction();
            $sessionYear = $this->cache->getSessionYear();

            // Fees Data Store
            $feesData = [
                'name' => $request->name,
                'due_date' => $request->due_date ? date('Y-m-d', strtotime($request->due_date)) : null,
                'due_charges' => $request->due_charges_percentage,
                'due_charges_amount' => $request->due_charges_amount
            ];

            $fees = $this->fees->update($id, $feesData);
            DB::commit();



            foreach ($request->compulsory_fees_type as $data) {
                $feeClassType[] = array(
                    "id" => $data['id'],
                    "fees_id" => $fees->id,
                    "class_id" => $fees->class_id,
                    "fees_type_id" => $data['fees_type_id'],
                    "amount" => $data['amount'],
                    "optional" => 0,
                );
            }

            if (!empty($request->optional_fees_type)) {
                foreach ($request->optional_fees_type as $data) {
                    $feeClassType[] = array(
                        "id" => $data['id'],
                        "fees_id" => $fees->id,
                        "class_id" => $fees->class_id,
                        "fees_type_id" => $data['fees_type_id'],
                        "amount" => $data['amount'],
                        "optional" => 1,
                    );
                }
            }

            if (isset($feeClassType)) {
                $this->feesClassType->upsert($feeClassType, ['id'], ['fees_type_id', 'amount', 'optional']);
            }

            if (!empty($request->fees_installments)) {
                $installmentData = array();
                foreach ($request->fees_installments as $data) {
                    $data = (object) $data;
                    $installmentData[] = array(
                        'id' => $data->id,
                        'name' => $data->name,
                        'due_date' => date('Y-m-d', strtotime($data->due_date)),
                        'due_charges_type' => $data->due_charges_type,
                        'due_charges' => $data->due_charges,
                        'fees_id' => $fees->id,
                        'session_year_id' => $sessionYear->id,
                        'installment_amount' => $data->amount
                    );
                }

                $this->feesInstallment->upsert($installmentData, ['id'], ['name', 'due_date', 'due_charges', 'due_charges_type', 'fees_id', 'session_year_id', 'installment_amount']);
            }

            $students = $this->student->builder()
                ->whereHas('class_section', function ($q) use ($fees) {
                    $q->whereIn('class_id', (array) $fees->class_id);
                })
                ->where('session_year_id', $sessionYear->id)
                ->where(function ($q) {
                    $q->where('application_type', 'offline')
                        ->orWhere(function ($q) {
                            $q->where('application_type', 'online')
                                ->where('application_status', 1);
                        });
                })
                ->whereHas('user', function ($q) {
                    $q->where('status', 1);
                })
                ->with('class_section.class', 'class_section.section', 'class_section.medium')
                ->get();
            $allPayloads = [];
            $title = 'Fees Updated';
            $type = "fees";
            $currencySymbol = $this->cache->getSchoolSettings('currency_symbol') ?: '';
            $feeAmount = collect($request->compulsory_fees_type)->sum(fn($d) => (float) ($d['amount'] ?? 0));
            $dueDate = $request->due_date ? date('d-m-Y', strtotime($request->due_date)) : '';
            foreach ($students as $student) {

                // Guardian recipient (array because send_notification expects array of user IDs)
                $recipient = [$student->guardian_id];

                // Child info
                $childId = $student->id;
                $childName = trim(($student->user->full_name ?? ''));

                if ($childName === '') {
                    $childName = "Student #$childId";
                }

                // Notification text
                $body = "Dear {$childName}. Fees is upated please pay the fees before due date.";

                // Custom payload
                $customData = [
                    'child_id'     => $childId,
                    'student_name' => $childName,
                    'class'        => $student->class_section->full_name ?? '',
                    'amount'       => trim($currencySymbol . ' ' . number_format($feeAmount, 2)),
                    'due_date'     => $dueDate,
                    'school_id'    => Auth::user()->school_id ?? null,
                ];

                // Send notification
                $payloads = buildPayloads($recipient, $title, $body, $type, $customData);
                $allPayloads = array_merge($allPayloads, $payloads);
            }

            DB::commit();
            sendBulk($allPayloads);
            ResponseService::successResponse('Data Update Successfully');
        } catch (Throwable) {
            DB::rollback();
            ResponseService::errorRedirectResponse();
        }
    }

    public function destroy($id)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenSendJson('fees-delete');
        try {
            DB::beginTransaction();
            $this->fees->deleteById($id);
            DB::commit();
            ResponseService::successResponse("Data Deleted Successfully");
        } catch (\Exception $e) {
            DB::rollback();
            // Return validation error instead of generic error
            return ResponseService::errorResponse($e->getMessage());
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "FeesController -> Store Method");
            ResponseService::errorResponse();
        }
    }

    public function restore(int $id)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-delete');
        try {
            $this->fees->findOnlyTrashedById($id)->restore();
            ResponseService::successResponse("Data Restored Successfully");
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function search(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        try {
            $data = $this->fees->builder()->where('session_year_id', $request->session_year_id)->get();
            ResponseService::successResponse("Data Restored Successfully", $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function trash($id)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-delete');
        try {
            $this->fees->findOnlyTrashedById($id)->forceDelete();
            ResponseService::successResponse("Data Deleted Permanently");
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    /* END : Fees Module */

    public function deleteInstallment($id)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        try {
            DB::beginTransaction();
            $this->feesInstallment->DeleteById($id);
            DB::commit();
            ResponseService::successResponse("Data Deleted Successfully");
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function deleteClassType($id)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        try {
            DB::beginTransaction();
            $this->feesClassType->DeleteById($id);
            DB::commit();
            ResponseService::successResponse("Data Deleted Successfully");
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function removeOptionalFees($id)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-paid');
        try {
            DB::beginTransaction();

            // Get Fees Paid ID and Amount of Fees Transaction Table
            $optionalFeeData = $this->optionalFee->findById($id);
            $feesPaidId = $optionalFeeData->fees_paid_id;
            $optionalFeeAmount = $optionalFeeData->amount;

            $this->optionalFee->permanentlyDeleteById($id); // Permanently Delete Optional Fees Data

            // Check Fees Transactions Entry
            $feesPaidDataQuery = $this->feesPaid->builder()->where('id', $feesPaidId);
            if ($feesPaidDataQuery->count()) {
                // Get Fees Paid Data
                $feesPaidAmount = $feesPaidDataQuery->first()->amount; // Get Fees Paid Amount
                $finalAmount = $feesPaidAmount - $optionalFeeAmount; // Calculate Final Amount
                if ($finalAmount > 0) {
                    $this->feesPaid->update($feesPaidId, ['amount' => $finalAmount]); // Update Fees Paid Data with Final Amount
                } else {
                    $this->feesPaid->permanentlyDeleteById($feesPaidId);
                }
            } else {
                $this->feesPaid->permanentlyDeleteById($feesPaidId);
            }

            DB::commit();
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function removeInstallmentFees($compulsoryFeesPaidID)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-paid');
        try {
            DB::beginTransaction();

            // Get Fees Paid ID and Amount of Fees Transaction Table
            $installmentFeeTransaction = $this->compulsoryFee->findById($compulsoryFeesPaidID);
            $feesPaidId = $installmentFeeTransaction->fees_paid_id;
            $feesTransactionAmount = $installmentFeeTransaction->amount;

            $this->compulsoryFee->permanentlyDeleteById($compulsoryFeesPaidID); // Permanently Delete Fees Transaction Data

            // Check Fees Transactions Entry
            $feesPaidDataQuery = $this->feesPaid->builder()->where('id', $feesPaidId);
            if ($feesPaidDataQuery->count()) {
                // Get Fees Paid Data
                $feesPaidAmount = $feesPaidDataQuery->first()->amount; // Get Fees Paid Amount
                $finalAmount = $feesPaidAmount - $feesTransactionAmount; // Calculate Final Amount
                if ($finalAmount > 0) {
                    $this->feesPaid->update($feesPaidId, ['amount' => $finalAmount, 'is_fully_paid' => 0]); // Update Fees Paid Data with Final Amount
                } else {
                    $this->feesPaid->permanentlyDeleteById($feesPaidId);
                }
            } else {
                $this->feesPaid->permanentlyDeleteById($feesPaidId);
            }

            DB::commit();
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function feesConfigIndex()
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-config');

        // List of the names to be fetched
        $names = array('currency_code', 'currency_symbol',);

        $settings = $this->schoolSettings->getBulkData($names); // Passing the array of names and gets the array of data
        $domain = request()->getSchemeAndHttpHost(); // Get Current Web Domain

        $stripeData = $this->paymentConfigurations->all()->where('payment_method', 'stripe')->first();
        return view('fees.fees_config', compact('settings', 'domain', 'stripeData'));
    }

    public function feesConfigUpdate(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-config');
        $request->validate(['stripe_status' => 'required', 'stripe_publishable_key' => 'required_if:stripe_status,1|nullable', 'stripe_secret_key' => 'required_if:stripe_status,1|nullable', 'stripe_webhook_secret' => 'required_if:stripe_status,1|nullable', 'stripe_webhook_url' => 'required_if:stripe_status,1|nullable', 'currency_code' => 'required|max:10', 'currency_symbol' => 'required|max:5',]);
        try {
            $this->paymentConfigurations->updateOrCreate(['payment_method' => strtolower('stripe')], ['api_key' => $request->stripe_publishable_key, 'secret_key' => $request->stripe_secret_key, 'webhook_secret_key' => $request->stripe_webhook_secret, 'status' => $request->stripe_status]);


            // Store Currency Code and Currency Symbol in School Settings
            $settings = array('currency_code', 'currency_symbol');

            $data = array();
            foreach ($settings as $row) {
                $data[] = [
                    "name" => $row,
                    "data" => $row == 'school_name' ? str_replace('"', '', $request->$row) : $request->$row,
                    "type" => "string"
                ];
            }

            $this->schoolSettings->upsert($data, ["name"], ["data"]);
            Cache::flush();

            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function feesTransactionsLogsIndex()
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-paid');
        $classes = $this->classes->builder()->orderByRaw('CONVERT(name, SIGNED) asc')->with('medium', 'stream', 'sections')->get();
        $mediums = $this->medium->builder()->orderBy('id', 'ASC')->get();

        $months = sessionYearWiseMonthYear();

        return response(view('fees.fees_transaction_logs', compact('classes', 'mediums', 'months')));
    }

    public function feesTransactionsLogsList(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-paid');
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $sessionYearId = $this->cache->getSessionYear()->id;

        //Fetching Students Data on Basis of Class Section ID with Relation fees paid
        $sql = $this->paymentTransaction->builder()->with('user:id,first_name,last_name,image,email')->where(function ($query) use ($sessionYearId) {
            $query->whereHas('compulsory_fees.fees_paid.fees', function ($q) use ($sessionYearId) {
                $q->where('session_year_id', $sessionYearId);
            })->orWhereHas('optional_fees.fees_paid.fees', function ($q) use ($sessionYearId) {
                $q->where('session_year_id', $sessionYearId);
            })->orWhereHas('transportation_payment', function ($q) use ($sessionYearId) {
                $q->where('session_year_id', $sessionYearId);
            });
        });

        if (!empty($request->search)) {
            $search = $request->search;
            $sql->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%$search%")
                    ->orwhere('order_id', 'LIKE', "%$search%")->orwhere('payment_id', 'LIKE', "%$search%")
                    ->orwhere('payment_gateway', 'LIKE', "%$search%")->orwhere('amount', 'LIKE', "%$search%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('first_name', 'LIKE', "%$search%")->orwhere('last_name', 'LIKE', "%$search%");
                    });
            });
        }

        if ($request->payment_status) {
            $sql = $sql->where('payment_status', $request->payment_status);
        }

        if ($request->payment_method) {
            $sql = $sql->where('payment_gateway', $request->payment_method);
        }

        if ($request->month) {
            $month_year = explode('_', $request->month);
            $sql = $sql->whereRaw('MONTH(created_at) = ?', [$month_year[0]])->whereRaw('YEAR(created_at) = ?', [$month_year[1]]);
        }

        $total = $sql->count();
        if ($offset >= $total && $total > 0) {
            $lastPage = floor(($total - 1) / $limit) * $limit; // calculate last page offset
            $offset = $lastPage;
        }
        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;
        foreach ($res as $row) {
            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    /* START : Fees Paid Module */
    public function feesPaidListIndex()
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-paid');

        // Fees Data With Few Selected Data
        $sessionYearId = $this->cache->getSessionYear()->id;
        $fees = $this->fees->builder()->select(['id', 'name', 'class_id'])->where('session_year_id', $sessionYearId)->get();
        $classes = $this->classes->all(['*'], ['medium', 'sections', 'shift']);
        //        $session_year_all = $this->sessionYear->builder()->where('default', 1)->get();
        $class_section = $this->classSection->builder()->with('class', 'class.stream', 'class.shift', 'section', 'medium')->get();
        $months = sessionYearWiseMonthYear();
        return response(view('fees.fees_paid', compact('fees', 'classes', 'months', 'class_section')));
    }




    public function feesPaidList(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-paid');

        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $feesId = (int) request('fees_id');
        $sessionYearId = $this->cache->getSessionYear()->id;
        $class_section_id = request('class_section_id');
        $class_id = request('class_id');
        $settings = $this->cache->getSchoolSettings();

        $fees = null;
        $isCurrentSession = false;
        $requestedSessionYearId = null;

        if ($feesId) {
            $fees = $this->fees->findById($feesId, ['*'], [
                'fees_class_type.fees_type:id,name',
                'installments:id,name,due_date,due_charges,fees_id',
                'fees_paid' => function ($q) {
                    $q->withSum([
                        'compulsory_fee' => function ($q) {
                            $q->whereNull('deleted_at')
                                ->where('status', CompulsoryFee::CHEQUE_SUCCESS);
                        }
                    ], 'amount')
                        ->withSum([
                            'compulsory_fee' => function ($q) {
                                $q->whereNull('deleted_at')
                                    ->where('status', CompulsoryFee::CHEQUE_SUCCESS);
                            }
                        ], 'due_charges')
                        ->withSum([
                            'optional_fee' => function ($q) {
                                $q->whereNull('deleted_at')
                                    ->where('status', OptionalFee::CHEQUE_SUCCESS);
                            }
                        ], 'amount');
                }
            ]);

            $sql = $this->user->builder()->role('Student')->select('id', 'first_name', 'last_name', 'email', 'image');

            $requestedSessionYearId = $fees->session_year_id;
            $defaultSessionYear = $this->cache->getDefaultSessionYear();
            $isCurrentSession = ($defaultSessionYear->id == $sessionYearId);

            if ($isCurrentSession) {
                $sql->with([
                    'student' => function ($query) use ($sessionYearId) {
                        $query->select('id', 'class_section_id', 'user_id', 'session_year_id')->with([
                            'class_section' => function ($query) {
                                $query->select('id', 'class_id', 'section_id', 'medium_id')->with(
                                    'class:id,name,shift_id,stream_id',
                                    'section:id,name',
                                    'medium:id,name',
                                    'class.shift:id,name',
                                    'class.stream:id,name'
                                );
                            }
                        ])->where('session_year_id', $sessionYearId);
                    }
                ])->whereHas('student', function ($q) use ($sessionYearId, $fees, $class_section_id, $class_id) {
                    $q->where('session_year_id', $sessionYearId)
                        ->whereHas('class_section', function ($q) use ($fees, $class_section_id, $class_id) {
                            $q->where('class_id', $fees->class_id);
                            if ($class_id) $q->where('class_id', $class_id);
                            if ($class_section_id) $q->where('id', $class_section_id);
                        });
                });
            } else {
                $sql->with([
                    'student' => function ($query) {
                        $query->withoutGlobalScopes();
                    },
                    'promote_student' => function ($query) use ($requestedSessionYearId) {
                        $query->where(function ($q) use ($requestedSessionYearId) {
                            $q->where('session_year_id', $requestedSessionYearId)
                                ->orWhere('session_year_id', $requestedSessionYearId);
                        })->with([
                            'class_section' => function ($query) {
                                $query->select('id', 'class_id', 'section_id', 'medium_id')->with(
                                    'class:id,name,shift_id,stream_id',
                                    'section:id,name',
                                    'medium:id,name',
                                    'class.shift:id,name',
                                    'class.stream:id,name'
                                );
                            }
                        ]);
                    }
                ])->whereHas('promote_student', function ($q) use ($requestedSessionYearId, $fees, $class_section_id, $class_id) {
                    $q->where(function ($query) use ($requestedSessionYearId, $fees, $class_section_id, $class_id) {
                        $query->where('session_year_id', $requestedSessionYearId)
                            ->whereHas('class_section', function ($q) use ($fees, $class_section_id, $class_id) {
                                $q->where('class_id', $fees->class_id);
                                if ($class_id) $q->where('class_id', $class_id);
                                if ($class_section_id) $q->where('id', $class_section_id);
                            });
                    })->orWhere(function ($query) use ($requestedSessionYearId, $fees, $class_section_id, $class_id) {
                        $query->where('session_year_id', $requestedSessionYearId)
                            ->whereHas('class_section', function ($q) use ($fees, $class_section_id, $class_id) {
                                $q->where('class_id', $fees->class_id);
                                if ($class_id) $q->where('class_id', $class_id);
                                if ($class_section_id) $q->where('id', $class_section_id);
                            });
                    });
                });
            }

            $sql->with([
                'optional_fees' => function ($query) use ($fees) {
                    $query->with('fees_class_type')
                        ->whereHas('fees_class_type', function ($q) use ($fees) {
                            $q->where('fees_id', $fees->id);
                        });
                },
                'fees_paid' => function ($q) use ($fees) {
                    $q->where('fees_id', $fees->id)
                        ->with([
                            'compulsory_fee' => function ($query) {
                                $query->whereNull('deleted_at')->with('file');
                            }
                        ]);
                },
                'compulsory_fees' => function ($q) use ($fees) {
                    $q->whereNull('deleted_at')
                        ->with('file')
                        ->where(function ($inner) use ($fees) {
                            $inner->whereHas('fees_paid', function ($q) use ($fees) {
                                $q->withoutGlobalScopes()->where('fees_id', $fees->id);
                            })->orWhereHas('installment_fee', function ($q) use ($fees) {
                                $q->where('fees_id', $fees->id);
                            });
                        });
                }
            ]);

            if (!empty($_GET['search'])) {
                $search = $_GET['search'];
                $sql->where(function ($q) use ($search) {
                    $q->where('id', 'LIKE', "%$search%")
                        ->orWhere('first_name', 'LIKE', "%$search%")
                        ->orWhere('last_name', 'LIKE', "%$search%")
                        ->orWhere('email', 'LIKE', "%$search%")
                        ->orWhereHas('fees_paid.compulsory_fee', function ($query) use ($search) {
                            $query->where('cheque_no', 'LIKE', "%$search%");
                        })
                        ->orWhereHas('fees_paid.optional_fee', function ($query) use ($search) {
                            $query->where('cheque_no', 'LIKE', "%$search%");
                        });
                });
            }

            $currencySymbol = $settings['currency_symbol'] ?? '';

            $total_compulsory_fees = ($fees->total_compulsory_fees * $sql->count());
            $total_optional_fees   = ($fees->total_optional_fees * $sql->count());
            $total_fees            = $total_compulsory_fees + $total_optional_fees;

            $fees_data = [
                'total_fees'            => $total_fees,
                'total_compulsory_fees' => $total_compulsory_fees,
                'total_optional_fees'   => $total_optional_fees,
                'currency_symbol'       => $currencySymbol,
            ];

            // Total Collected Fees
            if (count($fees->fees_paid)) {
                $total_compulsory_fees_collected = $fees->fees_paid->sum('compulsory_fee_sum_amount');
                $total_optional_fees_collected = $fees->fees_paid->sum('optional_fee_sum_amount');
                $total_fees_collected = $total_compulsory_fees_collected + $total_optional_fees_collected;
                $fees_data['total_fees_collected'] = $total_fees_collected;
                $fees_data['total_compulsory_fees_collected'] = $total_compulsory_fees_collected;
                $fees_data['total_optional_fees_collected'] = $total_optional_fees_collected;
                $fees_data['compulsory_fees_sum_due_charges'] = $fees->fees_paid->sum('compulsory_fee_sum_due_charges');
            }

            if ($request->month) {
                $sql->whereHas('fees_paid', function ($q) use ($request, $fees) {
                    $q->whereMonth('date', $request->month)
                        ->where('fees_id', $fees->id);
                });
            }

            // =========================================================================
            // PAYMENT GATEWAY & ONLINE/OFFLINE FILTERS
            // =========================================================================
            if ($request->payment_gateway == 'cash_cheque') {
                $sql->where(function ($query) use ($fees) {
                    $query->whereHas('compulsory_fees', function ($q) use ($fees) {
                        $q->whereNull('deleted_at')
                            ->whereIn('mode', ['Cash', 'Cheque'])
                            ->where(function ($sub) use ($fees) {
                                $sub->whereHas('fees_paid', function ($fp) use ($fees) {
                                    $fp->withoutGlobalScopes()->where('fees_id', $fees->id);
                                })->orWhereHas('installment_fee', function ($inst) use ($fees) {
                                    $inst->where('fees_id', $fees->id);
                                });
                            });
                    })->orWhereHas('fees_paid', function ($fp) use ($fees) {
                        $fp->withoutGlobalScopes()
                            ->where('fees_id', $fees->id)
                            ->whereHas('compulsory_fee', function ($q) {
                                $q->whereNull('deleted_at')->whereIn('mode', ['Cash', 'Cheque']);
                            });
                    });
                });
            }

            if ($request->payment_gateway == 'stripe_razorpay') {
                $onlineGateways = ['Stripe', 'Razorpay', 'Flutterwave', 'Paystack'];
                $sql->where(function ($query) use ($fees, $onlineGateways) {
                    $query->whereHas('compulsory_fees', function ($q) use ($fees, $onlineGateways) {
                        $q->whereNull('deleted_at')
                            ->where(function ($sub) use ($fees) {
                                $sub->whereHas('fees_paid', function ($fp) use ($fees) {
                                    $fp->withoutGlobalScopes()->where('fees_id', $fees->id);
                                })->orWhereHas('installment_fee', function ($inst) use ($fees) {
                                    $inst->where('fees_id', $fees->id);
                                });
                            })
                            ->whereHas('payment_transaction', function ($pt) use ($onlineGateways) {
                                $pt->whereIn('payment_gateway', $onlineGateways);
                            });
                    })->orWhereHas('fees_paid', function ($fp) use ($fees, $onlineGateways) {
                        $fp->withoutGlobalScopes()
                            ->where('fees_id', $fees->id)
                            ->whereHas('compulsory_fee.payment_transaction', function ($pt) use ($onlineGateways) {
                                $pt->whereIn('payment_gateway', $onlineGateways);
                            });
                    });
                });
            }

            if ($request->online_offline_payment) {
                $modes[] = $request->online_offline_payment;
                // $modes = ($request->online_offline_payment == 2)
                //     ? ['Cash', 'Cheque']
                //     : ['Stripe', 'Razorpay', 'Flutterwave', 'Paystack'];

                $sql->where(function ($query) use ($fees, $modes, $request) {
                    if ($request->online_offline_payment == 2) {
                        $query->whereHas('compulsory_fees', function ($q) use ($fees, $modes) {
                            $q->whereNull('deleted_at')
                                ->whereIn('mode', $modes)
                                ->where(function ($sub) use ($fees) {
                                    $sub->whereHas('fees_paid', function ($fp) use ($fees) {
                                        $fp->withoutGlobalScopes()->where('fees_id', $fees->id);
                                    })->orWhereHas('installment_fee', function ($inst) use ($fees) {
                                        $inst->where('fees_id', $fees->id);
                                    });
                                });
                        })->orWhereHas('fees_paid', function ($fp) use ($fees, $modes) {
                            $fp->withoutGlobalScopes()
                                ->where('fees_id', $fees->id)
                                ->whereHas('compulsory_fee', function ($q) use ($modes) {
                                    $q->whereNull('deleted_at')->whereIn('mode', $modes);
                                });
                        });
                    } else {
                        $query->whereHas('compulsory_fees', function ($q) use ($fees, $modes) {
                            $q->whereNull('deleted_at')
                                ->where(function ($sub) use ($fees) {
                                    $sub->whereHas('fees_paid', function ($fp) use ($fees) {
                                        $fp->withoutGlobalScopes()->where('fees_id', $fees->id);
                                    })->orWhereHas('installment_fee', function ($inst) use ($fees) {
                                        $inst->where('fees_id', $fees->id);
                                    });
                                })
                                ->where(function ($modeQuery) use ($modes) {
                                    $modeQuery->whereIn('mode', $modes)
                                        ->orWhereHas('payment_transaction', function ($pt) use ($modes) {
                                            $pt->whereIn('payment_gateway', $modes);
                                        });
                                });
                        })->orWhereHas('fees_paid', function ($fp) use ($fees, $modes) {
                            $fp->withoutGlobalScopes()
                                ->where('fees_id', $fees->id)
                                ->whereHas('compulsory_fee', function ($q) use ($modes) {
                                    $q->whereNull('deleted_at')
                                        ->where(function ($modeQuery) use ($modes) {
                                            $modeQuery->whereIn('mode', $modes)
                                                ->orWhereHas('payment_transaction', function ($pt) use ($modes) {
                                                    $pt->whereIn('payment_gateway', $modes);
                                                });
                                        });
                                });
                        });
                    }
                });
            }

            // =========================================================================
            // REUSABLE FEES STATUS & DISCOUNT EVALUATION CLOSURE (SINGLE TRUTH SOURCE)
            // =========================================================================
            $calculateStudentFeesStatus = function ($studentRow, $studentDiscounts) use ($fees) {
                $directFees = $studentRow->compulsory_fees ? $studentRow->compulsory_fees->toArray() : [];
                $nestedFees = ($studentRow->fees_paid && $studentRow->fees_paid->compulsory_fee)
                    ? $studentRow->fees_paid->compulsory_fee->toArray()
                    : [];

                $mergedFees = collect(array_merge($directFees, $nestedFees))
                    ->unique('id')
                    ->values()
                    ->toArray();

                // Sum confirmed payments strictly for compulsory fees
                $confirmedTransactions = collect($mergedFees)->filter(function ($item) {
                    return ($item['status'] ?? '') == CompulsoryFee::CHEQUE_SUCCESS
                        || strtolower($item['status'] ?? '') === 'success';
                });

                $compulsoryPaidSum       = $confirmedTransactions->sum('amount');
                $compulsoryDueChargesSum = $confirmedTransactions->sum('due_charges');

                // Count distinct paid installments
                $paidInstallmentIds     = $confirmedTransactions->pluck('installment_id')->filter()->unique();
                $paidInstallmentsCount  = $paidInstallmentIds->count();
                $totalInstallmentsCount = count($fees->installments);

                // Discount calculation
                $totalCompulsoryDiscount = 0;
                if ($totalInstallmentsCount > 0) {
                    $installmentDiscounts = [];
                    foreach ($studentDiscounts as $discount) {
                        foreach ($discount->items ?? [] as $item) {
                            if ($item->fee_type === 'installment') {
                                $installmentDiscounts[$item->fee_type_id] =
                                    ($installmentDiscounts[$item->fee_type_id] ?? 0) + $item->discount_amount;
                            }
                        }
                    }
                    $totalCompulsoryDiscount = array_sum($installmentDiscounts);
                } else {
                    $totalCompulsoryDiscount = $studentDiscounts->whereIn('discount_scope', ['compulsory', 'entire'])->sum('discount_value');
                }

                $netCompulsoryFee = max(0, $fees->total_compulsory_fees - $totalCompulsoryDiscount);

                // Fully-paid evaluation
                $isCompulsoryFullyPaid = ($totalInstallmentsCount > 0 && $paidInstallmentsCount >= $totalInstallmentsCount)
                    || (!empty($studentRow->fees_paid) && $studentRow->fees_paid->is_fully_paid == 1)
                    || ($compulsoryPaidSum >= ($netCompulsoryFee - 0.01) && $netCompulsoryFee > 0);

                $due_date   = Carbon::parse($fees->due_date);
                $today_date = Carbon::now()->format('Y-m-d');

                if ($isCompulsoryFullyPaid) {
                    $feesStatus = 1; // Fully Paid (Green)
                } elseif ($compulsoryPaidSum > 0 || $paidInstallmentsCount > 0) {
                    $feesStatus = 3; // Partial Paid (Yellow)
                } else {
                    $feesStatus = $due_date->gt($today_date) ? 0 : 2; // Unpaid (0) / Overdue (2)
                }

                return [
                    'fees_status'                => $feesStatus,
                    'is_fully_paid'              => $isCompulsoryFullyPaid,
                    'compulsory_paid_sum'        => $compulsoryPaidSum,
                    'compulsory_due_charges_sum' => $compulsoryDueChargesSum,
                    'merged_fees'                => $mergedFees,
                    'net_compulsory_fee'         => $netCompulsoryFee,
                    'total_compulsory_discount'  => $totalCompulsoryDiscount,
                ];
            };

            // =========================================================================
            // TOOLBAR paid_status FILTER (DISCOUNT & INSTALLMENT AWARE VIA SHARED CLOSURE)
            // =========================================================================
            if ($request->has('paid_status') && $request->paid_status != -1 && $request->paid_status !== null) {
                $candidateStudentIds = (clone $sql)->pluck('id');

                $candidateStudents = $this->user->builder()
                    ->whereIn('id', $candidateStudentIds)
                    ->with([
                        'fees_paid' => function ($q) use ($fees) {
                            $q->withoutGlobalScopes()->where('fees_id', $fees->id)->with(['compulsory_fee' => function ($query) {
                                $query->whereNull('deleted_at');
                            }]);
                        },
                        'compulsory_fees' => function ($q) use ($fees) {
                            $q->whereNull('deleted_at')->where(function ($inner) use ($fees) {
                                $inner->whereHas('fees_paid', function ($fp) use ($fees) {
                                    $fp->withoutGlobalScopes()->where('fees_id', $fees->id);
                                })->orWhereHas('installment_fee', function ($inst) use ($fees) {
                                    $inst->where('fees_id', $fees->id);
                                });
                            });
                        }
                    ])->get();

                $discountsByStudent = FeeDiscount::whereIn('student_id', $candidateStudentIds)
                    ->where('fees_id', $fees->id)
                    ->with('items')
                    ->get()
                    ->groupBy('student_id');

                $matchedIds = [];
                foreach ($candidateStudents as $candidateStudent) {
                    $calc = $calculateStudentFeesStatus(
                        $candidateStudent,
                        $discountsByStudent->get($candidateStudent->id, collect())
                    );

                    if ($request->paid_status == 0 && ($calc['fees_status'] === 0 || $calc['fees_status'] === 2)) {
                        // 0 = UNPAID
                        $matchedIds[] = $candidateStudent->id;
                    } elseif ($request->paid_status == 1 && $calc['fees_status'] === 1) {
                        // 1 = FULLY PAID
                        $matchedIds[] = $candidateStudent->id;
                    } elseif ($request->paid_status == 2 && $calc['fees_status'] === 3) {
                        // 2 = PARTIAL PAID (fees_status 3)
                        $matchedIds[] = $candidateStudent->id;
                    }
                }

                $sql->whereIn('id', $matchedIds);
            }
        }

        $total = $sql->count();
        if ($offset >= $total && $total > 0) {
            $lastPage = floor(($total - 1) / $limit) * $limit;
            $offset = $lastPage;
        }
        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $bulkData          = [];
        $bulkData['total'] = $total;
        $rows              = [];
        $no                = 1;

        // Pre-load discounts for paginated rows to prevent N+1 queries
        $pageStudentIds = $res->pluck('id')->toArray();
        $pageDiscounts  = FeeDiscount::whereIn('student_id', $pageStudentIds)
            ->where('fees_id', $fees->id)
            ->with('items')
            ->get()
            ->groupBy('student_id');

        foreach ($res as $row) {
            if (!$isCurrentSession) {
                if ($row->promote_student && $row->promote_student->count() > 0) {
                    $ps            = $row->promote_student->first();
                    $class_section = $ps->session_year_id == $requestedSessionYearId
                        ? $ps->class_section
                        : $ps->current_class_section;

                    if ($class_section) {
                        $studentModel = new Students([
                            'id'               => $row->student->id ?? null,
                            'class_section_id' => $class_section->id,
                            'user_id'          => $row->id,
                            'session_year_id'  => $requestedSessionYearId,
                        ]);
                        $studentModel->setRelation('class_section', $class_section);
                        $row->setRelation('student', $studentModel);
                    }
                } else {
                    $row->setRelation('student', null);
                }
                $row->unsetRelation('promote_student');
            }

            $tempRow         = $row->toArray();
            $fees_data['no'] = $no++;
            $tempRow['no']   = $fees_data;

            if (count($fees->installments) > 0) {
                collect($fees->installments)->map(function ($data) use ($fees) {
                    $data['minimum_amount'] = $fees->total_compulsory_fees / count($fees->installments);
                    $data['total_amount']   = $data['minimum_amount'];
                    return $data;
                });
            }
            $tempRow['fees'] = $fees->toArray();

            // =========================================================================
            // PER-ROW STATUS & AMOUNTS (VIA SHARED CALCULATION CLOSURE)
            // =========================================================================
            $studentDiscounts = $pageDiscounts->get($row->id, collect());
            $statusCalc = $calculateStudentFeesStatus($row, $studentDiscounts);

            $tempRow['fees_status']     = $statusCalc['fees_status'];
            $tempRow['paid_amount']     = number_format($statusCalc['compulsory_paid_sum'], 2);
            $tempRow['due_charges']     = number_format($statusCalc['compulsory_due_charges_sum'], 2);
            $tempRow['compulsory_fees'] = $statusCalc['merged_fees'];

            $operate = BootstrapTableService::menuButton('Compulsory Fees', route('fees.compulsory.index', [$fees->id, $row->id]));

            if (count($fees->optional_fees) > 0) {
                $operate .= BootstrapTableService::menuButton('Optional Fees', route('fees.optional.index', [$fees->id, $row->id]));
            }

            if (!empty($row->fees_paid) && $row->fees_paid->amount > 0) {
                $operate .= BootstrapTableService::menuButton('Receipt', route('fees.paid.receipt.pdf', [$row->fees_paid->fees_id, $row->id]), [], ['target' => '_blank']);
            }

            $pendingTxn = collect($statusCalc['merged_fees'])->first(function ($item) {
                return in_array($item['mode'] ?? '', ['Manual', 'Cheque'])
                    && strtolower($item['status'] ?? '') === 'pending';
            });

            $tempRow['receipt_url']           = null;
            $tempRow['fee_id_for_status']     = null;
            $tempRow['manual_payment_status'] = null;

            if ($pendingTxn) {
                $tempRow['fee_id_for_status']     = $pendingTxn['id'];
                $tempRow['manual_payment_status'] = $pendingTxn['status'];
                $tempRow['receipt_url']           = $pendingTxn['receipt_url']
                    ?? ($pendingTxn['file']['file_url'] ?? null);
            }

            // Exclude failed transactions for both payment_method and date fallback
            $latestValidFee = collect($statusCalc['merged_fees'])
                ->filter(function ($item) {
                    return strtolower($item['status'] ?? '') !== 'failed';
                })
                ->sortByDesc('id')
                ->first();

            $tempRow['payment_method'] = $latestValidFee ? ($latestValidFee['mode'] ?? null) : null;

            // Populate Date fallback from latest valid non-failed transaction
            if (empty($tempRow['fees_paid']['date']) && $latestValidFee) {
                $tempRow['fees_paid']['date'] = $latestValidFee['date'] ?? null;
            }

            $tempRow['operate'] = BootstrapTableService::menuItem($operate);
            $rows[]             = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function transactionReceiptPDF($type, $transactionId)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-paid');
        try {
            return $this->userService->generateTransactionReceiptPDF($type, $transactionId, 'panel');
        } catch (\Throwable $e) {
            ResponseService::errorRedirectResponse();
            return false;
        }
    }


    public function feesPaidReceiptPDF($feesId, $studentId)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-paid');
        try {

            return $this->userService->generateSchoolFeesReceiptPDF($feesId, $studentId, 'panel');

            // $sessionYear = $this->cache->getSessionYear();
            // $feesPaid = $this->feesPaid->builder()->where('id', $feesPaidId)
            //     ->with([
            //         'fees.fees_class_type.fees_type',
            //         'fees.session_year',
            //         'compulsory_fee.installment_fee:id,name',
            //         'compulsory_fee.fees_paid.fees',
            //         'optional_fee' => function ($q) {
            //             $q->with([
            //                 'fees_class_type' => function ($q) {
            //                     $q->select('id', 'fees_type_id')->with('fees_type:id,name');
            //                 }
            //             ]);
            //         },
            //         'compulsory_fee' => function ($q) {
            //             $q->whereNull('deleted_at')
            //                 ->with([
            //                     'installment_fee:id,name',
            //                     'fees_paid.fees'
            //                 ]);
            //         },
            //     ])->firstOrFail();

            // $student = $this->student->builder()->where('session_year_id', $sessionYear->id)->with('user:id,first_name,last_name', 'class_section.class.stream', 'class_section.class.shift', 'class_section.section', 'class_section.medium')->whereHas('user', function ($q) use ($feesPaid) {
            //     $q->where('id', $feesPaid->student_id);
            // })->firstOrFail();

            // $school = $this->cache->getSchoolSettings();

            // $data = explode("storage/", $school['horizontal_logo'] ?? '');
            // $school['horizontal_logo'] = end($data);

            // if ($school['horizontal_logo'] == null) {
            //     $systemSettings = $this->cache->getSystemSettings();
            //     $data = explode("storage/", $systemSettings['horizontal_logo'] ?? '');
            //     $school['horizontal_logo'] = end($data);
            // }

            // $pdf = Pdf::loadView('fees.fees_receipt', compact('school', 'feesPaid', 'student'));
            // return $pdf->stream('fees-receipt.pdf');
        } catch (Throwable $e) {
            ResponseService::errorRedirectResponse();
            return false;
        }
    }
    public function payCompulsoryFeesIndex($feesID, $studentID)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');

        $fees = $this->fees->findById($feesID, ['*'], [
            'fees_class_type.fees_type:id,name',
            'installments:id,name,due_date,due_charges,due_charges_type,fees_id,installment_amount'
        ]);

        $oneInstallmentPaid = false;

        $student = $this->user->builder()->role('Student')->select('id', 'first_name', 'last_name', 'email')
            ->with([
                'student' => function ($query) {
                    $query->select('id', 'class_section_id', 'user_id', 'guardian_id')->with([
                        'class_section' => function ($query) {
                            $query->select('id', 'class_id', 'section_id', 'medium_id')->with(
                                'class:id,name,shift_id,stream_id',
                                'class.shift:id,name',
                                'class.stream:id,name',
                                'section:id,name',
                                'medium:id,name'
                            );
                        }
                    ]);
                },
                'fees_paids' => function ($q) use ($feesID) {
                    $q->where('fees_id', $feesID)
                        ->with([
                            'compulsory_fee' => function ($q) {
                                $q->whereNull('deleted_at');
                            }
                        ])
                        ->withSum([
                            'compulsory_fee as compulsory_fee_sum' => function ($q) {
                                $q->whereNull('deleted_at');
                            }
                        ], 'amount');
                },
                'compulsory_fees' => function ($q) use ($feesID) {
                    $q->whereNull('deleted_at')->whereHas('fees_paid', function ($q2) use ($feesID) {
                        $q2->where('fees_id', $feesID);
                    });
                },
                'compulsory_fees.advance_fees',
                'compulsory_fees.payment_transaction',
                'compulsory_fees.installment_fee:id,name,due_date'
            ])
            ->findOrFail($studentID);

        $sessionYear = $this->cache->getSessionYear();
        $defaultSessionYear = $this->cache->getDefaultSessionYear();
        if ($sessionYear->id != $defaultSessionYear->id) {
            $promoteStudent = PromoteStudent::where('student_id', $studentID)
                ->where('session_year_id', $sessionYear->id)
                ->with(['class_section' => function ($query) {
                    $query->select('id', 'class_id', 'section_id', 'medium_id')->with(
                        'class:id,name,shift_id,stream_id',
                        'class.shift:id,name',
                        'class.stream:id,name',
                        'section:id,name',
                        'medium:id,name'
                    );
                }])->first();

            if ($promoteStudent) {
                $student->student->class_section = $promoteStudent->class_section;
            }
        }

        $feesPaidIds = $student->fees_paids->pluck('id')->toArray();

        // Allowed successful statuses
        $successStatuses = [CompulsoryFee::CHEQUE_SUCCESS, 'Success', 1, '1'];

        // Fetch student active discounts
        $discounts = FeeDiscount::where('student_id', $studentID)
            ->where('fees_id', $feesID)
            ->with('items')
            ->get();

        $installmentDiscounts = [];
        $optionalDiscounts = [];
        foreach ($discounts as $discount) {
            foreach ($discount->items as $item) {
                if ($item->fee_type === 'installment') {
                    $installmentDiscounts[$item->fee_type_id] = ($installmentDiscounts[$item->fee_type_id] ?? 0) + $item->discount_amount;
                } elseif ($item->fee_type === 'optional') {
                    $optionalDiscounts[$item->fee_type_id] = ($optionalDiscounts[$item->fee_type_id] ?? 0) + $item->discount_amount;
                }
            }
        }

        $totalCompulsoryDiscount = 0;
        if (count($fees->installments) > 0) {
            $totalCompulsoryDiscount = array_sum($installmentDiscounts);
        } else {
            $totalCompulsoryDiscount = $discounts->whereIn('discount_scope', ['compulsory', 'entire'])->sum('discount_value');
        }

        $net_payable_fee = max(0, $fees->total_compulsory_fees - $totalCompulsoryDiscount);

        // Calculate ACTUAL confirmed paid amount
        $total_confirmed_paid = $student->compulsory_fees
            ->whereNull('deleted_at')
            ->whereIn('status', $successStatuses)
            ->sum('amount');

        $isFullyPaid = ($net_payable_fee > 0 && $total_confirmed_paid >= ($net_payable_fee - 0.01));

        // Fetch class optional components
        $optionalFeesData = FeesClassType::where('fees_id', $feesID)
            ->where(['class_id' => $student->student->class_section->class_id, 'optional' => 1])
            ->with('fees_type')
            ->get();

        $installment_status = 0;
        if (count($fees->installments) > 0) {
            $installment_status = 1;
            $totalInstallments = count($fees->installments);

            // RUNNING SURPLUS TRACKER
            $runningSurplus = 0;

            collect($fees->installments->sortBy('due_date'))->map(function ($installment) use ($student, &$totalInstallments, $fees, &$oneInstallmentPaid, $isFullyPaid, $installmentDiscounts, $successStatuses, &$runningSurplus) {
                // Base Amount Paid specifically for this installment
                $paidBase = $student->compulsory_fees
                    ->whereNull('deleted_at')
                    ->where('installment_id', $installment->id)
                    ->whereIn('status', $successStatuses)
                    ->sum('amount');

                // Net Installment Amount
                $inst_amount = $installment->installment_amount > 0
                    ? $installment->installment_amount
                    : ($fees->total_compulsory_fees / $fees->installments->count());
                $installmentDiscountAmount = $installmentDiscounts[$installment->id] ?? 0;
                $inst_net_amount = max(0, $inst_amount - $installmentDiscountAmount);

                $installment['original_amount'] = $inst_amount;
                $installment['discount_amount'] = $installmentDiscountAmount;
                $installment['net_amount']      = $inst_net_amount;

                // Total credit available = Direct Paid + Carried Over Surplus
                $totalAvailable = $paidBase + $runningSurplus;

                // Due Charges Penalty Logic
                $due_date_obj = Carbon::parse($installment->due_date);
                $inst_due_charges_amount = 0;

                if ($isFullyPaid || $totalAvailable >= $inst_net_amount) {
                    $inst_due_charges_amount = 0;
                } elseif ($due_date_obj->isPast() && !$due_date_obj->isToday()) {
                    if ($installment->due_charges_type == "percentage") {
                        $inst_due_charges_amount = ($inst_net_amount * $installment->due_charges) / 100;
                    } elseif ($installment->due_charges_type == "fixed") {
                        $inst_due_charges_amount = $installment->due_charges;
                    }
                }

                $installment['due_charges_amount']    = $inst_due_charges_amount;
                $total_expected                       = $inst_net_amount + $inst_due_charges_amount;
                $installment['total_expected_amount'] = $total_expected;

                // Balance due accounting for surplus
                if ($totalAvailable >= ($total_expected - 0.01)) {
                    $balance_due = 0;
                    $installment['paid_amount'] = $total_expected;
                    // Pass surplus to next installment
                    $runningSurplus = max(0, $totalAvailable - $total_expected);
                } else {
                    $balance_due = max(0, $total_expected - $totalAvailable);
                    $installment['paid_amount'] = $totalAvailable;
                    $runningSurplus = 0; // Exhausted
                }

                $installment['balance_due']    = $isFullyPaid ? 0 : $balance_due;
                $installment['minimum_amount'] = $balance_due;

                // Status Logic (1 = Paid, 2 = Partial, 0 = Unpaid, 3 = Discounted)
                if ($inst_net_amount <= 0) {
                    $installment['status'] = 3; // Discounted
                } elseif ($isFullyPaid || $balance_due <= 0.01) {
                    $installment['status']  = 1; // Fully Paid
                    $installment['is_paid'] = true;
                    $oneInstallmentPaid     = true;
                    --$totalInstallments;
                } elseif ($installment['paid_amount'] > 0) {
                    $installment['status'] = 2; // Partial Paid
                    $oneInstallmentPaid    = true;
                } else {
                    $installment['status'] = 0; // Unpaid
                }

                $installment['transactions'] = $student->compulsory_fees
                    ->whereNull('deleted_at')
                    ->where('installment_id', $installment->id)
                    ->whereIn('status', $successStatuses)
                    ->values();

                $installment['total_amount'] = $total_expected;

                return $installment;
            });
        }

        // Calculate proportional compulsory components discounts
        $compulsoryComponentsDiscounts = [];
        $totalCompulsoryOriginal = (isset($fees->compulsory_fees) && count($fees->compulsory_fees) > 0) ? $fees->compulsory_fees->sum('amount') : 0;
        if ($totalCompulsoryOriginal > 0) {
            foreach ($fees->compulsory_fees as $c_fee) {
                if ($totalCompulsoryDiscount > 0) {
                    $compulsoryComponentsDiscounts[$c_fee->id] = round(($c_fee->amount / $totalCompulsoryOriginal) * $totalCompulsoryDiscount, 2);
                } else {
                    $compulsoryComponentsDiscounts[$c_fee->id] = 0;
                }
            }
            $diff = $totalCompulsoryDiscount - array_sum($compulsoryComponentsDiscounts);
            if ($diff != 0 && count($compulsoryComponentsDiscounts) > 0) {
                $firstKey = array_key_first($compulsoryComponentsDiscounts);
                $compulsoryComponentsDiscounts[$firstKey] += $diff;
            }
        }

        $due_charges = 0;
        $due_date = Carbon::createFromFormat('Y-m-d', $fees->getRawOriginal('due_date'));
        if ($due_date->isPast() && !$due_date->isToday() && $isFullyPaid != 1 && count($fees->installments) == 0) {
            $due_charges = $fees->due_charges_amount;
            if ($due_charges == 0 && $fees->due_charges > 0) {
                $due_charges = ($net_payable_fee * $fees->due_charges) / 100;
            }
        }

        // Calculate Minimum Payable Amount AND Total Payable Amount
        $metaData = $this->getPaymentMetadata($fees, $studentID);
        $min_payable_amount      = $metaData['min_payable_amount'];
        $total_payable_amount    = $metaData['total_payable_amount'];
        $overdue_installment_ids = $metaData['overdue_installment_ids'];
        $student_advance         = $metaData['student_advance'];

        $fees->complusory_details = $student->compulsory_fees->whereNull('deleted_at')->whereIn('fees_paid_id', $feesPaidIds);
        $payFullFeesAmountDueCharges = $total_payable_amount;

        $full_fee_total = max(0, $fees->total_compulsory_fees - $totalCompulsoryDiscount);
        $full_fee_due_charges = 0;
        if ($fees->due_date && Carbon::parse($fees->getRawOriginal('due_date'))->lt(Carbon::today()) && $isFullyPaid != 1) {
            if (isset($fees->due_charges_type)) {
                if ($fees->due_charges_type === 'percentage') {
                    $full_fee_due_charges = ($full_fee_total * $fees->due_charges) / 100;
                } elseif ($fees->due_charges_type === 'fixed') {
                    $full_fee_due_charges = $fees->due_charges;
                }
            } else {
                $full_fee_due_charges = $fees->due_charges_amount;
                if ($full_fee_due_charges == 0 && $fees->due_charges > 0) {
                    $full_fee_due_charges = ($full_fee_total * $fees->due_charges) / 100;
                }
            }
        }

        $paid_base_total = $student->compulsory_fees
            ->whereNull('deleted_at')
            ->whereIn('status', $successStatuses)
            ->sum('amount');

        $paid_due_charges_total = $student->compulsory_fees
            ->whereNull('deleted_at')
            ->whereIn('status', $successStatuses)
            ->sum('due_charges');

        $pending_base_total = max(0, $full_fee_total - $paid_base_total);
        $pending_due_charges_total = max(0, $full_fee_due_charges - $paid_due_charges_total);

        $instalmentFeesFullPaid = $pending_base_total + $pending_due_charges_total;

        $currencySymbol = $this->cache->getSchoolSettings('currency_symbol');

        return view('fees.pay-compulsory', compact(
            'fees',
            'student',
            'oneInstallmentPaid',
            'currencySymbol',
            'isFullyPaid',
            'due_charges',
            'installment_status',
            'student_advance',
            'min_payable_amount',
            'total_payable_amount',
            'overdue_installment_ids',
            'payFullFeesAmountDueCharges',
            'instalmentFeesFullPaid',
            'totalCompulsoryDiscount',
            'net_payable_fee',
            'optionalFeesData',
            'optionalDiscounts',
            'compulsoryComponentsDiscounts',
            'installmentDiscounts',
            'total_confirmed_paid'
        ));
    }



    public function getPaymentMetadata($fees, $studentID)
    {
        $min_payable_amount = 0;
        $total_payable_amount = 0;
        $overdue_installment_ids = [];

        // Fetch current advance amount from last transaction
        $last_transaction = $this->compulsoryFee->builder()
            ->where('student_id', $studentID)
            ->whereHas('fees_paid', function ($q) use ($fees) {
                $q->where('fees_id', $fees->id);
            })
            ->orderBy('id', 'DESC')
            ->first();
        $student_advance = $last_transaction ? $last_transaction->advance_amount : 0;

        $successStatuses = [CompulsoryFee::CHEQUE_SUCCESS, 'Success', 1, '1'];

        // Fetch student active discounts
        $discounts = FeeDiscount::where('student_id', $studentID)
            ->where('fees_id', $fees->id)
            ->with('items')
            ->get();

        $installmentDiscounts = [];
        foreach ($discounts as $discount) {
            foreach ($discount->items as $item) {
                if ($item->fee_type === 'installment') {
                    $installmentDiscounts[$item->fee_type_id] = ($installmentDiscounts[$item->fee_type_id] ?? 0) + $item->discount_amount;
                }
            }
        }

        $totalCompulsoryDiscount = 0;
        if (count($fees->installments) > 0) {
            $totalCompulsoryDiscount = array_sum($installmentDiscounts);
        } else {
            $totalCompulsoryDiscount = $discounts->whereIn('discount_scope', ['compulsory', 'entire'])->sum('discount_value');
        }

        if ($fees->include_fee_installments && count($fees->installments) > 0) {
            $runningSurplus = 0;
            $overdue_amount = 0;
            $next_due_amount = 0;
            $found_next_due = false;

            foreach ($fees->installments->sortBy('due_date') as $installment) {
                $due_date_obj = \Carbon\Carbon::parse($installment->due_date);
                $is_overdue   = $due_date_obj->isPast() && !$due_date_obj->isToday();

                $paid_installment_amount = CompulsoryFee::whereNull('deleted_at')
                    ->whereIn('status', $successStatuses)
                    ->where('installment_id', $installment->id)
                    ->where('student_id', $studentID)
                    ->sum('amount');

                $inst_amount = $installment->installment_amount > 0
                    ? $installment->installment_amount
                    : ($fees->total_compulsory_fees / $fees->installments->count());

                $inst_discount = $installmentDiscounts[$installment->id] ?? 0;
                $inst_amount_net = max(0, $inst_amount - $inst_discount);

                $totalAvailable = $paid_installment_amount + $runningSurplus;

                $inst_due_charges_amount = 0;
                if ($totalAvailable < $inst_amount_net && $is_overdue) {
                    if ($installment->due_charges_type == "percentage") {
                        $inst_due_charges_amount = ($inst_amount_net * $installment->due_charges) / 100;
                    } elseif ($installment->due_charges_type == "fixed") {
                        $inst_due_charges_amount = $installment->due_charges;
                    }
                }

                $total_expected = $inst_amount_net + $inst_due_charges_amount;

                if ($totalAvailable >= ($total_expected - 0.01)) {
                    $balance_due = 0;
                    $runningSurplus = max(0, $totalAvailable - $total_expected);
                } else {
                    $balance_due = max(0, $total_expected - $totalAvailable);
                    $runningSurplus = 0;
                }

                if ($balance_due > 0.01) {
                    $total_payable_amount += $balance_due;

                    if ($is_overdue) {
                        $overdue_amount += $balance_due;
                        $overdue_installment_ids[] = $installment->id;
                    } elseif (!$found_next_due) {
                        $next_due_amount = $balance_due;
                        $found_next_due = true;
                    }
                }
            }

            if ($overdue_amount > 0) {
                $min_payable_amount = $overdue_amount + ($next_due_amount > 0 ? $next_due_amount : 0);
            } else {
                $min_payable_amount = $next_due_amount;
            }

            $min_payable_amount = min($min_payable_amount, $total_payable_amount);
        } else {
            // Non-Installment Mode
            $due_date_fees = \Carbon\Carbon::parse($fees->getRawOriginal('due_date'));
            $is_fees_overdue = $due_date_fees->isPast() && !$due_date_fees->isToday();

            $net_compulsory_fee = max(0, $fees->total_compulsory_fees - $totalCompulsoryDiscount);
            $total_payable_amount = $net_compulsory_fee;

            if ($is_fees_overdue) {
                $due_charges = 0;
                if (isset($fees->due_charges_type)) {
                    if ($fees->due_charges_type === 'percentage') {
                        $due_charges = ($net_compulsory_fee * $fees->due_charges) / 100;
                    } elseif ($fees->due_charges_type === 'fixed') {
                        $due_charges = $fees->due_charges;
                    }
                } else {
                    $due_charges = $fees->due_charges_amount;
                    if ($due_charges == 0 && $fees->due_charges > 0) {
                        $due_charges = ($net_compulsory_fee * $fees->due_charges) / 100;
                    }
                }
                $total_payable_amount += $due_charges;
            }

            $paid_total = CompulsoryFee::whereNull('deleted_at')
                ->whereIn('status', $successStatuses)
                ->where('student_id', $studentID)
                ->whereHas('fees_paid', function ($q) use ($fees) {
                    $q->where('fees_id', $fees->id);
                })
                ->sum('amount');

            $total_payable_amount = max(0, $total_payable_amount - $paid_total);
            $min_payable_amount = $total_payable_amount;
        }

        return [
            'min_payable_amount'      => $min_payable_amount,
            'total_payable_amount'    => $total_payable_amount,
            'overdue_installment_ids' => $overdue_installment_ids,
            'student_advance'         => $student_advance,
        ];
    }
    public function deleteTransaction($id)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-paid');
        try {
            DB::beginTransaction();
            $transaction = $this->compulsoryFee->findById($id);
            $feesPaidId = $transaction->fees_paid_id;

            // Delete the transaction
            $transactionType = $transaction->type;
            $transaction->delete();

            // Recalculate FeesPaid Summary
            if ($feesPaidId) {
                $feesPaid = $this->feesPaid->findById($feesPaidId);

                // If the deleted transaction was a Full Payment, force generic reset.
                // compulsory_fees.type is an enum, so 1 is stored as 'Full Payment'.
                if ($transactionType == 1 || $transactionType === 'Full Payment') {
                    $feesPaid->update([
                        'amount' => 0,
                        'is_fully_paid' => 0,
                        'is_used_installment' => 1 // Revert to Installment Mode
                    ]);
                } else {
                    // Standard recalculation for Installment Payments
                    $total_paid = $this->compulsoryFee->builder()->whereNull('deleted_at')->where('fees_paid_id', $feesPaidId)->where('status', CompulsoryFee::CHEQUE_SUCCESS)->sum('amount');

                    // Recalculate fully paid status
                    $fees = $this->fees->findById($feesPaid->fees_id, ['*'], ['fees_class_type']);

                    // Calc total due charges paid
                    $total_due_charges_paid = $this->compulsoryFee->builder()->whereNull('deleted_at')->where('fees_paid_id', $feesPaidId)->sum('due_charges');

                    $is_fully_paid = $total_paid >= ($fees->total_compulsory_fees + $total_due_charges_paid);

                    $feesPaid->update([
                        'amount' => $total_paid,
                        'is_fully_paid' => $is_fully_paid
                    ]);
                }
            }

            DB::commit();
            ResponseService::successResponse("Transaction Deleted Successfully");
        } catch (\Throwable $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, 'FeesController -> deleteTransaction method');
            ResponseService::errorResponse();
        }
    }



    public function payCompulsoryFeesStore(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-paid');

        $chequeStatusMap = [
            'pending' => 'Pending',
            'success' => 'Success',
            'bounced' => 'Failed',
        ];

        $request->validate([
            'fees_id'          => 'required|numeric',
            'student_id'       => 'required|numeric',
            'installment_mode' => 'required|boolean',
            'amount'           => 'required|numeric|min:1',
            'date'             => 'required|date',
            'status'           => 'nullable|in:pending,success,bounced',
            'note'             => 'nullable|string',
        ]);

        // Backend Lock: Do not allow payment if a pending cheque exists for this fee
        $hasPendingCheque = CompulsoryFee::where('student_id', $request->student_id)
            ->where('status', CompulsoryFee::CHEQUE_PENDING)
            ->whereHas('fees_paid', fn($q) => $q->where('fees_id', $request->fees_id))
            ->whereNull('deleted_at')
            ->exists();

        if ($hasPendingCheque) {
            ResponseService::errorResponse(
                __('Cannot process payment. A cheque payment is currently pending clearance for this student. Please update the cheque status first.')
            );
        }

        try {
            DB::beginTransaction();

            // 1. Fetch Fees & Installments
            $fees = $this->fees->findById($request->fees_id, ['*'], ['installments', 'fees_class_type']);
            $student_id = $request->student_id;
            $input_amount = $request->amount;

            // 2. Fetch student active discounts
            $discounts = FeeDiscount::where('student_id', $student_id)
                ->where('fees_id', $fees->id)
                ->with('items')
                ->get();

            $installmentDiscounts = [];
            foreach ($discounts as $discount) {
                foreach ($discount->items as $item) {
                    if ($item->fee_type === 'installment') {
                        $installmentDiscounts[$item->fee_type_id] = ($installmentDiscounts[$item->fee_type_id] ?? 0) + $item->discount_amount;
                    }
                }
            }

            $totalCompulsoryDiscount = 0;
            if (count($fees->installments) > 0) {
                $totalCompulsoryDiscount = array_sum($installmentDiscounts);
            } else {
                $totalCompulsoryDiscount = $discounts->whereIn('discount_scope', ['compulsory', 'entire'])->sum('discount_value');
            }

            // 3. Get or Create FeesPaid Record
            $feesPaid = $this->feesPaid->builder()->where(['fees_id' => $fees->id, 'student_id' => $student_id])->first();
            if (empty($feesPaid)) {
                $feesPaid = $this->feesPaid->create([
                    'fees_id'             => $fees->id,
                    'student_id'          => $student_id,
                    'is_fully_paid'       => 0,
                    'is_used_installment' => $request->installment_mode,
                    'amount'              => 0,
                    'date'                => date('Y-m-d'),
                    'school_id'           => $fees->school_id,
                ]);
            }

            // Check if already fully paid
            if ($feesPaid->is_fully_paid) {
                return ResponseService::errorResponse("Compulsory Fees already fully Paid");
            }

            // Server-Side Validation
            $metaData = $this->getPaymentMetadata($fees, $student_id);
            $min_payable_server = $metaData['min_payable_amount'];
            $max_payable_server = $metaData['total_payable_amount'];
            $existing_advance   = $metaData['student_advance'];
            $total_available    = $input_amount + $existing_advance;

            if ((($total_available + 0.01) < $min_payable_server) && $request->installment_mode == 1) {
                throw new \Exception("Insufficient payment. You must pay at least " . number_format($min_payable_server, 2) . " (including advance).");
            }

            if (($total_available > ($max_payable_server + 1.00)) && $request->installment_mode == 1) {
                throw new \Exception("Overpayment not allowed. You are paying more than total outstanding dues (" . number_format($max_payable_server, 2) . ").");
            }

            // 4. Payment Allocation Loop
            $installments = $fees->installments->sortBy('due_date');

            if ($installments->count() > 0 && $request->installment_mode == 1) {
                $last_installment_id = $installments->last()->id;

                // One cheque covering several installments is a single payment, so all
                // the installment rows share one payment transaction and are reconciled
                // together when the admin updates the cheque status.
                $sharedChequeTransaction = null;
                $sharedChequeAmount = 0;

                foreach ($installments as $installment) {
                    if ($total_available <= 0) {
                        break;
                    }

                    // Calculate Net Installment Amount
                    $installment_amount = $installment->installment_amount > 0
                        ? $installment->installment_amount
                        : ($fees->total_compulsory_fees / $fees->installments->count());
                    $inst_discount = $installmentDiscounts[$installment->id] ?? 0;
                    $installment_net_amount = max(0, $installment_amount - $inst_discount);

                    // Calculate Due Charges (Penalty)
                    $total_due_charges = 0;
                    if (date('Y-m-d') > $installment->getRawOriginal('due_date')) {
                        if ($installment->due_charges_type == "percentage") {
                            $total_due_charges = ($installment_net_amount * $installment->due_charges) / 100;
                        } else if ($installment->due_charges_type == "fixed") {
                            $total_due_charges = $installment->due_charges;
                        }
                    }

                    // Check student_id + installment_id so mobile app payments are recognized
                    $paid_base = $this->compulsoryFee->builder()
                        ->where('student_id', $student_id)
                        ->where('installment_id', $installment->id)
                        ->whereNull('deleted_at')
                        ->where('status', CompulsoryFee::CHEQUE_SUCCESS)
                        ->sum('amount');

                    $paid_due_charges = $this->compulsoryFee->builder()
                        ->where('student_id', $student_id)
                        ->where('installment_id', $installment->id)
                        ->whereNull('deleted_at')
                        ->where('status', CompulsoryFee::CHEQUE_SUCCESS)
                        ->sum('due_charges');

                    $pending_base        = max(0, $installment_net_amount - $paid_base);
                    $pending_due_charges = max(0, $total_due_charges - $paid_due_charges);

                    // If this installment is already paid, skip to the next
                    if ($pending_base <= 0.01 && $pending_due_charges <= 0.01) {
                        continue;
                    }

                    // Allocate Funds: Due charges first, then base amount
                    $allocate_due_charges = 0;
                    $allocate_base = 0;

                    if ($total_available >= $pending_due_charges) {
                        $allocate_due_charges = $pending_due_charges;
                        $total_available -= $pending_due_charges;
                    } else {
                        $allocate_due_charges = $total_available;
                        $total_available = 0;
                    }

                    if ($total_available > 0) {
                        if ($total_available >= $pending_base) {
                            $allocate_base = $pending_base;
                            $total_available -= $pending_base;
                        } else {
                            $allocate_base = $total_available;
                            $total_available = 0;
                        }
                    }

                    // Create Transaction for the active unpaid installment
                    if ($allocate_base > 0 || $allocate_due_charges > 0) {
                        $txnStatus = $request->mode == 2
                            ? ($chequeStatusMap[$request->status] ?? CompulsoryFee::CHEQUE_PENDING)
                            : CompulsoryFee::CHEQUE_SUCCESS;

                        $paymentStatus = $txnStatus === CompulsoryFee::CHEQUE_SUCCESS ? 'succeed' : ($txnStatus === CompulsoryFee::CHEQUE_PENDING ? 'pending' : 'failed');

                        if ($request->mode == 2) {
                            // Reuse (and grow) the single transaction of this cheque
                            if (!$sharedChequeTransaction) {
                                $sharedChequeTransaction = PaymentTransaction::create([
                                    'user_id' => $student_id,
                                    'amount' => 0,
                                    'payment_gateway' => 'cheque',
                                    'payment_status' => $paymentStatus,
                                    'school_id' => $fees->school_id
                                ]);
                            }
                            $paymentTransaction = $sharedChequeTransaction;
                            $sharedChequeAmount += $allocate_base + $allocate_due_charges;
                            $sharedChequeTransaction->update(['amount' => $sharedChequeAmount]);
                        } else {
                            $paymentTransaction = PaymentTransaction::create([
                                'user_id' => $student_id,
                                'amount' => $allocate_base + $allocate_due_charges,
                                'payment_gateway' => 'cash',
                                'payment_status' => $paymentStatus,
                                'school_id' => $fees->school_id
                            ]);
                        }

                        $this->compulsoryFee->create([
                            'student_id'     => $student_id,
                            'fees_paid_id'   => $feesPaid->id,
                            'type'           => 2,
                            'installment_id' => $installment->id,
                            'mode'           => $request->mode,
                            'cheque_no'      => $request->mode == 2 ? $request->cheque_no : null,
                            'amount'         => $allocate_base,
                            'due_charges'    => $allocate_due_charges,
                            'date'           => date('Y-m-d', strtotime($request->date)),
                            'status'         => $txnStatus,
                            'notes'          => $request->note,
                            'school_id'      => $fees->school_id,
                            'payment_transaction_id' => $paymentTransaction->id
                        ]);
                    }

                    if ($installment->id == $last_installment_id && $total_available > 0.01) {
                        throw new \Exception("Overpayment is not allowed for the final installment. Surplus: " . $total_available);
                    }
                }
            } else {
                // Non-Installment Full/Partial logic
                $net_total_fee = max(0, $fees->total_compulsory_fees - $totalCompulsoryDiscount);
                $total_fee = $net_total_fee;
                $total_due_charges = 0;

                if (date('Y-m-d') > $fees->getRawOriginal('due_date')) {
                    if (isset($fees->due_charges_type)) {
                        if ($fees->due_charges_type === 'percentage') {
                            $total_due_charges = ($net_total_fee * $fees->due_charges) / 100;
                        } elseif ($fees->due_charges_type === 'fixed') {
                            $total_due_charges = $fees->due_charges;
                        }
                    } else {
                        $total_due_charges = $fees->due_charges_amount;
                        if ($total_due_charges == 0 && $fees->due_charges > 0) {
                            $total_due_charges = ($net_total_fee * $fees->due_charges) / 100;
                        }
                    }
                }

                $paid_base = $this->compulsoryFee->builder()
                    ->where('student_id', $student_id)
                    ->whereHas('fees_paid', fn($q) => $q->where('fees_id', $fees->id))
                    ->whereNull('deleted_at')
                    ->where('status', CompulsoryFee::CHEQUE_SUCCESS)
                    ->sum('amount');

                $paid_due_charges = $this->compulsoryFee->builder()
                    ->where('student_id', $student_id)
                    ->whereHas('fees_paid', fn($q) => $q->where('fees_id', $fees->id))
                    ->whereNull('deleted_at')
                    ->where('status', CompulsoryFee::CHEQUE_SUCCESS)
                    ->sum('due_charges');

                $pending_base = max(0, $total_fee - $paid_base);
                $pending_due_charges = max(0, $total_due_charges - $paid_due_charges);
                $max_payable = $pending_base + $pending_due_charges;

                if ($total_available > ($max_payable + 0.01)) {
                    throw new \Exception("Overpayment not allowed. Max Payable: " . number_format($max_payable, 2));
                }

                $allocate_due_charges = 0;
                $allocate_base = 0;

                if ($total_available >= $pending_due_charges) {
                    $allocate_due_charges = $pending_due_charges;
                    $total_available -= $pending_due_charges;
                } else {
                    $allocate_due_charges = $total_available;
                    $total_available = 0;
                }

                if ($total_available > 0) {
                    $allocate_base = min($total_available, $pending_base);
                    $total_available -= $allocate_base;
                }

                $txnStatus = $request->mode == 2
                    ? ($chequeStatusMap[$request->status] ?? CompulsoryFee::CHEQUE_PENDING)
                    : CompulsoryFee::CHEQUE_SUCCESS;

                $paymentTransaction = PaymentTransaction::create([
                    'user_id' => $student_id,
                    'amount' => $allocate_base + $allocate_due_charges,
                    'payment_gateway' => $request->mode == 2 ? 'cheque' : 'cash',
                    'payment_status' => $txnStatus === CompulsoryFee::CHEQUE_SUCCESS ? 'succeed' : ($txnStatus === CompulsoryFee::CHEQUE_PENDING ? 'pending' : 'failed'),
                    'school_id' => $fees->school_id
                ]);

                $this->compulsoryFee->create([
                    'student_id'   => $student_id,
                    'fees_paid_id' => $feesPaid->id,
                    'type'         => 1,
                    'mode'         => $request->mode,
                    'cheque_no'    => $request->mode == 2 ? $request->cheque_no : null,
                    'amount'       => $allocate_base,
                    'due_charges'  => $allocate_due_charges,
                    'date'         => date('Y-m-d', strtotime($request->date)),
                    'status'       => $txnStatus,
                    'notes'        => $request->note,
                    'school_id'    => $fees->school_id,
                    'payment_transaction_id' => $paymentTransaction->id
                ]);
            }

            // 5. Recalculate confirmed amount and is_fully_paid on fees_paid summary
            $total_paid = $this->compulsoryFee->builder()
                ->where('student_id', $student_id)
                ->whereHas('fees_paid', fn($q) => $q->where('fees_id', $fees->id))
                ->whereNull('deleted_at')
                ->where('status', CompulsoryFee::CHEQUE_SUCCESS)
                ->sum('amount');

            $paidInstallmentsCount = $this->compulsoryFee->builder()
                ->where('student_id', $student_id)
                ->whereHas('fees_paid', fn($q) => $q->where('fees_id', $fees->id))
                ->whereNull('deleted_at')
                ->where('status', CompulsoryFee::CHEQUE_SUCCESS)
                ->whereNotNull('installment_id')
                ->pluck('installment_id')
                ->unique()
                ->count();

            $totalInstallmentsCount = count($fees->installments);
            $net_total_fee = max(0, $fees->total_compulsory_fees - $totalCompulsoryDiscount);

            if ($totalInstallmentsCount > 0) {
                $is_fully_paid = ($paidInstallmentsCount >= $totalInstallmentsCount && $total_paid >= ($net_total_fee - 0.01));
            } else {
                $is_fully_paid = ($total_paid >= ($net_total_fee - 0.01));
            }

            $feesPaid->update([
                'amount'        => $total_paid,
                'is_fully_paid' => $is_fully_paid ? 1 : 0
            ]);

            DB::commit();

            if ($request->status == 'success') {
                // Send notification to guardian
                $student = $this->student->builder()->where('user_id', $student_id)->first();
                $currencySymbol = $this->cache->getSchoolSettings('currency_symbol');
                $user[] = $student->guardian_id;
                if ($user) {
                    $paymentType = 'Compulsory Fees Payment';
                    $title = 'Fees Payment Successful';
                    $body = "Your payment of " . $currencySymbol . number_format($input_amount, 2) . " for " . $paymentType . " was successful.";
                    send_notification($user, $title, $body, "payment", ['_skip_whatsapp' => true]);
                }



                // Communication Center: WhatsApp fee receipt to the guardian
                $this->whatsAppService->sendFeePaidWhatsApp($request->fees_id, $request->student_id, $request->amount);
            }

            ResponseService::successResponse("Compulsory Fees Paid Successfully");
        } catch (\Throwable $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, 'FeesController -> payCompulsoryFeesStore method');
            ResponseService::errorResponse($e->getMessage());
        }
    }
    public function payOptionalFeesIndex($feesID, $studentID)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        //        ResponseService::noPermissionThenRedirect('fees-edit');
        // $fees = $this->fees->findById($feesID, ['*'], ['fees_class_type.fees_type:id,name', 'installments:id,name,due_date,due_charges,fees_id']);
        $sessionYear = $this->cache->getSessionYear();
        $defaultSessionYear = $this->cache->getDefaultSessionYear();

        $fees = $this->fees->findById($feesID, ['*'], ['fees_class_type.fees_type:id,name', 'installments:id,name,due_date,due_charges,fees_id']);

        if ($sessionYear->id == $defaultSessionYear->id) {
            $student = $this->user->builder()->role('Student')->select('id', 'first_name', 'last_name')
                ->with([
                    'student' => function ($query) {
                        $query->select('id', 'class_section_id', 'user_id', 'session_year_id')->with([
                            'class_section' => function ($query) {
                                $query->select('id', 'class_id', 'section_id', 'medium_id')->with('class:id,name,shift_id,stream_id', 'class.shift:id,name', 'section:id,name', 'medium:id,name', 'class.stream');
                            }
                        ]);
                    },
                    'fees_paid' => function ($q) use ($feesID) {
                        $q->where('fees_id', $feesID)->first();
                    }
                ])->findOrFail($studentID);
        } else {
            // Get from promote student
            $promoteStudent = PromoteStudent::where('student_id', $studentID)
                ->with([
                    'student' => function ($query) use ($feesID, $sessionYear) {
                        $query->select('id', 'class_section_id', 'user_id', 'session_year_id')->with([
                            'class_section' => function ($query) {
                                $query->select('id', 'class_id', 'section_id', 'medium_id')->with('class:id,name,shift_id,stream_id', 'class.shift:id,name', 'section:id,name', 'medium:id,name', 'class.stream');
                            }
                        ])
                            ->with(['user.fees_paid' => function ($q) use ($feesID, $sessionYear) {
                                $q->where('fees_id', $feesID);
                            }]);
                    }
                ])->first();


            if (!$promoteStudent) {
                return redirect()->to('fees/paid')->with('error', 'Student Not Found');
            }

            $student = $promoteStudent->student->user;
        }

        $optionalFeesData = $this->feesClassType->builder()
            ->where('fees_id', $feesID)
            ->where(['class_id' => $student->student->class_section->class_id, 'optional' => 1])
            ->with([
                'fees_type',

                'optional_fees_paid' => function ($query) use ($student, $sessionYear) {
                    $query->where('student_id', $student->id)
                        ->whereHas('fees_paid', function ($subQuery1) use ($student, $sessionYear) {
                            $subQuery1->whereHas('fees', function ($subQuery2) use ($student, $sessionYear) {
                                $subQuery2->where('session_year_id', $sessionYear->id);
                            });
                        })
                        ->orderBy('id', 'DESC');
                }
            ])
            ->get();

        // Fetch optional discounts
        $discounts = \App\Models\FeeDiscount::where('student_id', $student->id)
            ->where('fees_id', $feesID)
            ->with('items')
            ->get();

        $optionalDiscounts = [];
        foreach ($discounts as $discount) {
            foreach ($discount->items as $item) {
                if ($item->fee_type === 'optional') {
                    $optionalDiscounts[$item->fee_type_id] = ($optionalDiscounts[$item->fee_type_id] ?? 0) + $item->discount_amount;
                }
            }
        }

        $optionalFeesData->map(function ($optionalFee) use ($optionalDiscounts) {
            $discountAmount = $optionalDiscounts[$optionalFee->id] ?? 0;
            $optionalFee->discount_amount = $discountAmount;
            $optionalFee->net_amount = max(0, $optionalFee->amount - $discountAmount);
            return $optionalFee;
        });

        $currencySymbol = $this->cache->getSchoolSettings('currency_symbol');

        return view('fees.pay-optional', compact('fees', 'student', 'optionalFeesData', 'currencySymbol'));
    }

    public function payOptionalFeesStore(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-paid');

        $chequeStatusMap = [
            'pending' => OptionalFee::CHEQUE_PENDING,
            'success' => OptionalFee::CHEQUE_SUCCESS,
            'bounced' => OptionalFee::CHEQUE_FAILED,
        ];

        $request->validate([
            'fees_id' => 'required|numeric',
            'student_id' => 'required|numeric',
            'status' => 'nullable|in:pending,success,bounced',
            'rejection_reason' => 'required_if:status,bounced|nullable|string|max:500',
        ]);
        try {
            DB::beginTransaction();

            // First Store in Fees Paid table to get Fees Paid ID
            $feesPaid = $this->feesPaid->builder()->where([
                'fees_id' => $request->fees_id,
                'student_id' => $request->student_id
            ])->first();

            // If Fees Paid Doesn't Exists
            if (empty($feesPaid)) {

                $feesPaidResult = $this->feesPaid->create([
                    'date' => date('Y-m-d', strtotime($request->date)),
                    'is_fully_paid' => 0,
                    'is_used_installment' => 0,
                    'fees_id' => $request->fees_id,
                    'student_id' => $request->student_id,
                    'amount' => $request->total_amount,
                ]);
            } else {
                $feesPaidResult = $this->feesPaid->update($feesPaid->id, [
                    'amount' => $request->total_amount + $feesPaid->amount
                ]);
            }


            $optionalFeesPaymentData = array();

            // Several optional fees paid by one cheque are a single payment, so they all
            // share one payment transaction and are reconciled together afterwards.
            $sharedChequeTransaction = null;
            $sharedChequeAmount = 0;

            // Loop to the Optional Fees
            if (!empty($request->fees_class_type)) {
                foreach ($request->fees_class_type as $key => $feesClassType) {
                    if (isset($feesClassType['id'])) {


                        $existingTxn = $this->optionalFee->builder()
                            ->where('student_id', $request->student_id)
                            ->where('fees_class_id', $feesClassType['id'])
                            ->whereNull('deleted_at')
                            ->whereIn('status', [OptionalFee::CHEQUE_PENDING, OptionalFee::CHEQUE_SUCCESS])
                            ->exists();

                        if ($existingTxn) {
                            $fct = $this->feesClassType->findById($feesClassType['id']);
                            $feeName = $fct->fees_type->name ?? 'this optional fee';

                            DB::rollback();
                            return ResponseService::errorResponse(
                                "Payment already recorded for ."
                            );
                        }
                        // Enforce overpayment boundaries against net amounts
                        $fct = $this->feesClassType->findById($feesClassType['id']);
                        if (!$fct) {
                            throw new \Exception("Invalid optional fee component.");
                        }

                        $discountItemAmount = \App\Models\FeeDiscountItem::where('fee_type', 'optional')
                            ->where('fee_type_id', $feesClassType['id'])
                            ->whereHas('fee_discount', function ($q) use ($request) {
                                $q->where('student_id', $request->student_id)
                                    ->where('fees_id', $request->fees_id);
                            })->sum('discount_amount');

                        $net_amount = max(0, $fct->amount - $discountItemAmount);

                        if ($feesClassType['amount'] > ($net_amount + 0.01)) {
                            throw new \Exception("Overpayment not allowed. The net payable amount for optional component is " . $net_amount);
                        }

                        $txnStatus = $request->mode == 2
                            ? ($chequeStatusMap[$request->status] ?? OptionalFee::CHEQUE_PENDING)
                            : OptionalFee::CHEQUE_SUCCESS;

                        $paymentStatus = $txnStatus === OptionalFee::CHEQUE_SUCCESS ? 'succeed' : ($txnStatus === OptionalFee::CHEQUE_PENDING ? 'pending' : 'failed');

                        if ($request->mode == 2) {
                            // Reuse (and grow) the single transaction of this cheque
                            if (!$sharedChequeTransaction) {
                                $sharedChequeTransaction = PaymentTransaction::create([
                                    'user_id' => $request->student_id,
                                    'amount' => 0,
                                    'payment_gateway' => 'cheque',
                                    'payment_status' => $paymentStatus,
                                    'school_id' => Auth::user()->school_id ?? $request->school_id
                                ]);
                            }
                            $paymentTransaction = $sharedChequeTransaction;
                            $sharedChequeAmount += (float) $feesClassType['amount'];
                            $sharedChequeTransaction->update(['amount' => $sharedChequeAmount]);
                        } else {
                            $paymentTransaction = PaymentTransaction::create([
                                'user_id' => $request->student_id,
                                'amount' => $feesClassType['amount'],
                                'payment_gateway' => 'cash',
                                'payment_status' => $paymentStatus,
                                'school_id' => Auth::user()->school_id ?? $request->school_id
                            ]);
                        }

                        $optionalFeesPaymentData[] = array(
                            'student_id' => $request->student_id,
                            'class_id' => $request->class_id,
                            'fees_class_id' => $feesClassType['id'],
                            'mode' => $request->mode,
                            'cheque_no' => $request->mode == 2 ? $request->cheque_no : null,
                            'amount' => $feesClassType['amount'],
                            'fees_paid_id' => $feesPaidResult->id,
                            'date' => date('Y-m-d', strtotime($request->date)),
                            // 'status' => "Success",
                            'status' => $txnStatus,

                            'notes' => $request->note,
                            'reason'       => ($request->status === 'bounced') ? $request->rejection_reason : null,
                            'payment_transaction_id' => $paymentTransaction->id,
                            'created_at' => now(),
                            'updated_at' => now()
                        );
                    }
                }
            }

            $this->optionalFee->createBulk($optionalFeesPaymentData);

            DB::commit();
            // Send rejection notification to parent when cheque bounces
            if ($request->mode == 2 && $request->status === 'bounced') {
                $student = $this->student->builder()->where('user_id', $request->student_id)->first();
                $currencySymbol = $this->cache->getSchoolSettings('currency_symbol');
                $notifyUsers = [$request->student_id];
                if ($student && !empty($student->guardian_id)) {
                    $notifyUsers[] = $student->guardian_id;
                }

                $reason = $request->rejection_reason ?? 'No reason provided';
                $title  = 'Cheque Payment Bounced';
                $body   = 'Your cheque payment of ' . $currencySymbol . number_format($request->total_amount, 2)
                    . ' for Optional Fees was bounced. Reason: ' . $reason;

                send_notification($notifyUsers, $title, $body, 'payment');
            }

            // Communication Center: WhatsApp fee receipt to the guardian
            $this->whatsAppService->sendFeePaidWhatsApp($request->fees_id, $request->student_id, $request->amount ?? '');

            $student = $this->student->builder()->where('user_id', $request->student_id)->first();
            $currencySymbol = $this->cache->getSchoolSettings('currency_symbol');
            $user[] = $student->guardian_id;
            if ($user) {
                // Get fees name safely
                $paymentType = 'Optional Fees Payment';
                $title = 'Fees Payment Successful';
                $body = "Your payment of " . $currencySymbol . number_format($request->total_amount, 2) . " for " . $paymentType . " was successful.";
                $type = "payment";

                // WhatsApp fee_paid is delivered by sendFeePaidWhatsApp() (with the receipt PDF),
                // so tell the central bridge to skip it here and avoid a duplicate message.
                send_notification($user, $title, $body, $type, ['_skip_whatsapp' => true]);
            }


            ResponseService::successResponse("Data Updated SuccessFully");
        } catch (Throwable $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, 'FeesController -> payOptionalFeesStore method ');
            ResponseService::errorResponse($e->getMessage());
        }
    }
    /* END : Fees Paid Module */

    public function optionalFees(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-paid');

        $sessionYear = $this->cache->getSessionYear();
        $class_section = $this->classSection->builder()->with('class', 'class.stream', 'class.shift', 'section', 'medium')->get();
        $feesClassTypes = FeesClassType::where('optional', '=', 1, 'and')
            ->whereHas('fees', function ($query) use ($sessionYear) {
                $query->where('session_year_id', $sessionYear->id);
            })
            ->with([
                'fees_type' => function ($query) {
                    $query->select('id', 'name');
                }
            ])->get();

        return view('fees.optional-fees', compact('class_section', 'feesClassTypes'));
    }



    public function optionalFeesList(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-paid');
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $filter_optional_fees = (int) request('filter_optional_fees');
        $class_section_id = request('class_section_id');
        $sessionYear = $this->cache->getSessionYear();
        $defaultSessionYear = $this->cache->getDefaultSessionYear();

        if ($sessionYear->id == $defaultSessionYear->id) {
            $sql = $this->user->builder()
                ->role('Student')
                ->select('id', 'first_name', 'last_name', 'email', 'image')
                ->with([
                    'optional_fees' => function ($query) use ($filter_optional_fees) {
                        $query->when($filter_optional_fees, function ($q) use ($filter_optional_fees) {
                            $q->where('fees_class_id', $filter_optional_fees);
                        })->orderBy('id', 'DESC')->with([
                            'fees_class_type' => function ($query) {
                                $query->where(['optional' => 1]);
                            },
                            'file'
                        ]);
                    },
                    'fees_paid' => function ($q) use ($request, $sessionYear) {
                        $q->withSum('optional_fee', 'amount')->whereHas('fees', function ($q) use ($sessionYear) {
                            $q->where('session_year_id', $sessionYear->id);
                        });
                    }
                ])
                ->whereHas('optional_fees', function ($query) use ($filter_optional_fees) {
                    $query->when($filter_optional_fees, function ($q) use ($filter_optional_fees) {
                        $q->where('fees_class_id', $filter_optional_fees);
                    })->with([
                        'fees_class_type' => function ($query) {
                            $query->where('optional', 1);
                        }
                    ]);
                })
                ->whereHas('fees_paid.fees', function ($q) use ($sessionYear) {
                    $q->where('session_year_id', $sessionYear->id);
                })
                ->when($class_section_id, function ($query) use ($class_section_id) {
                    $query->whereHas('student.class_section', function ($q) use ($class_section_id) {
                        $q->where('id', $class_section_id);
                    });
                });
        } else {
            // Promote table
            $sql = $this->user->builder()
                ->role('Student')
                ->whereHas('promote_student', function ($q) use ($sessionYear, $class_section_id) {
                    $q->where('session_year_id', $sessionYear->id)->where('class_section_id', $class_section_id);
                })
                ->select('id', 'first_name', 'last_name', 'email', 'image')
                ->with([
                    'optional_fees' => function ($query) use ($filter_optional_fees) {
                        $query->when($filter_optional_fees, function ($q) use ($filter_optional_fees) {
                            $q->where('fees_class_id', $filter_optional_fees);
                        })->orderBy('id', 'DESC')->with([
                            'fees_class_type' => function ($query) {
                                $query->where(['optional' => 1]);
                            },
                            'file'
                        ]);
                    },
                    'fees_paid' => function ($q) use ($request, $sessionYear) {
                        $q->withSum('optional_fee', 'amount');
                    }
                ])
                ->whereHas('optional_fees', function ($query) use ($filter_optional_fees) {
                    $query->when($filter_optional_fees, function ($q) use ($filter_optional_fees) {
                        $q->where('fees_class_id', $filter_optional_fees);
                    })->with([
                        'fees_class_type' => function ($query) {
                            $query->where('optional', 1);
                        }
                    ]);
                })
                ->whereHas('fees_paid.fees', function ($q) use ($sessionYear) {
                    $q->where('session_year_id', $sessionYear->id);
                });
        }

        if (!empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%$search%")->orWhere('first_name', 'LIKE', "%$search%")->orWhere('last_name', 'LIKE', "%$search%")
                    ->orWhereHas('optional_fees', function ($q) use ($search) {
                        $q->where('cheque_no', 'LIKE', "%$search%");
                    });
            });
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
        $no = 1;

        foreach ($res as $row) {
            $tempRow = $row->toArray();
            $fees_data['no'] = $no++;
            $tempRow['no'] = $fees_data;

            if (!empty($row->fees_paid)) {
                $tempRow['fees_status'] = $row->fees_paid->is_fully_paid;
            }

            // ALWAYS pick the LATEST transaction (sorted by id DESC)
            if (!empty($row->optional_fees) && count($row->optional_fees) > 0) {
                $latestOptionalFee = $row->optional_fees->sortByDesc('id')->first();
                $tempRow['optional_fees_amount'] = $latestOptionalFee->amount ?? 0;
                $tempRow['payment_method'] = $latestOptionalFee->mode ?? null;
                $tempRow['status'] = $latestOptionalFee->status ?? null;
                if (!empty($latestOptionalFee->date)) {
                    $tempRow['fees_paid'] = ['date' => $latestOptionalFee->date];
                }
            } else {
                $tempRow['optional_fees_amount'] = 0;
            }

            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function feesOverDue($class_section_id)
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-paid');

        try {
            // $sessionYear = $this->cache->getDefaultSessionYear();
            $class_id = $this->classSection->builder()->where('id', $class_section_id)->pluck('class_id')->toArray();

            // Ensure $class_id is a single value rather than an array if you expect a single class_id
            $class_id = reset($class_id);

            $today = Carbon::now()->format('Y-m-d');
            $student_ids = [];


            $sessionYearId = $this->cache->getSessionYear()->id;
            $fees = $this->fees->builder()->where('session_year_id', $sessionYearId)->whereDate('due_date', '<', $today)->with('installments:id,name,due_date,due_charges,fees_id')->where('class_id', $class_id)->get();

            foreach ($fees as $fee) {
                $sql = $this->user->builder()
                    ->role('Student')
                    ->select('id', 'first_name', 'last_name')->where('status', 1)
                    ->with([
                        'fees_paids' => function ($query) use ($fee) {
                            $query->where('fees_id', $fee->id);
                        },
                    ])->whereDoesntHave('fees_paids', function ($q) use ($fee) {
                        $q->where('fees_id', $fee->id);
                    })->orwhereHas('fees_paids', function ($query) use ($fee, $today) {
                        $query->where('fees_id', $fee->id)->where('is_fully_paid', 0)
                            ->where(function ($q) use ($fee, $today) {
                                $q->where('is_used_installment', true)
                                    ->whereHas('fees', function ($q) use ($today) {
                                        $q->whereHas('installments', function ($q) use ($today) {
                                            $q->whereDate('due_date', '<', $today);
                                        });
                                    });
                            });
                    });
                $student_ids = array_merge($student_ids, $sql->whereHas('student', function ($q) use ($sessionYearId) {
                    $q->where('session_year_id', $sessionYearId);
                })->get()->pluck('id')->toArray());
            }
            $student_ids = array_unique($student_ids);

            $students = $this->student->builder()->with('guardian')->whereIn('user_id', $student_ids)->where('class_section_id', $class_section_id)
                ->whereHas('user', function ($query) {
                    $query->where('status', 1);
                })->with([
                    'user',
                    'user.fees_paids',
                    'class_section' => function ($query) {
                        $query->select('id', 'class_id', 'section_id', 'medium_id')->with('class:id,name,shift_id', 'class.shift:id,name', 'section:id,name', 'medium:id,name');
                    }
                ])->get();

            // $guardian_ids = $students->pluck('guardian_id')->toArray();

            // // send notification to guardians
            // $title = "Overdue Fees";
            // $body = "Dear Guardian, the fees for your ward are overdue. Please make the necessary payment at the earliest.";
            // $type = 'Notification';

            // // Send the notification to the guardians
            // send_notification($guardian_ids, $title, $body, $type);

            ResponseService::successResponse("Data Fetched SuccessFully", $students);
        } catch (Throwable $e) {
            if (
                Str::contains($e->getMessage(), [
                    'does not exist',
                    'file_get_contents'
                ])
            ) {
                DB::commit();
                ResponseService::warningResponse("Data Stored successfully. But App push notification not send.");
            } else {
                DB::rollback();
                ResponseService::logErrorResponse($e, 'FeesController -> feesOverDue method ');
                ResponseService::errorResponse();
            }
        }
    }

    public function studentAccountDeactivate(Request $request)
    {
        try {
            // Retrieve the IDs from the request
            $checkedIds = explode(',', $request->checked_ids);
            $users = $this->user->builder()->whereIn('id', $checkedIds)->get();
            // dd($users);
            foreach ($users as $user) {
                $user->status = 0;
                $user->update();
            }

            ResponseService::successResponse("Students Deactived Account Successfully.");
        } catch (\Throwable $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, 'FeesController -> studentAccountDeactivate method ');
            ResponseService::errorResponse();
        }
    }



    /**
     * Build the query that returns every compulsory fee row paid by the same payment.
     *
     * One cheque can cover several installments: those rows are written in a single
     * payCompulsoryFeesStore call and share the cheque number, the collection date and
     * the fees paid record. A manual payment from the parent app carries no cheque
     * number, so its installments are grouped by the payment transaction they were
     * submitted under. Cash and online payments keep the one-record-at-a-time behaviour.
     */
    private function chequeGroupBuilder(CompulsoryFee $fee)
    {
        $builder = CompulsoryFee::whereNull('deleted_at')
            ->where('student_id', $fee->student_id)
            ->where('mode', $fee->mode)
            ->where('status', $fee->status);

        $chequeDate = $fee->getRawOriginal('date');

        if ($fee->mode === 'Manual') {
            return !empty($fee->payment_transaction_id)
                ? $builder->where('payment_transaction_id', $fee->payment_transaction_id)
                : $builder->where('id', $fee->id);
        }

        if ($fee->mode !== 'Cheque' || empty($fee->cheque_no) || empty($chequeDate)) {
            return $builder->where('id', $fee->id);
        }

        $builder->where('cheque_no', $fee->cheque_no)
            ->whereDate('date', date('Y-m-d', strtotime($chequeDate)));

        if ($fee->fees_paid_id) {
            $builder->where('fees_paid_id', $fee->fees_paid_id);
        } else {
            $builder->whereNull('fees_paid_id');
        }

        return $builder;
    }

    /**
     * Full detail of a cheque payment (and every fee/installment it covers) for the
     * cheque status update modal.
     */
    public function chequeGroupDetails($id)
    {
        ResponseService::noFeatureThenSendJson('Fees Management');
        ResponseService::noPermissionThenSendJson('fees-paid');
        try {
            $fee = CompulsoryFee::with('fees_paid.fees', 'payment_transaction.file')->findOrFail($id);

            $group = $this->chequeGroupBuilder($fee)
                ->with(['installment_fee:id,name,due_date,fees_id', 'file'])
                ->orderBy('id')
                ->get();

            $student = $this->user->builder()->select('id', 'first_name', 'last_name')
                ->with([
                    'student' => function ($q) {
                        $q->select('id', 'user_id', 'class_section_id', 'guardian_id')
                            ->with([
                                'class_section' => function ($q) {
                                    $q->select('id', 'class_id', 'section_id', 'medium_id')
                                        ->with('class:id,name,stream_id,shift_id', 'class.stream:id,name', 'class.shift:id,name', 'section:id,name', 'medium:id,name');
                                },
                                'guardian:id,first_name,last_name'
                            ]);
                    }
                ])->find($fee->student_id);

            $statusMap = [
                CompulsoryFee::CHEQUE_SUCCESS => 'success',
                CompulsoryFee::CHEQUE_FAILED  => 'bounced',
                CompulsoryFee::CHEQUE_PENDING => 'pending',
            ];

            $parentFee = $fee->fees_paid->fees ?? null;
            $feeDueDate = $parentFee ? $parentFee->getRawOriginal('due_date') : null;

            $items = $group->map(function ($row) use ($feeDueDate) {
                $dueDate = $row->installment_fee
                    ? $row->installment_fee->getRawOriginal('due_date')
                    : $feeDueDate;

                return [
                    'id'          => $row->id,
                    'name'        => $row->installment_fee->name ?? __('full_payment'),
                    'due_date'    => $dueDate ? date('d-m-Y', strtotime($dueDate)) : '-',
                    'amount'      => (float) $row->amount,
                    'due_charges' => (float) $row->due_charges,
                    'total'       => (float) $row->amount + (float) $row->due_charges,
                ];
            })->values();

            ResponseService::successResponse('Data Fetched Successfully', [
                'ids'               => $group->pluck('id')->implode(','),
                'count'             => $group->count(),
                'mode'              => $fee->mode,
                'cheque_no'         => $fee->cheque_no,
                'cheque_date'       => $fee->getRawOriginal('date') ? date('d-m-Y', strtotime($fee->getRawOriginal('date'))) : '-',
                'status'            => $statusMap[$fee->status] ?? 'pending',
                'status_label'      => $fee->status,
                'note'              => $fee->notes,
                'reason'            => $fee->reason,
                'receipt_url'       => $fee->receipt_url ?? $fee->payment_transaction->receipt_url ?? null,
                'reference'         => $fee->payment_transaction->order_id ?? null,
                'student_name'      => $student->full_name ?? '',
                'parent_name'       => $student->student->guardian->full_name ?? null,
                'class_section'     => $student->student->class_section->full_name ?? '',
                'fee_name'          => $parentFee->name ?? '',
                'currency_symbol'   => $this->cache->getSchoolSettings('currency_symbol'),
                'total_amount'      => (float) $group->sum('amount'),
                'total_due_charges' => (float) $group->sum('due_charges'),
                'grand_total'       => (float) $group->sum('amount') + (float) $group->sum('due_charges'),
                'items'             => $items,
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, 'FeesController -> chequeGroupDetails');
            ResponseService::errorResponse($e->getMessage());
        }
    }

    public function updateChequeStatus(Request $request)
    {
        // Handle both single ID or comma-separated string (e.g., "101,102")
        $idInput = $request->id;
        if (is_string($idInput) && str_contains($idInput, ',')) {
            $ids = array_map('trim', explode(',', $idInput));
        } else {
            $ids = is_array($idInput) ? $idInput : [$idInput];
        }

        $request->merge(['id_list' => $ids]);

        $request->validate([
            'id_list'   => 'required|array',
            'id_list.*' => 'exists:compulsory_fees,id',
            'status'    => 'required|in:pending,success,bounced',
            'note'      => 'nullable|string',
            'reason'    => 'nullable|string|required_if:status,bounced',
        ]);

        try {
            DB::beginTransaction();

            $requestedFees = CompulsoryFee::whereIn('id', $ids)->get();

            if ($requestedFees->isEmpty()) {
                ResponseService::errorResponse('No payment records found.');
            }

            // A cheque is one payment even when it covers several installments. Expand
            // every requested record to all the fee rows paid by the same cheque so the
            // admin never has to update them one by one.
            $compulsoryFees = collect();
            foreach ($requestedFees as $requestedFee) {
                $compulsoryFees = $compulsoryFees->merge($this->chequeGroupBuilder($requestedFee)->get());
            }
            $compulsoryFees = $compulsoryFees->unique('id')->values();
            $ids = $compulsoryFees->pluck('id')->all();

            // Use the first record to extract mode and the note fallback
            $firstFee = $compulsoryFees->first();
            $isManual = ($firstFee->mode === 'Manual');

            switch ($request->status) {
                case 'success':
                    $dbStatus = CompulsoryFee::CHEQUE_SUCCESS;
                    break;
                case 'bounced':
                    $dbStatus = CompulsoryFee::CHEQUE_FAILED;
                    break;
                case 'pending':
                default:
                    $dbStatus = CompulsoryFee::CHEQUE_PENDING;
                    break;
            }

            // Update all target compulsory_fees records at once
            CompulsoryFee::whereIn('id', $ids)->update([
                'status' => $dbStatus,
                'notes'  => $request->filled('note') ? $request->note : $firstFee->notes,
                'reason' => ($request->status === 'bounced') ? $request->reason : null,
            ]);

            // Keep the linked payment transactions in sync with the cheque status
            $this->syncOfflinePaymentTransactions($compulsoryFees, $dbStatus);

            // Recalculate totals in fees_paid table for every affected fee
            foreach ($compulsoryFees->pluck('fees_paid_id')->filter()->unique() as $feesPaidId) {
                $this->recalculateFeesPaidSummary($feesPaidId);
            }

            DB::commit();

            // Notify each student/guardian once for the whole cheque payment
            foreach ($compulsoryFees->groupBy('student_id') as $studentId => $studentFees) {
                $studentDetails = Students::where('user_id', $studentId)->first();
                $notifyUsers = array_filter([$studentId, $studentDetails?->guardian_id]);
                $customData = [];

                if ($dbStatus === CompulsoryFee::CHEQUE_SUCCESS) {
                    $title = $isManual ? 'Manual Payment Approved' : 'Cheque is cleared';
                    $body  = $isManual
                        ? 'Your manual payment was approved successfully.'
                        : 'Your cheque payment is completed successfully.';

                    // Send WhatsApp Fee Receipt for total amount
                    foreach ($studentFees->groupBy('fees_paid_id') as $feesPaidId => $feesPaidFees) {
                        $feesId = $feesPaidFees->first()->fees_paid->fees_id ?? null;
                        if ($feesId && isset($this->whatsAppService)) {
                            $this->whatsAppService->sendFeePaidWhatsApp($feesId, $studentId, $feesPaidFees->sum('amount'));
                        }
                    }
                } elseif ($dbStatus === CompulsoryFee::CHEQUE_FAILED) {
                    $title = $isManual ? 'Manual Payment Rejected' : 'Cheque is Bounced';
                    $reasonText = !empty($request->reason) ? ' Reason: ' . $request->reason : '';

                    $body = $isManual
                        ? 'Your manual payment was rejected.' . $reasonText
                        : 'Your cheque payment for fee has bounced.' . $reasonText;

                    $customData = ['reason' => $request->reason];
                } else {
                    continue; // Back to pending : nothing to announce
                }

                send_notification($notifyUsers, $title, $body, 'payment', $customData);
            }

            ResponseService::successResponse('Payment status updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'FeesController -> updateChequeStatus');
            ResponseService::errorResponse($e->getMessage());
        }
    }

    /**
     * Mirror the cheque/manual status onto the payment transactions of the given fee rows.
     * Rows sharing one payment transaction (a single cheque covering many installments or
     * several optional fees) are summed into that transaction instead of duplicating it.
     */
    private function syncOfflinePaymentTransactions($fees, $dbStatus, $model = CompulsoryFee::class)
    {
        $paymentStatus = match ($dbStatus) {
            CompulsoryFee::CHEQUE_SUCCESS => 'succeed',
            CompulsoryFee::CHEQUE_FAILED  => 'failed',
            default                       => 'pending',
        };

        foreach ($fees->groupBy('payment_transaction_id') as $transactionId => $rows) {
            $gateway = $rows->first()->mode === 'Manual' ? 'manual' : 'cheque';
            $amount  = $rows->sum(fn($row) => (float) $row->amount + (float) ($row->due_charges ?? 0));

            if (!empty($transactionId)) {
                PaymentTransaction::where('id', $transactionId)->update([
                    'amount'         => $amount,
                    'payment_status' => $paymentStatus,
                ]);
                continue;
            }

            // Legacy rows without a transaction : create one for the whole cheque
            $paymentTransaction = PaymentTransaction::create([
                'user_id'         => $rows->first()->student_id,
                'amount'          => $amount,
                'payment_gateway' => $gateway,
                'payment_status'  => $paymentStatus,
                'school_id'       => $rows->first()->school_id,
            ]);

            $model::whereIn('id', $rows->pluck('id'))->update([
                'payment_transaction_id' => $paymentTransaction->id,
            ]);
        }
    }

    /**
     * Recalculate the collected amount and the fully paid flag of a fees_paid record.
     */
    private function recalculateFeesPaidSummary($feesPaidId)
    {
        $feesPaid = FeesPaid::find($feesPaidId);
        if (!$feesPaid) {
            return;
        }

        $totalPaid = CompulsoryFee::where('fees_paid_id', $feesPaid->id)
            ->whereNull('deleted_at')
            ->where('status', CompulsoryFee::CHEQUE_SUCCESS)
            ->sum('amount');

        $fee = Fee::with(['installments', 'fees_class_type'])->find($feesPaid->fees_id);
        if (!$fee) {
            return;
        }

        $discounts = FeeDiscount::where('student_id', $feesPaid->student_id)
            ->where('fees_id', $feesPaid->fees_id)
            ->with('items')
            ->get();

        $totalInstallmentsCount = $fee->installments ? count($fee->installments) : 0;

        if ($totalInstallmentsCount > 0) {
            $installmentDiscounts = [];
            foreach ($discounts as $discount) {
                foreach ($discount->items as $item) {
                    if ($item->fee_type === 'installment') {
                        $installmentDiscounts[$item->fee_type_id] = ($installmentDiscounts[$item->fee_type_id] ?? 0) + $item->discount_amount;
                    }
                }
            }
            $totalCompulsoryDiscount = array_sum($installmentDiscounts);
        } else {
            $totalCompulsoryDiscount = $discounts->whereIn('discount_scope', ['compulsory', 'entire'])->sum('discount_value');
        }

        $netTotal = max(0, ($fee->total_compulsory_fees ?? 0) - $totalCompulsoryDiscount);

        $paidInstallmentsCount = CompulsoryFee::where('fees_paid_id', $feesPaid->id)
            ->whereNull('deleted_at')
            ->where('status', CompulsoryFee::CHEQUE_SUCCESS)
            ->whereNotNull('installment_id')
            ->pluck('installment_id')
            ->unique()
            ->count();

        $isFullyPaid = 0;
        if ($netTotal > 0) {
            if ($totalInstallmentsCount > 0) {
                $isFullyPaid = ($paidInstallmentsCount >= $totalInstallmentsCount && $totalPaid >= ($netTotal - 0.01)) ? 1 : 0;
            } else {
                $isFullyPaid = ($totalPaid >= ($netTotal - 0.01)) ? 1 : 0;
            }
        }

        $feesPaid->update([
            'amount'        => $totalPaid,
            'is_fully_paid' => $isFullyPaid,
        ]);
    }
    /**
     * Build the query that returns every optional fee paid by the same payment.
     * Mirrors chequeGroupBuilder() so compulsory and optional fees reconcile the same
     * way : cheques group by cheque number, manual payments by payment transaction.
     */
    private function optionalChequeGroupBuilder(OptionalFee $fee)
    {
        $builder = OptionalFee::whereNull('deleted_at')
            ->where('student_id', $fee->student_id)
            ->where('mode', $fee->mode)
            ->where('status', $fee->status);

        $chequeDate = $fee->getRawOriginal('date');

        if ($fee->mode === 'Manual') {
            return !empty($fee->payment_transaction_id)
                ? $builder->where('payment_transaction_id', $fee->payment_transaction_id)
                : $builder->where('id', $fee->id);
        }

        if ($fee->mode !== 'Cheque' || empty($fee->cheque_no) || empty($chequeDate)) {
            return $builder->where('id', $fee->id);
        }

        $builder->where('cheque_no', $fee->cheque_no)
            ->whereDate('date', date('Y-m-d', strtotime($chequeDate)));

        if ($fee->fees_paid_id) {
            $builder->where('fees_paid_id', $fee->fees_paid_id);
        } else {
            $builder->whereNull('fees_paid_id');
        }

        return $builder;
    }

    /**
     * Full detail of an optional fee cheque payment (and every fee it covers)
     * for the cheque status update modal.
     */
    public function optionalChequeGroupDetails($id)
    {
        ResponseService::noFeatureThenSendJson('Fees Management');
        ResponseService::noPermissionThenSendJson('fees-paid');
        try {
            $fee = OptionalFee::with('fees_paid.fees', 'payment_transaction.file')->findOrFail($id);

            $group = $this->optionalChequeGroupBuilder($fee)
                ->with(['fees_class_type.fees_type:id,name', 'file'])
                ->orderBy('id')
                ->get();

            $student = $this->user->builder()->select('id', 'first_name', 'last_name')
                ->with([
                    'student' => function ($q) {
                        $q->select('id', 'user_id', 'class_section_id', 'guardian_id')
                            ->with([
                                'class_section' => function ($q) {
                                    $q->select('id', 'class_id', 'section_id', 'medium_id')
                                        ->with('class:id,name,stream_id,shift_id', 'class.stream:id,name', 'class.shift:id,name', 'section:id,name', 'medium:id,name');
                                },
                                'guardian:id,first_name,last_name'
                            ]);
                    }
                ])->find($fee->student_id);

            $statusMap = [
                OptionalFee::CHEQUE_SUCCESS => 'success',
                OptionalFee::CHEQUE_FAILED  => 'bounced',
                OptionalFee::CHEQUE_PENDING => 'pending',
            ];

            $items = $group->map(function ($row) {
                return [
                    'id'          => $row->id,
                    'name'        => $row->fees_class_type->fees_type->name ?? __('optional_fees'),
                    'due_date'    => null,
                    'status'      => $row->status,
                    'amount'      => (float) $row->amount,
                    'due_charges' => 0,
                    'total'       => (float) $row->amount,
                ];
            })->values();

            ResponseService::successResponse('Data Fetched Successfully', [
                'ids'               => $group->pluck('id')->implode(','),
                'count'             => $group->count(),
                'mode'              => $fee->mode,
                'cheque_no'         => $fee->cheque_no,
                'cheque_date'       => $fee->getRawOriginal('date') ? date('d-m-Y', strtotime($fee->getRawOriginal('date'))) : '-',
                'status'            => $statusMap[$fee->status] ?? 'pending',
                'status_label'      => $fee->status,
                'note'              => $fee->notes,
                'reason'            => $fee->reason,
                'receipt_url'       => $fee->receipt_url ?? $fee->payment_transaction->receipt_url ?? null,
                'reference'         => $fee->payment_transaction->order_id ?? null,
                'student_name'      => $student->full_name ?? '',
                'parent_name'       => $student->student->guardian->full_name ?? null,
                'class_section'     => $student->student->class_section->full_name ?? '',
                'fee_name'          => $fee->fees_paid->fees->name ?? __('optional_fees'),
                'currency_symbol'   => $this->cache->getSchoolSettings('currency_symbol'),
                'total_amount'      => (float) $group->sum('amount'),
                'total_due_charges' => 0,
                'grand_total'       => (float) $group->sum('amount'),
                'items'             => $items,
            ]);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e, 'FeesController -> optionalChequeGroupDetails');
            ResponseService::errorResponse($e->getMessage());
        }
    }

    public function updateOptionalChequeStatus(Request $request)
    {
        // Handle both single ID or comma-separated string (e.g., "101,102")
        $idInput = $request->id;
        if (is_string($idInput) && str_contains($idInput, ',')) {
            $ids = array_map('trim', explode(',', $idInput));
        } else {
            $ids = is_array($idInput) ? $idInput : [$idInput];
        }

        $request->merge(['id_list' => $ids]);

        $request->validate([
            'id_list'          => 'required|array',
            'id_list.*'        => 'exists:optional_fees,id',
            'status'           => 'required|in:pending,success,bounced',
            'note'             => 'nullable|string',
            'rejection_reason' => 'required_if:status,bounced|nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $requestedFees = OptionalFee::whereIn('id', $ids)->get();

            if ($requestedFees->isEmpty()) {
                ResponseService::errorResponse('No payment records found.');
            }

            // Several optional fees can be paid by one cheque : expand every requested
            // record to its whole cheque so the admin updates the status only once.
            $optionalFees = collect();
            foreach ($requestedFees as $requestedFee) {
                $optionalFees = $optionalFees->merge($this->optionalChequeGroupBuilder($requestedFee)->get());
            }
            $optionalFees = $optionalFees->unique('id')->values();
            $ids = $optionalFees->pluck('id')->all();

            $firstFee = $optionalFees->first();
            $isManual = ($firstFee->mode === 'Manual');

            switch ($request->status) {
                case 'success':
                    $dbStatus = OptionalFee::CHEQUE_SUCCESS;
                    break;
                case 'bounced':
                    $dbStatus = OptionalFee::CHEQUE_FAILED;
                    break;
                case 'pending':
                default:
                    $dbStatus = OptionalFee::CHEQUE_PENDING;
                    break;
            }

            OptionalFee::whereIn('id', $ids)->update([
                'status' => $dbStatus,
                'notes'  => $request->filled('note') ? $request->note : $firstFee->notes,
                'reason' => $request->status === 'bounced' ? $request->rejection_reason : null,
            ]);

            // Keep the linked payment transactions in sync with the cheque status
            $this->syncOfflinePaymentTransactions($optionalFees, $dbStatus, OptionalFee::class);

            // Recalculate total amount in fees_paid table for every affected fee
            foreach ($optionalFees->pluck('fees_paid_id')->filter()->unique() as $feesPaidId) {
                $feesPaid = FeesPaid::find($feesPaidId);
                if (!$feesPaid) {
                    continue;
                }

                $compulsorySuccess = CompulsoryFee::where('fees_paid_id', $feesPaid->id)
                    ->whereNull('deleted_at')
                    ->where('status', CompulsoryFee::CHEQUE_SUCCESS)
                    ->sum('amount');

                $optionalSuccess = OptionalFee::where('fees_paid_id', $feesPaid->id)
                    ->whereNull('deleted_at')
                    ->where('status', OptionalFee::CHEQUE_SUCCESS)
                    ->sum('amount');

                $feesPaid->update([
                    'amount' => $compulsorySuccess + $optionalSuccess,
                ]);
            }

            DB::commit();

            // Notify each student/guardian once for the whole cheque payment
            foreach ($optionalFees->groupBy('student_id') as $studentId => $studentFees) {
                $totalAmount = $studentFees->sum('amount');

                $studentDetails = Students::where('user_id', $studentId)->first();
                $notifyUsers = array_filter([$studentId, $studentDetails?->guardian_id]);

                if ($dbStatus === OptionalFee::CHEQUE_SUCCESS) {
                    $title = $isManual ? 'Manual Payment Approved' : 'Cheque Cleared';
                    $body  = $isManual
                        ? 'Your manual fee payment of ' . $totalAmount . ' has been approved successfully.'
                        : 'Your cheque payment of ' . $totalAmount . ' has been completed successfully.';
                } elseif ($dbStatus === OptionalFee::CHEQUE_FAILED) {
                    $reason = $request->rejection_reason ?? 'No reason provided';
                    $title  = $isManual ? 'Manual Payment Rejected' : 'Cheque Bounced';
                    $body   = $isManual
                        ? 'Your manual fee payment of ' . $totalAmount . ' was rejected. Reason: ' . $reason
                        : 'Your cheque payment of ' . $totalAmount . ' was not completed. Reason: ' . $reason;
                } else {
                    continue; // Back to pending : nothing to announce
                }

                send_notification($notifyUsers, $title, $body, 'payment');
            }

            return ResponseService::successResponse('Payment status updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            ResponseService::logErrorResponse($e, 'FeesController -> updateOptionalChequeStatus');

            return ResponseService::errorResponse($e->getMessage());
        }
    }
}
