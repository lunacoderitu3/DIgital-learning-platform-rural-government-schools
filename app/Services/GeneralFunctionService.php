<?php

namespace App\Services;

use App\Models\School;
use App\Repositories\ClassSection\ClassSectionInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GeneralFunctionService
{

    public function __construct(
        private CachingService $cache,
        private ClassSectionInterface $classSection,
    ) {}



    public function wrongNotificationSetup($e)
    {
        $status = 1;
        if (Str::contains($e->getMessage(), ['does not exist', 'file_get_contents', 'Cannot access offset of type string on string'])) {
            $status = 0;
        }
        return $status;
    }

    public function reCaptcha($request)
    {
        if (env('RECAPTCHA_SECRET_KEY') ?? '') {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => env('RECAPTCHA_SECRET_KEY'),
                'response' => $request->input('g-recaptcha-response'),
                'remoteip' => $request->ip(),
            ]);

            $responseData = $response->json();

            if (!$responseData['success']) {
                return 0;
            }
            return 1;
        } else {
            return 1;
        }
    }

    public function schoolreCaptcha($request, $schoolSettings)
    {
        if ($schoolSettings['SCHOOL_RECAPTCHA_SECRET_KEY'] ?? '') {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $schoolSettings['SCHOOL_RECAPTCHA_SECRET_KEY'],
                'response' => $request->input('g-recaptcha-response'),
                'remoteip' => $request->ip(),
            ]);
            $responseData = $response->json();
            if (!$responseData['success']) {
                return 0;
            }
            return 1;
        } else {
            return 1;
        }
    }


    public function replacePlaceholders($templateContent, $assignment)
    {
        // $settings = $this->cache->getSchoolSettings();
        // $sessionYear = $this->cache->getSessionYear();
        $user = $assignment->user;

        $settings = app(CachingService::class)->getSchoolSettings('*', $user->school_id);
        $sessionYear = $assignment->session_year;

        $placeholders = [
            '{full_name}' => $user->full_name,
            '{first_name}' => $user->first_name,
            '{last_name}' => $user->last_name,
            '{mobile}' => $user->mobile,
            '{student_mobile}' => $user->mobile,
            '{dob}' => $user->dob,
            '{current_address}' => $user->current_address,
            '{permanent_address}' => $user->permanent_address,
            '{gender}' => $user->gender,
            '{email}' => $user->email ? '<!--email_off-->' . $user->email . '<!--/email_off-->' : '',
            '{session_year}' => $sessionYear->name ?? '',
            '{school_name}' => $settings['school_name'] ?? '',
            '{issue_date}' => $assignment->getRawOriginal('issued_at') ? date('Y-m-d', strtotime($assignment->getRawOriginal('issued_at'))) : date('Y-m-d'),
            ...$this->extraFormFields($user)
        ];

        if ($assignment->user_type === 'Student') {
            // Get class name, section and medium

            $classSection = $assignment->class_section;
            $className = $classSection->class ? $classSection->class->name : '';
            $className .=  $classSection->section ? ' - ' . $classSection->section->name : '';
            $className .= $classSection->medium ? ' (' . $classSection->medium->name . ')' : '';
            // $className = 
            $student_data = [
                '{class_section}' => $className,
                '{shift}' => $assignment->class_section->class->shift ? $assignment->class_section->class->shift->name : '',
                '{stream}' => $assignment->class_section->class->stream ? $assignment->class_section->class->stream->name : '',
                '{roll_no}' => $assignment->roll_no,
                '{admission_no}' => $user->student ? $user->student->admission_no : '',
                '{admission_date}' => $user->student ? $user->student->admission_date : '',
                '{guardian_name}' => ($user->student && $user->student->guardian) ? $user->student->guardian->full_name : '',
                '{guardian_mobile}' => ($user->student && $user->student->guardian) ? $user->student->guardian->mobile : '',
                '{guardian_email}' => ($user->student && $user->student->guardian) ? '<!--email_off-->' . $user->student->guardian->email . '<!--/email_off-->' : '',
            ];
            $placeholders = array_merge($placeholders, $student_data);

            if ($assignment->exam_id && $user->student && count($user->student->exam_result)) {
                $result = $user->student->exam_result->where('exam_id', $assignment->exam_id)->first();
                if ($result) {
                    $exam_data = [
                        '{exam}' => $result->exam->name,
                        '{total_marks}' => $result->total_marks,
                        '{obtain_marks}' => $result->obtained_marks,
                        '{grade}' => $result->grade,
                        '{percentage}' => $result->percentage,
                        '{result_status}' => $result->status == 1 ? 'Pass' : 'Fail',
                    ];
                    $placeholders = array_merge($placeholders, $exam_data);
                }
            }
        } elseif ($assignment->user_type === 'Staff') {
            $today_date = \Carbon\Carbon::now();
            $experience = 0;

            if ($user->staff && $user->staff->joining_date) {
                // Fix: Extract just the date string if time/format is tricky, but Carbon::parse should handle most standard date formats
                $joining_date = \Carbon\Carbon::parse($user->staff->joining_date);
                $experience = $joining_date->diffInMonths($today_date) / 12;
            }

            $staff_data = [
                '{joining_date}' => ($user->staff && $user->staff->joining_date) ? date($settings['date_format'], strtotime($user->staff->joining_date)) : '',
                '{role}' => $user->roles ? implode(',', $user->roles->pluck('name')->toArray()) : '',
                '{qualification}' => ($user->staff) ? $user->staff->qualification : '',
                '{experience}' => number_format($experience, 1),
            ];
            $placeholders = array_merge($placeholders, $staff_data);
        }

        foreach ($placeholders as $placeholder => $replacement) {
            $templateContent = str_replace($placeholder, (string)$replacement, $templateContent);
        }

        return $templateContent;
    }

    public function extraFormFields($user)
    {
        $extraStudentDetails = array();
        foreach ($user->extra_student_details as $key => $formField) {
            if (in_array($formField->form_field->type, ['radio', 'text', 'number', 'textarea'])) {
                $extraStudentDetails['{' . $formField->form_field->name . '}'] = $formField->data;
            }
            if ($formField->form_field->type == 'checkbox') {
                $data = json_decode($formField->data);
                if ($data) {
                    $extraStudentDetails['{' . $formField->form_field->name . '}'] = implode(", ", $data);
                }
            }
            if ($formField->form_field->type == 'dropdown') {
                if ($formField->form_field && isset($formField->form_field->default_values[$formField->data])) {
                    $extraStudentDetails['{' . $formField->form_field->name . '}'] = $formField->form_field->default_values[$formField->data];
                }
            }
        }
        return $extraStudentDetails;
    }

    public function getClassesForUser()
    {
        $sessionYearId = $this->cache->getSessionYear()->id;
        if (Auth::user()->hasRole('Teacher')) {
            // Teachers can only see classes where they are teaching subjects
            return $this->classSection->builder()
                ->whereHas('subject_teachers', function ($query) use ($sessionYearId) {
                    $query->where('teacher_id', Auth::user()->id)
                        ->where('session_year_id', $sessionYearId);
                })
                ->with(['class.medium', 'class.stream', 'class.shift', 'section'])
                ->get()
                ->groupBy('class.id')
                ->map(function ($classSections) {
                    $class = $classSections->first()->class;
                    $mediumName = $class->medium ? $class->medium->name : '';
                    $streamName = $class->stream ? $class->stream->name : '';

                    return [
                        'id' => $class->id,
                        'name' => $class->name,
                        'medium' => $mediumName,
                        'stream' => $streamName,
                        'full_name' => $class->name . ' - ' . $mediumName . ($streamName ? ' (' . $streamName . ')' : '') . ($class->shift ? ' ' . $class->shift->name : '')
                    ];
                })
                ->values();
        } else {
            // School Admin can see all classes
            return $this->classSection->builder()
                ->with(['class.medium', 'class.stream', 'class.shift', 'section'])
                ->get()
                ->groupBy('class.id')
                ->map(function ($classSections) {
                    $class = $classSections->first()->class;
                    $mediumName = $class->medium ? $class->medium->name : '';
                    $streamName = $class->stream ? $class->stream->name : '';

                    return [
                        'id' => $class->id,
                        'name' => $class->name,
                        'medium' => $mediumName,
                        'stream' => $streamName,
                        'full_name' => $class->name . ' - ' . $mediumName . ($streamName ? ' (' . $streamName . ')' : '') . ($class->shift ? ' ' . $class->shift->name : '')
                    ];
                })
                ->values();
        }
    }


    public function getMessage(\Throwable $e)
    {
        $error = $e->getMessage();

        if (str_contains($error, '535')) {
            return 'Email authentication failed. Please check your SMTP credentials.';
        }

        if (str_contains($error, '421')) {
            return 'Email server is temporarily unavailable. Please try again later.';
        }

        if (str_contains($error, '450') || str_contains($error, '451') || str_contains($error, '452')) {
            return 'The email server temporarily rejected the email. Please try again later.';
        }

        if (str_contains($error, '550') || str_contains($error, '553')) {
            return 'The recipient email address is invalid or was rejected by the email server.';
        }

        if (str_contains($error, '552')) {
            return 'The recipient mailbox is full and cannot receive emails.';
        }

        if (str_contains(strtolower($error), 'daily limit')) {
            return 'The daily email sending limit has been reached. Please try again later.';
        }

        if (str_contains(strtolower($error), 'rate limit')) {
            return 'The email sending limit has been reached. Please try again later.';
        }

        if (str_contains(strtolower($error), 'timeout')) {
            return 'Email server connection timed out. Please try again later.';
        }

        return null;
    }
}
