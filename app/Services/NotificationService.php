<?php

namespace App\Services;

use App\Jobs\SendWhatsAppJob;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Communication Center.
 *
 * One engine for every outgoing channel. Modules never talk to a provider
 * (WhatsAppService, mail, FCM, ...) directly — they publish an EVENT here and
 * the enabled channels deliver it.
 *
 * Currently implemented channel: whatsapp.
 * Push and Email keep flowing through their existing legacy helpers and can be
 * migrated behind this engine without touching the calling modules. Adding a
 * new provider (Telegram, Messenger, ...) only requires a new sendVia*() branch.
 */
class NotificationService
{
    public const CHANNEL_WHATSAPP = 'whatsapp';

    /** Channels the engine currently knows how to deliver */
    public const CHANNELS = [
        self::CHANNEL_WHATSAPP,
    ];

    /**
     * Publish an event to the communication center.
     *
     * @param string   $event      Template event key, e.g. attendance_absent, fee_paid
     * @param array    $recipients Each item: ['mobile' => string, 'user_id' => int|null]
     * @param array    $data       Variables available to the templates, e.g. ['student_name' => ...]
     * @param School|null $school   Defaults to the authenticated user's school
     * @param array|null $channels Subset of self::CHANNELS, defaults to all
     */
    public static function send(string $event, array $recipients, array $data = [], ?School $school = null, ?array $channels = null, ?array $media = null): void
    {
        $schoolId = $school?->id;
        if (empty($schoolId) || empty($recipients)) {
            return;
        }

        foreach ($channels ?? self::CHANNELS as $channel) {
            try {
                match ($channel) {
                    self::CHANNEL_WHATSAPP => self::sendViaWhatsApp($event, $recipients, $data, $school, $media),
                    default => null,
                };
            } catch (\Throwable $e) {
                // A channel failure must never break the business flow that raised the event
                Log::error("NotificationService [$channel] error: " . $e->getMessage());
            }
        }
    }

    /**
     * Convenience wrapper: resolve the recipients from user ids on the current
     * (school) connection and publish the event.
     */
    public static function sendToUsers(string $event, array $userIds, array $data = [], ?School $school, ?array $channels = null, ?array $media = null): void
    {
        $userIds = array_values(array_filter(array_unique($userIds)));
        if (empty($userIds)) {
            return;
        }

        // Connect school database
        DB::setDefaultConnection('school');
        Config::set('database.connections.school.database', $school->database_name);
        DB::purge('school');
        DB::connection('school')->reconnect();
        DB::setDefaultConnection('school');

        $recipients = User::whereIn('id', $userIds)
            ->whereNotNull('mobile')
            ->whereNotNull('country_code')
            ->where('mobile', '!=', '')
            ->get(['id', 'mobile', 'first_name', 'last_name', 'country_code'])
            ->map(static fn($user) => [
                'mobile'  => $user->country_code . $user->mobile,
                'user_id' => $user->id,
                // Per recipient variables so templates can address the receiver
                'data'    => [
                    'recipient_name' => trim($user->first_name . ' ' . $user->last_name),
                    'parent_name'    => trim($user->first_name . ' ' . $user->last_name),
                    'teacher_name'   => trim($user->first_name . ' ' . $user->last_name),
                ],
            ])
            ->all();

        self::send($event, $recipients, $data, $school, $channels, $media);
    }

    /* =====================================================
     |  Channel: WhatsApp
     ===================================================== */

    private static function sendViaWhatsApp(string $event, array $recipients, array $data, School $school, ?array $media = null): void
    {
        $schoolId = $school->id;
        // Package must include the feature
        $features = FeaturesService::getFeatures($schoolId);
        if (!in_array(WhatsAppService::FEATURE, $features)) {
            return;
        }

        // School must be connected + enabled with an active template for the event
        $service = WhatsAppService::forSchool($schoolId);
        if (!$service || !$service->isEnabled()) {
            return;
        }
        if (!WhatsAppService::templateForEvent($schoolId, $event)) {
            return;
        }

        // Everything is queued — never send synchronously from a request
        foreach ($recipients as $recipient) {
            if (empty($recipient['mobile'])) {
                continue;
            }
            SendWhatsAppJob::dispatch(
                $school,
                (string) $recipient['mobile'],
                $recipient['user_id'] ?? null,
                $event,
                array_merge($data, $recipient['data'] ?? []),
                null,
                null,
                null,
                $media
            );
        }
    }
}
