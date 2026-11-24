<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Jobs\SendWhatsAppJob;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppLog;
use App\Models\WhatsAppMasterTemplate;
use App\Models\WhatsAppScheduledMessage;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTemplate;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\ResponseService;
use App\Services\SchoolDataService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class WhatsAppController extends Controller
{
    public function index()
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenRedirect(WhatsAppService::FEATURE);

        $schoolId = Auth::user()->school_id;
        $settings = $this->settingsRow($schoolId);

        $service = new WhatsAppService($settings);
        $service->syncUsageMonth();
        $settings->refresh();

        $stats = [
            'sent_today'  => WhatsAppLog::owner()->where('status', '!=', 'failed')->whereDate('created_at', now()->toDateString())->count(),
            'sent_month'  => (int) $settings->messages_sent,
            'failed'      => WhatsAppLog::owner()->where('status', 'failed')->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'remaining'   => $service->remainingLimit(),
        ];

        $metaInsights = $service->metaInsights();
        $webhookUrl = url('api/whatsapp/webhook/' . (Auth::user()->school->code ?? $schoolId));

        // Bulk messaging data (recipient roles + selectable templates)
        $bulkRoles = [];
        $templates = WhatsAppTemplate::owner()->manual()->where('status', 1)->orderBy('name')
            ->get(['id', 'event', 'name', 'category', 'header', 'body', 'footer', 'meta_template_name']);

        return view('whatsapp.index', compact('settings', 'stats', 'webhookUrl', 'bulkRoles', 'metaInsights', 'templates'));
    }

    public function saveSettings(Request $request)
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);
        $request->validate([
            'phone_number_id'       => 'required|string|max:64',
            'business_account_id'   => 'required|string|max:64',
            'access_token'          => 'nullable|string',
            'enabled'               => 'nullable|boolean',
            'default_meta_template' => 'nullable|string|max:191|regex:/^[a-z0-9_]+$/',
            'default_meta_language' => 'nullable|string|max:20',
            'default_meta_media_template' => 'nullable|string|max:191|regex:/^[a-z0-9_]+$/',
            'default_meta_image_template' => 'nullable|string|max:191|regex:/^[a-z0-9_]+$/',
        ], [
            'default_meta_template.regex' => trans('meta_template_name_format'),
            'default_meta_media_template.regex' => trans('meta_template_name_format'),
            'default_meta_image_template.regex' => trans('meta_template_name_format'),
        ]);

        // Meta needs a language CODE (en, en_US, hi …); names like "English"
        // would be sent verbatim and every send would fail with error 132001.
        $defaultMetaLanguage = null;
        if ($request->filled('default_meta_language')) {
            $defaultMetaLanguage = WhatsAppService::normalizeLanguageCode($request->default_meta_language);
            if ($defaultMetaLanguage === null) {
                ResponseService::validationError(trans('meta_language_code_format'));
            }
        }

        try {
            $settings = $this->settingsRow(Auth::user()->school_id);

            $data = [
                'phone_number_id'       => trim($request->phone_number_id),
                'business_account_id'   => trim($request->business_account_id),
                'enabled'               => (bool) $request->enabled,
                'default_meta_template' => $request->default_meta_template ? trim($request->default_meta_template) : null,
                'default_meta_language' => $defaultMetaLanguage,
                'default_meta_media_template' => $request->default_meta_media_template ? trim($request->default_meta_media_template) : null,
                'default_meta_image_template' => $request->default_meta_image_template ? trim($request->default_meta_image_template) : null,
            ];
            // Keep the stored token when the field is left empty on edit
            if (!empty($request->access_token)) {
                $data['access_token'] = trim($request->access_token);
            }
            if (empty($settings->verify_token)) {
                $data['verify_token'] = Str::random(32);
            }
            $settings->update($data);
            $settings->refresh();

            // Re-evaluate the connection state
            $service = new WhatsAppService($settings);
            $test = $service->testConnection();
            $settings->update(['status' => $test['success'] ? 'connected' : 'disconnected']);

            $message = $test['success'] ? 'Data Updated Successfully' : trans('saved_but_connection_failed') . ' ' . ($test['error'] ?? '');
            ResponseService::successResponse($message, ['connected' => $test['success'], 'verify_token' => $settings->verify_token]);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'WhatsApp Controller -> saveSettings method');
            ResponseService::errorResponse();
        }
    }

    public function testConnection()
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);
        try {
            $settings = $this->settingsRow(Auth::user()->school_id);
            $service = new WhatsAppService($settings);
            $result = $service->testConnection();
            $settings->update(['status' => $result['success'] ? 'connected' : 'disconnected']);

            if ($result['success']) {
                ResponseService::successResponse('connection_successful', $result['data'] ?? null);
            }
            ResponseService::errorResponse($result['error'] ?? 'connection_failed');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'WhatsApp Controller -> testConnection method');
            ResponseService::errorResponse();
        }
    }

    public function testMessage(Request $request)
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);
        $request->validate(['mobile' => 'required|string|min:8|max:20']);

        try {
            $settings = $this->settingsRow(Auth::user()->school_id);
            $service = new WhatsAppService($settings);

            if (!$service->isEnabled()) {
                ResponseService::errorResponse('whatsapp_is_not_enabled');
            }
            if (!$service->canSend()) {
                ResponseService::errorResponse('monthly_whatsapp_limit_reached');
            }

            // The editable test_message template drives the test. It ships linked to
            // the pre-approved hello_world Meta template so it always delivers, even
            // without an open 24h customer-service window. Schools may edit or re-link it.
            $template = WhatsAppService::templateForEvent(Auth::user()->school_id, 'test_message');
            $schoolName = app(CachingService::class)->getSchoolSettings('school_name') ?? '';
            if ($template) {
                $result = $service->sendUsingTemplate($template, $request->mobile, ['school_name' => $schoolName]);
                $templateName = $template->name;
                $message = $result['message'];
            } else {
                // Fallback when the template was disabled/removed
                $result = $service->sendTemplate($request->mobile, 'hello_world', 'en_US');
                $templateName = 'hello_world';
                $message = 'hello_world';
            }

            WhatsAppLog::create([
                'school_id'       => Auth::user()->school_id,
                'mobile'          => WhatsAppService::normalizeMobile($request->mobile),
                'event'           => 'test_message',
                'template_name'   => $templateName,
                'message'         => $message,
                'meta_message_id' => $result['meta_message_id'] ?? null,
                'status'          => $result['success'] ? 'sent' : 'failed',
                'response'        => $result['success'] ? null : ($result['error'] ?? null),
            ]);

            if ($result['success']) {
                $service->incrementUsage();
                ResponseService::successResponse('test_message_sent_successfully');
            }
            ResponseService::errorResponse($result['error'] ?? 'Error Occurred');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'WhatsApp Controller -> testMessage method');
            ResponseService::errorResponse();
        }
    }

    /* =====================================================
     |  Templates
     ===================================================== */

    public function templatesList()
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);

        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'ASC');
        $search = request('search');

        $sql = WhatsAppTemplate::owner()->automatic()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%$search%")->orwhere('event', 'LIKE', "%$search%");
                });
            });
        $total = $sql->count();
        $res = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();


        $bulkData = ['total' => $total];
        $rows = [];
        $no = $offset + 1;
        foreach ($res as $row) {
            $operate = '';
            $operate = BootstrapTableService::menuEditButton('edit', route('whatsapp.templates.update', $row->id));
            $operate .= BootstrapTableService::menuButton('send_test', route('whatsapp.templates.test', $row->id), ['wa-template-test'], [], 'fa fa-paper-plane');

            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['variables'] = WhatsAppService::extractVariables($row->body);
            $tempRow['operate'] = $operate ? BootstrapTableService::menuItem($operate) : '';
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function templateUpdate($id, Request $request)
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);
        $request->validate([
            'name'               => 'required|string|max:191',
            'body'               => 'required|string|max:4000',
            'header'             => 'nullable|string|max:191',
            'footer'             => 'nullable|string|max:191',
            'meta_template_name' => 'nullable|string|max:191|regex:/^[a-z0-9_]+$/',
            'meta_body_param'    => 'nullable|boolean',
            'status'             => 'nullable|boolean',
        ], [
            'meta_template_name.regex' => trans('meta_template_name_format'),
        ]);

        try {
            $settings = $this->settingsRow(Auth::user()->school_id);


            $template = WhatsAppTemplate::owner()->findOrFail($id);

            // Guard: the variables of the master template must survive the edit,
            // otherwise automatic messages would silently lose information.
            // $master = WhatsAppMasterTemplate::where('event', $template->event)->first();
            // if ($master) {
            //     $requiredVariables = WhatsAppService::extractVariables($master->body);
            //     $newVariables = WhatsAppService::extractVariables((string) $request->body);
            //     $missing = array_diff($requiredVariables, $newVariables);
            //     if (!empty($missing)) {
            //         ResponseService::validationError(trans('whatsapp_template_variables_missing') . ' {{' . implode('}}, {{', $missing) . '}}');
            //     }
            // }

            $template->update([
                'name'               => $request->name,
                'header'             => $request->header,
                'body'               => $request->body,
                'footer'             => $request->footer,
                'meta_template_name' => $request->meta_template_name ?: null,
                'meta_body_param'    => $request->meta_template_name ? ($request->meta_body_param ?? 0) : 0,
                'status'             => $request->status ?? 0,
            ]);
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'WhatsApp Controller -> templateUpdate method');
            ResponseService::errorResponse();
        }
    }

    /**
     * Send a single template to a test number with realistic sample data,
     * through the exact same delivery pipeline the automatic events use.
     */
    public function templateTest($id, Request $request)
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);
        $request->validate(['mobile' => 'required|string|min:8|max:20']);

        try {
            $settings = $this->settingsRow(Auth::user()->school_id);
            $service = new WhatsAppService($settings);

            if (!$service->isEnabled()) {
                ResponseService::errorResponse('whatsapp_is_not_enabled');
            }
            if (!$service->canSend()) {
                ResponseService::errorResponse('monthly_whatsapp_limit_reached');
            }

            $template = WhatsAppTemplate::owner()->findOrFail($id);
            $schoolName = app(\App\Services\CachingService::class)->getSchoolSettings('school_name') ?? '';
            $sampleData = WhatsAppService::sampleVariableData(WhatsAppService::extractVariables($template->body), $schoolName);

            $result = $service->sendUsingTemplate($template, $request->mobile, $sampleData);

            WhatsAppLog::create([
                'school_id'       => Auth::user()->school_id,
                'mobile'          => WhatsAppService::normalizeMobile($request->mobile),
                'event'           => $template->event,
                'template_name'   => $template->name . ' (' . trans('test_connection') . ')',
                'message'         => $result['message'],
                'meta_message_id' => $result['meta_message_id'] ?? null,
                'status'          => $result['success'] ? 'sent' : 'failed',
                'response'        => $result['success'] ? null : ($result['error'] ?? null),
            ]);

            if ($result['success']) {
                $service->incrementUsage();
                ResponseService::successResponse('test_message_sent_successfully', ['message' => $result['message']]);
            }
            ResponseService::errorResponse($result['error'] ?? 'Error Occurred');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'WhatsApp Controller -> templateTest method');
            ResponseService::errorResponse();
        }
    }

    /* =====================================================
     |  Manual templates (admin created, bulk sends only)
     ===================================================== */

    public function manualTemplatesList()
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);

        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');

        $sql = WhatsAppTemplate::owner()->manual()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%$search%")->orwhere('category', 'LIKE', "%$search%");
                });
            });
        $total = $sql->count();
        $res = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $bulkData = ['total' => $total];
        $rows = [];
        $no = $offset + 1;
        foreach ($res as $row) {
            $operate = BootstrapTableService::menuButton('edit', route('whatsapp.manual-templates.update', $row->id), ['wa-manual-edit'], [], 'fa fa-edit');
            $operate .= BootstrapTableService::menuButton('send_test', route('whatsapp.templates.test', $row->id), ['wa-template-test'], [], 'fa fa-paper-plane');
            $operate .= BootstrapTableService::menuButton('delete', route('whatsapp.manual-templates.destroy', $row->id), ['wa-manual-delete'], [], 'fa fa-trash');

            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['variables'] = WhatsAppService::extractVariables($row->body);
            $tempRow['operate'] = BootstrapTableService::menuItem($operate);
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function manualTemplateStore(Request $request)
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);
        $request->validate([
            'name'     => 'required|string|max:191',
            'category' => 'nullable|string|max:100',
            'body'     => 'required|string|max:4000',
            'header'   => 'nullable|string|max:191',
            'footer'   => 'nullable|string|max:191',
            'status'   => 'nullable|boolean',
        ]);

        try {
            WhatsAppTemplate::create([
                'school_id' => Auth::user()->school_id,
                // Unique internal key. Never collides with the fixed automatic event names.
                'event'     => 'manual_' . strtolower(Str::random(12)),
                'type'      => 'manual',
                'category'  => $request->category,
                'name'      => $request->name,
                'language'  => 'en',
                'header'    => $request->header,
                'body'      => $request->body,
                'footer'    => $request->footer,
                'status'    => $request->status ?? 1,
            ]);
            ResponseService::successResponse('Data Stored Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'WhatsApp Controller -> manualTemplateStore method');
            ResponseService::errorResponse();
        }
    }

    public function manualTemplateUpdate($id, Request $request)
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);
        $request->validate([
            'name'     => 'required|string|max:191',
            'category' => 'nullable|string|max:100',
            'body'     => 'required|string|max:4000',
            'header'   => 'nullable|string|max:191',
            'footer'   => 'nullable|string|max:191',
            'status'   => 'nullable|boolean',
        ]);

        try {
            $template = WhatsAppTemplate::owner()->manual()->findOrFail($id);
            $template->update([
                'name'     => $request->name,
                'category' => $request->category,
                'header'   => $request->header,
                'body'     => $request->body,
                'footer'   => $request->footer,
                'status'   => $request->status ?? 0,
            ]);
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'WhatsApp Controller -> manualTemplateUpdate method');
            ResponseService::errorResponse();
        }
    }

    public function manualTemplateDestroy($id)
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);
        try {
            // Automatic event templates can never be deleted through this endpoint
            WhatsAppTemplate::owner()->manual()->findOrFail($id)->delete();
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'WhatsApp Controller -> manualTemplateDestroy method');
            ResponseService::errorResponse();
        }
    }

    /* =====================================================
     |  External users (persistent WhatsApp contacts directory)
     ===================================================== */

    public function contactsList()
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);

        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');

        $sql = WhatsAppContact::owner()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%$search%")
                        ->orwhere('mobile', 'LIKE', "%$search%")
                        ->orwhere('tag', 'LIKE', "%$search%");
                });
            })
            ->when(request('tag'), function ($query) {
                $query->where('tag', request('tag'));
            });
        $total = $sql->count();
        $res = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $bulkData = ['total' => $total];
        $rows = [];
        $no = $offset + 1;
        foreach ($res as $row) {
            $operate = BootstrapTableService::menuButton('edit', route('whatsapp.contacts.update', $row->id), ['wa-contact-edit'], [], 'fa fa-edit');
            $operate .= BootstrapTableService::menuButton('delete', route('whatsapp.contacts.destroy', $row->id), ['wa-contact-delete'], [], 'fa fa-trash');

            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['operate'] = BootstrapTableService::menuItem($operate);
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function contactStore(Request $request)
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);
        $request->validate([
            'name'   => 'nullable|string|max:191',
            'mobile' => 'required|string|min:8|max:20',
            'tag'    => 'nullable|string|max:100',
        ]);

        try {
            $mobile = WhatsAppService::normalizeMobile($request->mobile);
            if (strlen($mobile) < 8 || strlen($mobile) > 15) {
                ResponseService::validationError(trans('invalid_mobile_number'));
            }

            // Sync semantics: same number updates the existing entry instead of failing
            WhatsAppContact::updateOrCreate(
                ['school_id' => Auth::user()->school_id, 'mobile' => $mobile],
                ['name' => $request->name, 'tag' => $request->tag, 'source' => 'manual']
            );
            ResponseService::successResponse('Data Stored Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'WhatsApp Controller -> contactStore method');
            ResponseService::errorResponse();
        }
    }

    public function contactUpdate($id, Request $request)
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);
        $request->validate([
            'name'   => 'nullable|string|max:191',
            'mobile' => 'required|string|min:8|max:20',
            'tag'    => 'nullable|string|max:100',
        ]);

        try {
            $contact = WhatsAppContact::owner()->findOrFail($id);
            $mobile = WhatsAppService::normalizeMobile($request->mobile);
            if (strlen($mobile) < 8 || strlen($mobile) > 15) {
                ResponseService::validationError(trans('invalid_mobile_number'));
            }
            $duplicate = WhatsAppContact::owner()->where('mobile', $mobile)->where('id', '!=', $contact->id)->exists();
            if ($duplicate) {
                ResponseService::validationError(trans('mobile_already_exists'));
            }
            $contact->update(['name' => $request->name, 'mobile' => $mobile, 'tag' => $request->tag]);
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'WhatsApp Controller -> contactUpdate method');
            ResponseService::errorResponse();
        }
    }

    public function contactDestroy($id)
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);
        try {
            WhatsAppContact::owner()->findOrFail($id)->delete();
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'WhatsApp Controller -> contactDestroy method');
            ResponseService::errorResponse();
        }
    }

    /**
     * Import / synchronize contacts from a CSV. Upsert by mobile: new numbers
     * are added, existing ones get their name / tag refreshed — importing the
     * same file twice never creates duplicates.
     */
    public function contactsImport(Request $request)
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
            'tag'  => 'nullable|string|max:100',
        ]);

        try {
            $handle = fopen($request->file('file')->getRealPath(), 'r');
            $added = 0;
            $updated = 0;
            $invalid = 0;
            $seen = [];
            $rowNumber = 0;
            $schoolId = Auth::user()->school_id;

            while (($row = fgetcsv($handle)) !== false && ($added + $updated) < 5000) {
                $rowNumber++;
                $mobileRaw = trim((string) ($row[0] ?? ''));
                $name = trim((string) ($row[1] ?? ''));
                if ($mobileRaw === '') {
                    continue;
                }
                $mobile = WhatsAppService::normalizeMobile($mobileRaw);
                if ($rowNumber === 1 && $mobile === '') {
                    continue; // header row
                }
                if (strlen($mobile) < 8 || strlen($mobile) > 15 || isset($seen[$mobile])) {
                    $invalid++;
                    continue;
                }
                $seen[$mobile] = true;

                $contact = WhatsAppContact::firstOrNew(['school_id' => $schoolId, 'mobile' => $mobile]);
                $isNew = !$contact->exists;
                $contact->fill([
                    'name'   => $name !== '' ? $name : $contact->name,
                    'tag'    => $request->tag ?: $contact->tag,
                    'source' => 'csv',
                ])->save();
                $isNew ? $added++ : $updated++;
            }
            fclose($handle);

            if ($added === 0 && $updated === 0) {
                ResponseService::errorResponse(trans('csv_no_valid_contacts'));
            }
            ResponseService::successResponse(
                trans('contacts_synced_summary', ['added' => $added, 'updated' => $updated, 'invalid' => $invalid]),
                ['added' => $added, 'updated' => $updated, 'invalid' => $invalid]
            );
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'WhatsApp Controller -> contactsImport method');
            ResponseService::errorResponse();
        }
    }

    /* =====================================================
     |  Bulk messaging
     ===================================================== */

    /**
     * Selectable recipients for a bulk send. Mirrors the notification module's
     * user list (current session year students / guardians / staff, role filter).
     */
    public function recipientsList(Request $request)
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);

        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');

        $sessionYearId = app(\App\Services\CachingService::class)->getSessionYear()->id;
        $sql = User::whereHas('roles', function ($q) {
            $q->whereNot('name', 'School Admin');
        })
            ->where(function ($q) use ($sessionYearId) {
                $q->whereHas('student', function ($q) use ($sessionYearId) {
                    $q->where('session_year_id', $sessionYearId);
                })->orWhereHas('child', function ($q) use ($sessionYearId) {
                    $q->where('session_year_id', $sessionYearId);
                })->orWhereHas('roles', function ($q) {
                    $q->whereNotIn('name', ['Student', 'Guardian']);
                });
            })
            ->where(function ($query) use ($search) {
                $query->when($search, function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%$search%")
                        ->orwhere('last_name', 'LIKE', "%$search%")
                        ->orwhere('mobile', 'LIKE', "%$search%")
                        ->orWhereRaw("concat(first_name,' ',last_name) LIKE '%" . $search . "%'");
                });
            })
            ->where('school_id', Auth::user()->school_id);

        if ($request->roles) {
            $roles = is_array($request->roles) ? $request->roles : explode(',', $request->roles);
            $sql->whereHas('roles', function ($q) use ($roles) {
                $q->whereIn('id', $roles);
            });
        }

        $total = $sql->count();
        $res = $sql->with('roles:id,name')->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $bulkData = ['total' => $total];
        $rows = [];
        $no = $offset + 1;
        foreach ($res as $row) {
            $rows[] = [
                'id'        => $row->id,
                'no'        => $no++,
                'full_name' => $row->full_name,
                'mobile'    => $row->mobile ?: '-',
                'role'      => $row->roles->pluck('name')->implode(', '),
            ];
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    /**
     * Queue one WhatsApp message per selected recipient — same pipeline as the
     * automatic events (template link / relay / text, limits, logging).
     */
    public function bulkSend(Request $request)
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);
        $request->validate([
            'template_id'   => 'required',
            'user_id'       => 'required_without_all:contacts,contact_ids|nullable|string',
            'contacts'      => 'nullable|json',
            'contact_ids'   => 'nullable|string',
            'variables'     => 'nullable|array',
            // Only the formats Meta accepts as an image / document header
            'attachment'    => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,ppt,pptx,csv,txt|max:10240',
            'schedule_type' => 'nullable|in:now,once,weekly,monthly',
            'scheduled_at'  => 'required_unless:schedule_type,now,null|nullable|date|after:now',
        ], [
            'user_id.required_without_all' => trans('please_select_users'),
            'scheduled_at.required_unless' => trans('please_select_schedule_datetime'),
            'scheduled_at.after'        => trans('schedule_time_must_be_future'),
        ]);

        try {
            $settings = $this->settingsRow(Auth::user()->school_id);
            $service = new WhatsAppService($settings);

            if (!$service->isEnabled()) {
                ResponseService::errorResponse('whatsapp_is_not_enabled');
            }
            if (!$service->canSend()) {
                ResponseService::errorResponse('monthly_whatsapp_limit_reached');
            }

            $template = WhatsAppTemplate::owner()->manual()->where('status', 1)->findOrFail($request->template_id);

            // Two audience sources: registered users or external CSV contacts
            $userIds = array_values(array_filter(array_unique(explode(',', (string) $request->user_id))));
            $contacts = [];
            if ($request->contact_ids) {
                $contactIds = array_values(array_filter(explode(',', (string) $request->contact_ids)));
                foreach (WhatsAppContact::owner()->whereIn('id', $contactIds)->get() as $contactRow) {
                    $contacts[] = ['mobile' => $contactRow->mobile, 'name' => (string) $contactRow->name];
                }
            }
            if ($request->contacts) {
                $jsonContacts = collect(json_decode($request->contacts, true) ?: [])
                    ->filter(static fn($contact) => !empty($contact['mobile']))
                    ->map(static fn($contact) => ['mobile' => (string) $contact['mobile'], 'name' => (string) ($contact['name'] ?? '')])
                    ->values()
                    ->take(5000)
                    ->toArray();
                $contacts = array_merge($contacts, $jsonContacts);
            }
            if (empty($userIds) && empty($contacts)) {
                ResponseService::errorResponse(trans('please_select_users'));
            }

            // Shared variable values entered by the admin (restricted to the template's own variables)
            $templateVariables = WhatsAppService::extractVariables($template->body);
            $sharedData = collect($request->variables ?? [])
                ->only($templateVariables)
                ->map(static fn($value) => trim((string) $value))
                ->filter()
                ->toArray();
            $sharedData['school_name'] = $sharedData['school_name']
                ?? (app(CachingService::class)->getSchoolSettings('school_name') ?? '');

            // Optional attachment (PDF / image / document) — published so the Meta API can fetch it
            $media = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $extension = strtolower($file->getClientOriginalExtension());
                $isImage = in_array($extension, WhatsAppService::IMAGE_EXTENSIONS, true);

                // Meta caps images at 5 MB — the generic 10 MB upload rule would
                // otherwise let a large photo through and fail at send time.
                if ($isImage && $file->getSize() > WhatsAppService::MAX_IMAGE_BYTES) {
                    ResponseService::validationError(trans('whatsapp_image_too_large'));
                }

                $originalName = $file->getClientOriginalName();
                $storedPath = $file->storeAs(
                    'whatsapp/attachments/' . Auth::user()->school_id,
                    time() . '-' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension,
                    'public'
                );
                $media = [
                    'type'     => $isImage ? 'image' : 'document',
                    'url'      => url(Storage::url($storedPath)),
                    'filename' => $originalName,
                ];
            }

            // Scheduled delivery: store and let the scheduler dispatch when due
            $scheduleType = $request->schedule_type ?: 'now';
            if ($scheduleType !== 'now') {
                $scheduled = WhatsAppScheduledMessage::create([
                    'school_id'     => Auth::user()->school_id,
                    'template_id'   => $template->id,
                    'user_ids'      => implode(',', $userIds),
                    'contacts'      => $contacts ?: null,
                    'variables'     => $sharedData,
                    'media'         => $media,
                    'schedule_type' => $scheduleType,
                    'next_run_at'   => date('Y-m-d H:i:s', strtotime($request->scheduled_at)),
                    'status'        => 'pending',
                    'created_by'    => Auth::user()->id,
                ]);
                ResponseService::successResponse(
                    trans('message_scheduled_successfully', ['datetime' => $scheduled->next_run_at]),
                    ['scheduled' => true, 'schedule_id' => $scheduled->id]
                );
            }

            $result = ['queued' => 0, 'skipped' => 0, 'batch_id' => null];
            if (!empty($userIds)) {
                $result = WhatsAppService::dispatchBulk(Auth::user()->school, $template, $userIds, $sharedData, $media);
            }
            if (!empty($contacts)) {
                $contactResult = WhatsAppService::dispatchBulkContacts(Auth::user()->school, $template, $contacts, $sharedData, $media);
                $result = [
                    'queued'   => $result['queued'] + $contactResult['queued'],
                    'skipped'  => $result['skipped'] + $contactResult['skipped'],
                    'batch_id' => $result['batch_id'] ?? $contactResult['batch_id'],
                ];
            }

            if ($result['queued'] === 0) {
                ResponseService::errorResponse(trans('no_recipients_with_mobile'));
            }
            ResponseService::successResponse(
                trans('bulk_messages_queued', ['queued' => $result['queued'], 'skipped' => $result['skipped']]),
                ['batch_id' => $result['batch_id'], 'queued' => $result['queued'], 'skipped' => $result['skipped']]
            );
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'WhatsApp Controller -> bulkSend method');
            ResponseService::errorResponse();
        }
    }

    /**
     * Parse an uploaded CSV of external contacts (column 1 = mobile, column 2 = name)
     * and return the cleaned list for preview. Nothing is stored or sent here.
     */
    public function parseContactsCsv(Request $request)
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        try {
            $handle = fopen($request->file('file')->getRealPath(), 'r');
            $valid = [];
            $invalid = 0;
            $duplicates = 0;
            $seen = [];
            $rowNumber = 0;

            while (($row = fgetcsv($handle)) !== false && count($valid) < 5000) {
                $rowNumber++;
                $mobileRaw = trim((string) ($row[0] ?? ''));
                $name = trim((string) ($row[1] ?? ''));
                if ($mobileRaw === '') {
                    continue;
                }

                $mobile = WhatsAppService::normalizeMobile($mobileRaw);
                // Header row (e.g. "mobile,name") — no digits after normalizing
                if ($rowNumber === 1 && $mobile === '') {
                    continue;
                }
                if (strlen($mobile) < 8 || strlen($mobile) > 15) {
                    $invalid++;
                    continue;
                }
                if (isset($seen[$mobile])) {
                    $duplicates++;
                    continue;
                }
                $seen[$mobile] = true;
                $valid[] = ['mobile' => $mobile, 'name' => $name];
            }
            fclose($handle);

            if (empty($valid)) {
                ResponseService::errorResponse(trans('csv_no_valid_contacts'));
            }

            ResponseService::successResponse(
                trans('csv_parsed_summary', ['valid' => count($valid), 'invalid' => $invalid, 'duplicates' => $duplicates]),
                ['contacts' => $valid, 'invalid' => $invalid, 'duplicates' => $duplicates]
            );
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'WhatsApp Controller -> parseContactsCsv method');
            ResponseService::errorResponse();
        }
    }

    /**
     * Sample CSV so admins know the expected format.
     */
    public function contactsSample()
    {
        ResponseService::noRoleThenRedirect('School Admin');
        $content = "mobile,name\n919876543210,Ramesh Shah\n918855885588,\"Patel, Priya\"\n917000000001,Alumni 2020 Batch\n";
        return response($content, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="whatsapp_contacts_sample.csv"',
        ]);
    }

    /* =====================================================
     |  Scheduled messages
     ===================================================== */

    public function scheduledList()
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);

        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');

        $sql = WhatsAppScheduledMessage::owner()->with('template:id,name')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('schedule_type', 'LIKE', "%$search%")
                        ->orwhere('status', 'LIKE', "%$search%")
                        ->orWhereHas('template', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%$search%");
                        });
                });
            });
        $total = $sql->count();
        $res = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $bulkData = ['total' => $total];
        $rows = [];
        $no = $offset + 1;
        foreach ($res as $row) {
            $operate = '';
            if ($row->status === 'pending') {
                $operate = BootstrapTableService::menuButton('cancel', route('whatsapp.scheduled.cancel', $row->id), ['wa-schedule-cancel'], [], 'fa fa-ban');
            }
            $rows[] = [
                'id'            => $row->id,
                'no'            => $no++,
                'template_name' => $row->template->name ?? '-',
                'recipients'    => count($row->userIdsArray()) + count($row->contacts ?? []),
                'schedule_type' => $row->schedule_type,
                'next_run_at'   => $row->status === 'pending' ? $row->next_run_at?->format('d-m-Y h:i A') : '-',
                'last_run_at'   => $row->last_run_at?->format('d-m-Y h:i A') ?? '-',
                'status'        => $row->status,
                'operate'       => $operate ? BootstrapTableService::menuItem($operate) : '',
            ];
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function scheduledCancel($id)
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);
        try {
            $scheduled = WhatsAppScheduledMessage::owner()->where('status', 'pending')->findOrFail($id);
            $scheduled->update(['status' => 'cancelled']);
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'WhatsApp Controller -> scheduledCancel method');
            ResponseService::errorResponse();
        }
    }

    /* =====================================================
     |  Logs
     ===================================================== */

    public function logsList()
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);

        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');

        $sql = WhatsAppLog::owner()->with('user:id,first_name,last_name')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('mobile', 'LIKE', "%$search%")
                        ->orwhere('event', 'LIKE', "%$search%")
                        ->orwhere('batch_id', 'LIKE', "%$search%")
                        ->orwhere('template_name', 'LIKE', "%$search%");
                });
            })
            ->when(request('status'), function ($query) {
                $query->where('status', request('status'));
            })
            ->when(request('date'), function ($query) {
                $query->whereDate('created_at', date('Y-m-d', strtotime(request('date'))));
            });

        $total = $sql->count();
        $res = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $bulkData = ['total' => $total];
        $rows = [];
        $no = $offset + 1;
        foreach ($res as $row) {
            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['recipient'] = $row->user->full_name ?? '-';
            $tempRow['operate'] = '';
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    /**
     * Re-queue failed messages: the stored rendered message is resent through
     * the current delivery routing and the SAME log row is updated in place.
     * Accepts specific ids (Retry Selected) or all=1 (Retry All Failed).
     */
    public function logsRetry(Request $request)
    {
        ResponseService::noRoleThenRedirect('School Admin');
        ResponseService::noFeatureThenSendJson(WhatsAppService::FEATURE);

        try {
            $settings = $this->settingsRow(Auth::user()->school_id);
            $service = new WhatsAppService($settings);
            if (!$service->isEnabled()) {
                ResponseService::errorResponse('whatsapp_is_not_enabled');
            }
            if (!$service->canSend()) {
                ResponseService::errorResponse('monthly_whatsapp_limit_reached');
            }

            $query = WhatsAppLog::owner()->where('status', 'failed')->whereNotNull('message');
            if (!$request->boolean('all')) {
                $ids = array_values(array_filter(explode(',', (string) $request->ids)));
                if (empty($ids)) {
                    ResponseService::errorResponse(trans('no_failed_messages_selected'));
                }
                $query->whereIn('id', $ids);
            }

            $failedLogs = $query->get();
            if ($failedLogs->isEmpty()) {
                ResponseService::errorResponse(trans('no_failed_messages_selected'));
            }

            $retried = 0;
            foreach ($failedLogs as $log) {
                // Optimistic UI state; the job resolves it to sent / failed
                $log->update(['status' => 'pending', 'response' => null]);
                SendWhatsAppJob::dispatch(
                    Auth::user()->school,
                    (string) $log->mobile,
                    $log->user_id,
                    $log->event,
                    [],
                    WhatsAppService::stripAttachmentMarker($log->message),
                    $log->batch_id,
                    $log->id
                );
                $retried++;
            }

            ResponseService::successResponse(trans('messages_queued_for_retry', ['count' => $retried]), ['retried' => $retried]);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'WhatsApp Controller -> logsRetry method');
            ResponseService::errorResponse();
        }
    }

    /* =====================================================
     |  Helpers
     ===================================================== */

    private function settingsRow(int $schoolId): WhatsAppSetting
    {
        return WhatsAppSetting::firstOrCreate(
            ['school_id' => $schoolId],
            ['enabled' => 0, 'status' => 'disconnected', 'verify_token' => Str::random(32), 'usage_month' => now()->format('Y-m')]
        );
    }
}
