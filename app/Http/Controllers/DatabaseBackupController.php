<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use App\Models\ClassGroup;
use App\Models\ExpenseCategory;
use App\Models\Faq;
use App\Models\FeesType;
use App\Models\File as ModelsFile;
use App\Models\FormField;
use App\Models\Grade;
use App\Models\Holiday;
use App\Models\Mediums;
use App\Models\PaymentTransaction;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Semester;
use App\Models\SessionYear;
use App\Models\Shift;
use App\Models\Slider;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Students;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;
use App\Repositories\DatabaseBackup\DatabaseBackupInterface;
use App\Services\BootstrapTableService;
use App\Services\ResponseService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class DatabaseBackupController extends Controller
{
    //

    private DatabaseBackupInterface $databaseBackup;
    private SubscriptionService $subscriptionService;

    public function __construct(DatabaseBackupInterface $databaseBackup, SubscriptionService $subscriptionService)
    {
        $this->databaseBackup = $databaseBackup;
        $this->subscriptionService = $subscriptionService;
    }

    public function index()
    {
        if ((Auth::user()->hasRole(['Super Admin', 'School Admin']) || Auth::user()->hasPermissionTo('database-backup'))) {
            $schoolId = Auth::user()->hasRole('School Admin') ? Auth::user()->school_id : Auth::user()->id;
            return view('database-backup.index', compact('schoolId'));
        } else {
            return redirect()->route('home')->with('error', "You Don't have enough permissions");
        }
    }

    /**
     * Helper method to clean up old backups to save disk space
     */
    private function cleanupOldBackups($schoolId = null)
    {
        try {
            $query = \App\Models\DatabaseBackup::query();
            if ($schoolId) {
                $query->where('school_id', $schoolId);
            } else {
                $query->whereNull('school_id');
            }

            $existingBackups = $query->get();

            foreach ($existingBackups as $existing) {
                // Delete physical zip file
                if ($existing->file_path && Storage::disk('public')->exists($existing->file_path)) {
                    Storage::disk('public')->delete($existing->file_path);
                }

                // Delete legacy static paths just in case they exist
                $folderIdentifier = $schoolId ?? Auth::user()->id;
                $sql_file = 'database-backup/' . $folderIdentifier . '/' . $existing->name . '.sql';
                $zip_file = 'database-backup/' . $folderIdentifier . '/' . $existing->name . '.zip';

                if (Storage::disk('public')->exists($sql_file)) {
                    Storage::disk('public')->delete($sql_file);
                }
                if (Storage::disk('public')->exists($zip_file)) {
                    Storage::disk('public')->delete($zip_file);
                }

                // Delete database record
                $existing->delete();
            }
        } catch (\Exception $e) {
            \Log::error("Failed to clean up old backups: " . $e->getMessage());
            // We don't abort here, so new backup can still proceed
        }
    }

    public function store()
    {
        ResponseService::noPermissionThenSendJson('database-backup');

        if (env('DEMO_MODE') == true) {
            ResponseService::errorResponse('Database backup is not allowed in demo mode');
        }

        $user_Id = Auth::user()->hasRole('School Admin') ? Auth::user()->school_id : Auth::user()->id;
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');

        if (Auth::user()->hasRole('School Admin')) {
            // Delete all previous backups for this school to save space
            $this->cleanupOldBackups($user_Id);

            $file_name = "school-backup-{$timestamp}.zip";
            $data = [
                'name' => $file_name,
                'school_id' => $user_Id,
                'status' => 'pending'
            ];
            $backupRecord = $this->databaseBackup->create($data);

            \App\Jobs\GenerateSchoolBackupJob::dispatch($user_Id, $backupRecord);
            ResponseService::successResponse('Backup started in background. Please wait until it completes.');
        } else if (Auth::user()->hasRole('Super Admin')) {
            // Delete all previous full system backups to save space
            $this->cleanupOldBackups(null);

            $file_name = "database_backup_{$user_Id}_{$timestamp}.zip";
            $data = [
                'name' => $file_name,
                'school_id' => null,
                'status' => 'pending'
            ];
            $backupRecord = $this->databaseBackup->create($data);

            \App\Jobs\GenerateSuperAdminBackupJob::dispatch($user_Id, $backupRecord);
            ResponseService::successResponse('Backup started in background. Please wait until it completes.');
        } else {
            ResponseService::errorResponse();
        }
    }

    public function download($id)
    {
        ResponseService::noPermissionThenRedirect('database-backup');
        $backup = $this->databaseBackup->findById($id);

        if (!$backup || $backup->status !== 'completed' || !$backup->file_path) {
            return redirect()->back()->with('error', 'Backup is not ready or does not exist.');
        }

        $filePath = storage_path('app/public/' . $backup->file_path);


        if (!file_exists($filePath)) {
            return redirect()->back()->with('error', 'File not found on server.');
        }

        return response()->download($filePath);
    }

    public function show()
    {
        ResponseService::noPermissionThenRedirect('database-backup');
        if (env('DEMO_MODE') == true) {
            ResponseService::errorResponse('Database backup is not allowed in demo mode');
        }
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');

        $sql = $this->databaseBackup->builder()
            ->where(function ($query) use ($search) {
                $query->when($search, function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('id', 'LIKE', "%$search%")->orwhere('title', 'LIKE', "%$search%")->orwhere('description', 'LIKE', "%$search%")->orwhere('date', 'LIKE', "%$search%");
                    });
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
        $no = 1;
        foreach ($res as $row) {
            $operate = '';
            if ($row->status === 'completed') {
                $operate .= BootstrapTableService::button('fa fa-download', url('database-backup/download', $row->id), ['btn-gradient-success'], ['title' => trans("download")]);
            }
            $operate .= BootstrapTableService::deleteButton(url('database-backup', $row->id));

            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['status'] = '<span class="badge badge-' . ($row->status === 'completed' ? 'success' : ($row->status === 'failed' ? 'danger' : 'warning')) . '">' . ucfirst($row->status) . '</span>';
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function destroy($id)
    {
        ResponseService::noPermissionThenSendJson('database-backup');
        if (env('DEMO_MODE') == true) {
            ResponseService::errorResponse('Database backup is not allowed in demo mode');
        }
        try {
            $databaseBackup = $this->databaseBackup->findById($id);

            $sql_file = 'database-backup/' . Auth::user()->school_id . '/' . $databaseBackup->name . '.sql';
            $zip_file = 'database-backup/' . Auth::user()->school_id . '/' . $databaseBackup->name . '.zip';


            if (Storage::disk('public')->exists($sql_file)) {
                Storage::disk('public')->delete($sql_file);
            }
            if (Storage::disk('public')->exists($zip_file)) {
                Storage::disk('public')->delete($zip_file);
            }

            if ($databaseBackup->file_path && Storage::disk('public')->exists($databaseBackup->file_path)) {
                Storage::disk('public')->delete($databaseBackup->file_path);
            }

            $this->databaseBackup->deleteById($id);
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "DatabaseBackup Controller -> Destroy Method");
            ResponseService::errorResponse();
        }
    }

    public function restore(Request $request)
    {
        if (env('DEMO_MODE') == true) {
            ResponseService::errorResponse('Database backup is not allowed in demo mode');
        }
        $validator = Validator::make($request->all(), [
            'zip_file' => 'required|mimes:zip',
        ]);

        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }

        try {
            $authId = Auth::user()->hasRole('School Admin') ? Auth::user()->school_id : Auth::user()->id;

            // Save the uploaded ZIP file to a temporary location
            $file = $request->file('zip_file');
            $timestamp = \Carbon\Carbon::now()->format('Y-m-d_H-i-s');
            $fileName = "restore_temp_{$authId}_{$timestamp}.zip";

            // Store the file safely before dispatching the job
            $filePath = $file->storeAs('public/restore_temp', $fileName);

            if (Auth::user()->hasRole('School Admin')) {
                \App\Jobs\RestoreSchoolBackupJob::dispatch($filePath, $authId);
            } else if (Auth::user()->hasRole('Super Admin')) {
                \App\Jobs\RestoreSuperAdminBackupJob::dispatch($filePath, $authId);
            }

            ResponseService::successResponse('Restore process has been started in the background. It may take some time depending on the backup size.');
        } catch (\Throwable $th) {
            ResponseService::logErrorResponse($th, "DatabaseBackup Controller -> Restore Method");
            ResponseService::errorResponse('Failed to start restore process.');
        }
    }

    public function getTableNameFromQuery($query)
    {
        if (preg_match('/CREATE TABLE `?(\w+)`?/', $query, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function deleteOldRecords($subscription)
    {
        $schoolId = $subscription->school_id;
        if ($subscription) {

            $guardian_ids = Students::where('school_id', $schoolId)->pluck('guardian_id')->toArray();

            Mediums::where('school_id', $schoolId)->delete();
            User::where('school_id', $schoolId)->whereNot('id', Auth::user()->id)->whereNot('id', $subscription->school->admin_id)->delete();
            User::whereIn('id', $guardian_ids)->delete();
            CertificateTemplate::where('school_id', $schoolId)->delete();
            ClassGroup::where('school_id', $schoolId)->delete();
            ExpenseCategory::where('school_id', $schoolId)->delete();
            Faq::where('school_id', $schoolId)->delete();
            FeesType::where('school_id', $schoolId)->delete();
            ModelsFile::where('school_id', $schoolId)->delete();
            FormField::where('school_id', $schoolId)->delete();
            SessionYear::where('school_id', $schoolId)->delete();
            Grade::where('school_id', $schoolId)->delete();
            Holiday::where('school_id', $schoolId)->delete();
            SchoolSetting::where('school_id', $schoolId)->delete();
            Section::where('school_id', $schoolId)->delete();
            Semester::where('school_id', $schoolId)->delete();
            Shift::where('school_id', $schoolId)->delete();
            Slider::where('school_id', $schoolId)->delete();
            Stream::where('school_id', $schoolId)->delete();
            Subscription::where('school_id', $schoolId)->delete();
            PaymentTransaction::where('school_id', $schoolId)->delete();
        }
    }
}
