<?php

use App\Jobs\SendFcmNotification;
use App\Models\AssignmentCommon;
use App\Models\ClassSubject;
use App\Models\FcmToken;
use App\Models\Students;
use App\Models\User;
use App\Services\CachingService;
use App\Services\FcmTokenService;
use App\Services\WhatsAppNotificationBridge;
use Google\Client;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Auth;

function send_notification($user, $title, $body, $type, $customData = [])
{
    // Mirror the push to WhatsApp when a matching template is enabled (best-effort,
    // never breaks the push flow). Runs before the token checks below so WhatsApp
    // still goes out to recipients without an app installed.
    WhatsAppNotificationBridge::dispatch($user, $title, $body, $type, $customData);

    // Ensure $user is an array
    $userIds = is_array($user) ? $user : [$user];
    // Fetch all active FCM tokens for the users using the new fcm_tokens table
    $fcmTokenService = app(FcmTokenService::class);
    $fcmTokens = $fcmTokenService->getUsersTokens($userIds);

    // If no tokens found, try backward compatibility with old columns
    if ($fcmTokens->isEmpty()) {
        // Fallback to old columns for backward compatibility
        $mobileFcmTokens = User::where('fcm_id', '!=', '')
            ->whereNotNull('fcm_id')
            ->whereIn('id', $userIds)
            ->get()
            ->pluck('fcm_id')
            ->filter();

        $webFcmTokens = User::where('web_fcm', '!=', '')
            ->whereNotNull('web_fcm')
            ->whereIn('id', $userIds)
            ->get()
            ->pluck('web_fcm')
            ->filter();

        if ($mobileFcmTokens->isEmpty() && $webFcmTokens->isEmpty()) {
            return; // No FCM tokens found
        }

        // Migrate old tokens and send notifications
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if ($user) {
                $fcmTokenService->migrateUserTokens($user);
            }
        }

        // Fetch tokens again after migration
        $fcmTokens = $fcmTokenService->getUsersTokens($userIds);
    }

    if ($fcmTokens->isEmpty()) {
        return; // No FCM tokens found
    }

    // Queue notification jobs for each token
    foreach ($fcmTokens as $fcmToken) {
        // Get school_id from the user
        $user = $fcmToken->user;
        if (!$user || !$user->school_id) {
            continue; // Skip if user or school_id not found
        }

        SendFcmNotification::dispatch(
            $user->school_id,
            $fcmToken->id,
            $title,
            $body,
            $type,
            $customData
        );
    }
}

/**
 * Send FCM notification via cURL
 */
function sendFcmNotification($url, $access_token, $data)
{
    $encodedData = json_encode($data);

    $headers = [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

    // Disabling SSL Certificate support temporarly
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedData);

    // Execute post
    $result = curl_exec($ch);
    // dd($result);

    if ($result === FALSE) {
        // Log error but continue with other tokens
        error_log('FCM notification failed: ' . curl_error($ch));
    } else {
        // Log response for debugging (optional)
        $response = json_decode($result, true);
        if (isset($response['error'])) {
            error_log('FCM notification error: ' . json_encode($response['error']));
        }
    }

    // Close connection
    curl_close($ch);
}

/**
 * Get web notification icon URL
 */
function getWebNotificationIcon($customData = [])
{
    // Default icon path - adjust based on your project structure
    $defaultIcon = asset('assets/images/favicon.png');

    // If image is provided in customData, use it as icon
    if (isset($customData['image']) && !empty($customData['image'])) {
        // If image is already a full URL, return it
        if (filter_var($customData['image'], FILTER_VALIDATE_URL)) {
            return $customData['image'];
        }
        // Otherwise, it's likely a storage path, convert to URL
        return url($customData['image']);
    }

    return $defaultIcon;
}

function getAccessToken()
{
    $cache = app(CachingService::class);

    $file_name = $cache->getSystemSettings('firebase_service_file');
    $data = explode("storage/", $file_name ?? '');
    $file_name = end($data);

    $file_path = base_path('public/storage/' . $file_name);

    $client = new Client();
    $client->setAuthConfig($file_path);
    $client->setScopes(['https://www.googleapis.com/auth/firebase.messaging']);
    $accessToken = $client->fetchAccessTokenWithAssertion()['access_token'];

    return $accessToken;
}

