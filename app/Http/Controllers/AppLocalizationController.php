<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\User;
use App\Repositories\Languages\LanguageInterface;
use App\Services\AppLocalizationService;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\ResponseService;
use App\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AppLocalizationController extends Controller
{
    private LanguageInterface $language;
    private CachingService $cache;

    public function __construct(LanguageInterface $language, CachingService $cachingService)
    {
        $this->language = $language;
        $this->cache = $cachingService;
    }

    public function index()
    {
        ResponseService::noPermissionThenRedirect('language-list');
        return view('super_admin.app-localization.index');
    }

    public function show()
    {
        ResponseService::noPermissionThenSendJson('language-list');
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');

        $sql = $this->language->builder()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")
                        ->orwhere('name', 'LIKE', "%$search%")
                        ->orwhere('code', 'LIKE', "%$search%");
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
            $operate = BootstrapTableService::menuEditButton('edit', route('app-localization.update', $row->id));
            if ($row->code != "en") {
                $operate .= BootstrapTableService::menuDeleteButton('delete', route('app-localization.destroy', $row->id));
            }

            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['files'] = [];
            foreach (AppLocalizationService::types() as $type) {
                $fileName = $row->{AppLocalizationService::column($type)};
                $tempRow['files'][$type] = [
                    'name'     => $fileName,
                    'download' => $fileName ? route('app-localization.file', [$row->id, $type]) : null,
                ];
            }
            $tempRow['operate'] = BootstrapTableService::menuItem($operate);
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function store(Request $request)
    {
        ResponseService::noPermissionThenSendJson('language-create');
        $request->validate([
            'name'             => 'required|string|max:191',
            'code'             => 'required|string|max:10|alpha_dash|unique:languages,code',
            'image'            => 'nullable|mimes:jpg,jpeg,png,svg|max:2048',
            'rtl'              => 'nullable|boolean',
            'student_app_file' => 'nullable|file|mimes:json|max:5120',
            'staff_app_file'   => 'nullable|file|mimes:json|max:5120',
            'web_file'         => 'nullable|file|mimes:json|max:5120',
            'panel_file'       => 'nullable|file|mimes:json|max:5120',
        ]);
        $this->validateJsonFiles($request);

        try {
            $code = strtolower(trim($request->code));
            $languageData = [
                'name'   => $request->name,
                'code'   => $code,
                'status' => 0,
                'is_rtl' => $request->rtl ?? 0,
            ];

            if ($request->hasFile('image')) {
                $languageData['image'] = UploadService::upload($request->file('image'), 'language');
            }

            foreach (AppLocalizationService::types() as $type) {
                if ($request->hasFile($this->requestKey($type))) {
                    $fileName = AppLocalizationService::storeFile($request->file($this->requestKey($type)), $code, $type);
                    $languageData[AppLocalizationService::column($type)] = $fileName;
                }
            }

            $this->language->create($languageData);
            $this->cache->removeSystemCache(config('constants.CACHE.SYSTEM.LANGUAGE'));
            Cache::forget('lang.js');
            ResponseService::successResponse('Data Stored Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "App Localization Controller -> Store method");
            ResponseService::errorResponse();
        }
    }

    public function update($id, Request $request)
    {
        ResponseService::noPermissionThenSendJson('language-edit');
        $request->validate([
            'name'             => 'required|string|max:191',
            'code'             => 'required|string|max:10|alpha_dash|unique:languages,code,' . $id,
            'image'            => 'nullable|mimes:jpg,jpeg,png,svg|max:2048',
            'rtl'              => 'nullable|boolean',
            'student_app_file' => 'nullable|file|mimes:json|max:5120',
            'staff_app_file'   => 'nullable|file|mimes:json|max:5120',
            'web_file'         => 'nullable|file|mimes:json|max:5120',
            'panel_file'       => 'nullable|file|mimes:json|max:5120',
        ]);
        $this->validateJsonFiles($request);

        try {
            $language = $this->language->findById($id);
            $code = strtolower(trim($request->code));

            $languageData = [
                'name'   => $request->name,
                'code'   => $code,
                'is_rtl' => $request->rtl ?? 0,
            ];

            // Keep stored file names in sync when the language code changes
            if ($code !== $language->code) {
                $languageData = array_merge($languageData, AppLocalizationService::renameFiles($language, $code));
            }

            if ($request->hasFile('image')) {
                UploadService::delete($language->raw_image);
                $languageData['image'] = UploadService::upload($request->file('image'), 'language');
            }

            foreach (AppLocalizationService::types() as $type) {
                if ($request->hasFile($this->requestKey($type))) {
                    AppLocalizationService::deleteFile($language, $type);
                    $fileName = AppLocalizationService::storeFile($request->file($this->requestKey($type)), $code, $type);
                    $languageData[AppLocalizationService::column($type)] = $fileName;
                }
            }

            $this->language->update($id, $languageData);
            $this->cache->removeSystemCache(config('constants.CACHE.SYSTEM.LANGUAGE'));
            Cache::forget('lang.js');
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "App Localization Controller -> Update method");
            ResponseService::errorResponse();
        }
    }

    public function destroy($id)
    {
        ResponseService::noPermissionThenSendJson('language-delete');
        try {
            $language = $this->language->findById($id);

            // Safety Check: English is the base language of the system
            if ($language->code == 'en') {
                ResponseService::errorResponse("The default English language cannot be deleted.");
            }

            // Safety Check: Prevent deletion of default language
            $defaultLanguage = SystemSetting::where('name', 'default_language')->first();
            if ($defaultLanguage && $defaultLanguage->data == $language->code) {
                ResponseService::errorResponse("Cannot delete the system default language.");
            }

            // Safety Check: Prevent deletion if language is assigned to any user

            if (User::where('language', $language->code)->exists()) {
                User::where('language', $language->code)->update(['language' => 'en']);
            }

            foreach (AppLocalizationService::types() as $type) {
                AppLocalizationService::deleteFile($language, $type);
            }
            UploadService::delete($language->raw_image);

            $this->language->deleteById($id);
            $this->cache->removeSystemCache(config('constants.CACHE.SYSTEM.LANGUAGE'));
            Cache::forget('lang.js');
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "App Localization Controller -> Destroy method");
            ResponseService::errorResponse();
        }
    }

    public function downloadFile($id, $type)
    {
        $type = AppLocalizationService::normalizeType($type);
        if (!$type) {
            return redirect()->back()->with('error', trans('Invalid platform type.'));
        }

        try {
            $language = $this->language->findById($id);
            $filePath = AppLocalizationService::filePath($language, $type);
            if ($filePath) {
                $fileName = $language->code . '_' . $type . '.json';
                return response()->download($filePath, $fileName, ['Content-Type: application/json']);
            }
            return redirect()->back()->with('error', trans('No file found.'));
        } catch (Throwable $e) {
            return redirect()->back()->with('error', trans('No file found.'));
        }
    }

    public function downloadSample($type)
    {
        $type = AppLocalizationService::normalizeType($type);
        if (!$type) {
            return redirect()->back()->with('error', trans('Invalid platform type.'));
        }

        $filePath = AppLocalizationService::samplePath($type);
        if ($filePath) {
            return response()->download($filePath, $type . '_sample_en.json', ['Content-Type: application/json']);
        }
        return redirect()->back()->with('error', trans('No file found.'));
    }

    /**
     * Request input name of the platform file. Panel file is posted as panel_file while it is stored in the `file` column.
     */
    private function requestKey(string $type): string
    {
        return $type == 'panel' ? 'panel_file' : AppLocalizationService::column($type);
    }

    /**
     * Ensure every uploaded translation file contains valid JSON key/value pairs.
     */
    private function validateJsonFiles(Request $request): void
    {
        foreach (AppLocalizationService::types() as $type) {
            $key = $this->requestKey($type);
            if ($request->hasFile($key) && !AppLocalizationService::isValidJsonFile($request->file($key))) {
                ResponseService::validationError(trans('The uploaded file must contain valid JSON key/value pairs.') . ' (' . $key . ')');
            }
        }
    }
}
