<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Bridges the central push-notification helpers (send_notification /
 * buildPayloads) to the WhatsApp channel.
 *
 * Modules keep raising a single push event; this translates that event's
 * push `$type` into a WhatsApp template event and delegates to
 * {@see NotificationService::sendToUsers()}, which owns all the gating
 * (feature flag, global enabled, per-template status) and per-recipient
 * dispatch. Nothing here talks to the WhatsApp provider directly.
 *
 * WhatsApp is best-effort: any failure is swallowed so the push / business
 * flow that raised the event is never affected.
 */
final class WhatsAppNotificationBridge
{
    /**
     * Map a push `$type` to a WhatsApp automatic template event key.
     * Returns null when the event has no WhatsApp counterpart (skip WhatsApp).
     *
     * Only the events seeded as automatic templates are supported:
     * fee, fee_paid, attendance_absent, assignment, exam_scheduled,
     * exam_result, leave_approved, leave_rejected.
     */
    public static function eventFor(string $type, string $title, string $body, array $customData): ?string
    {
        $type = strtolower(trim($type));
        $hay  = strtolower($title . ' ' . $body);

        return match (true) {
            // Fees payment: only the successful receipt has a template
            $type === 'payment'
            => (self::truthy($customData['is_payment_success'] ?? null) || str_contains($hay, 'success'))
                ? 'fee_paid' : null,

            // Fee assigned / reminder
            $type === 'fees', $type === 'fee-reminder'
            => 'fee',

            // Attendance push fires for present too — only absent has a template
            $type === 'attendance'
            => str_contains($hay, 'absent') ? 'attendance_absent' : null,

            $type === 'assignment'  => 'assignment',
            $type === 'exam'        => 'exam_scheduled',
            $type === 'exam result' => 'exam_result',
            $type === 'holiday'     => 'holiday',

            // Leave: distinguish approved vs rejected from the message text
            $type === 'leave'
            => str_contains($hay, 'reject') ? 'leave_rejected'
                : (str_contains($hay, 'approv') ? 'leave_approved' : null),

            // payroll, Transportation, Diary, Message, Notification, custom,
            // Online, task_assigned, holiday_leave_rejection => no WhatsApp
            default => null,
        };
    }

    /**
     * Translate a push notification into a WhatsApp send and delegate to the
     * communication center. Best-effort; never throws to the caller.
     *
     * @param int|array $user      Recipient user id or array of ids
     * @param array     $customData Extra structured variables from the caller
     */
    public static function dispatch($user, string $title, string $body, string $type, array $customData = []): void
    {
        try {
            // Caller already delivers this WhatsApp event through a richer dedicated
            // path (e.g. with a PDF receipt or computed dates) — skip to avoid duplicates.
            if (!empty($customData['_skip_whatsapp'])) {
                return;
            }

            $event = self::eventFor($type, $title, $body, $customData);
            if (!$event) {
                return;
            }

            $userIds = array_values(array_filter(is_array($user) ? $user : [$user]));
            if (empty($userIds)) {
                return;
            }

            // WhatsApp is per-school; webhooks / console commands may have no Auth user,
            // so fall back to the recipient's school.
            $schoolId = $customData['school_id'] ?? null;
            if (!$schoolId) {
                return;
            }

            // Shared variables. Per-recipient names (parent_name, ...) are added by
            // NotificationService::sendToUsers(). Caller-supplied structured values win.
            // Pass the resolved school id explicitly: webhooks/console commands have no
            // Auth user, and getSchoolSettings() otherwise falls back to Auth and returns null.
            $data = array_merge([
                'school_name' => app(CachingService::class)->getSchoolSettings('school_name', $schoolId) ?? '',
                // Catch-all for templates that use {{message}}
                'message'     => trim($title . ' — ' . $body),
            ], $customData);

            $school = School::find($schoolId);

            if ($school) {
                NotificationService::sendToUsers($event, $userIds, $data, $school);
            }
        } catch (\Throwable $e) {
            // WhatsApp must never break the push / business flow.
            Log::error('WhatsAppNotificationBridge::dispatch failed: ' . $e->getMessage());
        }
    }

    private static function truthy($value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'yes'], true);
    }
}