function buildPayloads(array $userIds, string $title, string $body, string $type, array $customData = [])
{
    // Mirror the push to WhatsApp when a matching template is enabled (best-effort,
    // never breaks the push flow). Runs before the token checks below so WhatsApp
    // still goes out to recipients without an app installed.
    WhatsAppNotificationBridge::dispatch($userIds, $title, $body, $type, $customData);

    // Convert custom data values to strings
    $customDataStrings = array_map(function ($value) {
        return is_array($value) ? json_encode($value) : (string) $value;
    }, $customData);

    // Get FCM tokens from new fcm_tokens table
    $fcmTokenService = app(FcmTokenService::class);
    $fcmTokens = $fcmTokenService->getUsersTokens($userIds);

    // If no tokens found, try backward compatibility with old columns
    if ($fcmTokens->isEmpty()) {
        // Fallback to old columns for backward compatibility
        $mobileTokens = User::whereIn('id', $userIds)
            ->whereNotNull('fcm_id')
            ->where('fcm_id', '!=', '')
            ->pluck('fcm_id')
            ->toArray();

        $webTokens = User::whereIn('id', $userIds)
            ->whereNotNull('web_fcm')
            ->where('web_fcm', '!=', '')
            ->pluck('web_fcm')
            ->toArray();

        // If still no tokens → nothing to send
        if (empty($mobileTokens) && empty($webTokens)) {
            return [];
        }

        // Build payloads from old tokens (for backward compatibility)
        $payloads = [];
        foreach ($mobileTokens as $token) {
            $payloads[] = [
                "message" => [
                    "token" => $token,
                    "notification" => [
                        "title" => $title,
                        "body" => $body,
                    ],
                    "data" => array_merge([
                        "title" => $title,
                        "body" => $body,
                        "type" => $type,
                    ], $customDataStrings),
                    "android" => [
                        "notification" => [
                            "sound" => "default",
                            "click_action" => "FLUTTER_NOTIFICATION_CLICK",
                        ],
                        "priority" => "high"
                    ],
                    "apns" => [
                        "headers" => [
                            "apns-priority" => "10"
                        ],
                        "payload" => [
                            "aps" => [
                                "alert" => [
                                    "title" => $title,
                                    "body" => $body,
                                ],
                                "sound" => "default",
                                "mutable-content" => 1,
                                "content-available" => 1
                            ]
                        ] + $customDataStrings
                    ]
                ]
            ];
        }

        foreach ($webTokens as $token) {
            $icon = getWebNotificationIcon($customData);

            $webNotification = [
                "title" => $title,
                "body" => $body,
                "icon" => $icon,
                "badge" => $icon,
                "requireInteraction" => false,
                "silent" => false,
            ];

            if (!empty($customData['image'])) {
                $img = $customData['image'];
                $webNotification["image"] = filter_var($img, FILTER_VALIDATE_URL) ? $img : url($img);
            }

            $payloads[] = [
                "message" => [
                    "token" => $token,
                    "notification" => [
                        "title" => $title,
                        "body" => $body
                    ],
                    "data" => array_merge([
                        "title" => $title,
                        "body" => $body,
                        "type" => $type,
                    ], $customDataStrings),
                    "webpush" => [
                        "notification" => $webNotification,
                    ]
                ]
            ];
        }

        return $payloads;
    }

    // Build payloads from new fcm_tokens table
    $cache = app(CachingService::class);
    $projectId = $cache->getSystemSettings('firebase_project_id');

    if (!$projectId) {
        return [];
    }

    $payloads = [];

    // Group tokens by device type
    $mobileTokens = $fcmTokens->whereIn('device_type', ['android', 'ios']);
    $webTokens = $fcmTokens->where('device_type', 'web');

    // Build mobile payloads
    foreach ($mobileTokens as $fcmToken) {
        $payloads[] = [
            "message" => [
                "token" => $fcmToken->fcm_token,
                "notification" => [
                    "title" => $title,
                    "body" => $body,
                ],
                "data" => array_merge([
                    "title" => $title,
                    "body" => $body,
                    "type" => $type,
                ], $customDataStrings),
                "android" => [
                    "notification" => [
                        "sound" => "default",
                        "click_action" => "FLUTTER_NOTIFICATION_CLICK",
                    ],
                    "priority" => "high"
                ],
                "apns" => [
                    "headers" => [
                        "apns-priority" => "10"
                    ],
                    "payload" => [
                        "aps" => [
                            "alert" => [
                                "title" => $title,
                                "body" => $body,
                            ],
                            "sound" => "default",
                            "mutable-content" => 1,
                            "content-available" => 1
                        ]
                    ] + $customDataStrings
                ]
            ]
        ];
    }

    // Build web payloads
    foreach ($webTokens as $fcmToken) {
        $icon = getWebNotificationIcon($customData);

        $webNotification = [
            "title" => $title,
            "body" => $body,
            "icon" => $icon,
            "badge" => $icon,
            "requireInteraction" => false,
            "silent" => false,
        ];

        if (!empty($customData['image'])) {
            $img = $customData['image'];
            $webNotification["image"] = filter_var($img, FILTER_VALIDATE_URL) ? $img : url($img);
        }

        $payloads[] = [
            "message" => [
                "token" => $fcmToken->fcm_token,
                "notification" => [
                    "title" => $title,
                    "body" => $body
                ],
                "data" => array_merge([
                    "title" => $title,
                    "body" => $body,
                    "type" => $type,
                ], $customDataStrings),
                "webpush" => [
                    "notification" => $webNotification,
                ]
            ]
        ];
    }

    return $payloads;
}

/**
 * Send all payloads in PARALLEL — super fast.
 */
function sendBulk(array $payloads)
{
    if (empty($payloads)) {
        return;
    }

    $cache = app(CachingService::class);
    $projectId = $cache->getSystemSettings('firebase_project_id');

    if (!$projectId) {
        return;
    }

    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
    $token = getAccessToken();

    Http::pool(function (Pool $pool) use ($payloads, $url, $token) {

        $requests = [];

        foreach ($payloads as $payload) {
            $requests[] = $pool
                ->withToken($token)
                ->post($url, $payload);
        }

        return $requests;
    });
}

if (!function_exists('sendAssignmentNotification')) {
    function sendAssignmentNotification($assignment, $assignmentName, $dueDate, $actionTitlePrefix = 'New assignment added in')
    {
        $assignmentCommons = AssignmentCommon::query()->where('assignment_id', $assignment->id)
            ->get(['class_section_id', 'class_subject_id']);

        $classSubjectsMap = ClassSubject::query()
            ->whereIn('id', $assignmentCommons->pluck('class_subject_id')->toArray())
            ->with('subject')
            ->get()
            ->keyBy('id');

        $sectionMap = $assignmentCommons->mapWithKeys(function ($ac) use ($classSubjectsMap) {
            return [$ac->class_section_id => $classSubjectsMap[$ac->class_subject_id] ?? null];
        })->filter();

        $studentsQuery = Students::query()->with('user')
            ->whereIn('class_section_id', $sectionMap->keys())
            ->with('class_section.class', 'class_section.section', 'class_section.medium')
            ->with('user:id,first_name,last_name,mobile,email');

        $coreSections = $sectionMap
            ->filter(fn($cs) => $cs->type === 'Compulsory')
            ->keys()
            ->toArray();

        $electivePairs = $sectionMap
            ->filter(fn($cs) => $cs->type === 'Elective')
            ->map(fn($cs, $sid) => [
                'class_section_id' => $sid,
                'class_subject_id' => $cs->id,
            ])
            ->values();

        if ($electivePairs->isNotEmpty()) {
            $studentsQuery->where(function ($q) use ($coreSections, $electivePairs) {
                if ($coreSections) {
                    $q->whereIn('class_section_id', $coreSections);
                }

                $q->orWhereIn('user_id', function ($sub) use ($electivePairs) {
                    $sub->select('student_id')
                        ->from('student_subjects')
                        ->where(function ($inner) use ($electivePairs) {
                            foreach ($electivePairs as $pair) {
                                $inner->orWhere(function ($c) use ($pair) {
                                    $c->where('class_section_id', $pair['class_section_id'])
                                        ->where('class_subject_id', $pair['class_subject_id']);
                                });
                            }
                        });
                });
            });
        }

        $students = $studentsQuery->get([
            'id',
            'user_id',
            'guardian_id',
            'class_section_id'
        ]);

        $body = $assignmentName;
        $type = 'assignment';

        $allPayloads = [];

        foreach ($students as $student) {
            $classSubject = $sectionMap[$student->class_section_id] ?? null;
            if (!$classSubject || !$classSubject->subject) {
                continue;
            }

            $subjectNameWithType = $classSubject->subject->name_with_type ?? ($classSubject->subject->name . ' (' . $classSubject->subject->type . ')');
            $title = $actionTitlePrefix . ' ' . $subjectNameWithType;

            $customData = [
                'assignment_id' => $assignment->id,
                'class_subject_id' => $classSubject->id,
                'assigment_status_update' => str_contains(strtolower($actionTitlePrefix), 'update') ? 1 : 0,
                'subject' => $subjectNameWithType,
                'class' => $student->class_section->full_name ?? '',
                'student_name' => $student->user->full_name ?? '',
                'due_date' => date('Y-m-d H:i', strtotime($dueDate)),
                'school_id' => Auth::user()->school_id ?? null,
            ];

            if ($student->user_id) {
                $allPayloads = array_merge(
                    $allPayloads,
                    buildPayloads([$student->user_id], $title, $body, $type, $customData)
                );
            }

            if ($student->guardian_id) {
                $allPayloads = array_merge(
                    $allPayloads,
                    buildPayloads(
                        [$student->guardian_id],
                        $title,
                        $body,
                        $type,
                        array_merge($customData, ['child_id' => $student->id])
                    )
                );
            }
        }

        if (!empty($allPayloads)) {
            sendBulk($allPayloads);
        }
    }
}
