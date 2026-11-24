<?php

namespace App\Services;

use App\Jobs\SendWhatsAppJob;
use App\Models\School;
use App\Models\Students;
use App\Models\User;
use App\Models\WhatsAppLog;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Meta WhatsApp Business Cloud API integration.
 *
 * All reads / writes of whatsapp_* tables happen on the CURRENT default
 * connection, which the request middleware / queue jobs switch to the
 * school database before using this service.
 */
class WhatsAppService
{
    public const GRAPH_URL = 'https://graph.facebook.com/v25.0';

    /** Feature name registered in the features table / packages */
    public const FEATURE = 'WhatsApp Messaging';

    /** The only extensions Meta accepts for an IMAGE header (image/jpeg, image/png) */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    /** Extensions Meta accepts for a DOCUMENT header */
    public const DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];

    /** Appended to the logged message so the panel shows a message had a file */
    public const ATTACHMENT_LOG_MARKER = "\n📎 ";

    /** Meta media size caps, in bytes */
    public const MAX_IMAGE_BYTES = 5242880;    // 5 MB
    public const MAX_DOCUMENT_BYTES = 104857600; // 100 MB

    private WhatsAppSetting $settings;

    public function __construct(WhatsAppSetting $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Build the service for a school on the current connection.
     */
    public static function forSchool(int $schoolId): ?self
    {
        $settings = WhatsAppSetting::where('school_id', $schoolId)->first();
        return $settings ? new self($settings) : null;
    }

    public function settings(): WhatsAppSetting
    {
        return $this->settings;
    }

    public function isConfigured(): bool
    {
        return !empty($this->settings->phone_number_id) && !empty($this->settings->getRawOriginal('access_token'));
    }

    public function isEnabled(): bool
    {
        return $this->settings->enabled && $this->isConfigured();
    }

    /* =====================================================
     |  Sending
     ===================================================== */

    public function sendText(string $to, string $message): array
    {
        return $this->callMessagesApi([
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => self::normalizeMobile($to),
            'type'              => 'text',
            'text'              => ['preview_url' => false, 'body' => $message],
        ]);
    }

    /**
     * Send a Meta pre-approved template message (template must exist in the
     * WhatsApp Business account).
     */
    public function sendTemplate(string $to, string $templateName, string $language = 'en', array $components = []): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => self::normalizeMobile($to),
            'type'              => 'template',
            'template'          => [
                'name'     => $templateName,
                // Stored values may predate input normalization ("English", "en-us")
                'language' => ['code' => self::normalizeLanguageCode($language) ?? 'en'],
            ],
        ];
        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }
        return $this->callMessagesApi($payload);
    }

    public function sendImage(string $to, string $imageUrl, ?string $caption = null): array
    {
        $image = ['link' => $imageUrl];
        if ($caption) {
            $image['caption'] = $caption;
        }
        return $this->callMessagesApi([
            'messaging_product' => 'whatsapp',
            'to'                => self::normalizeMobile($to),
            'type'              => 'image',
            'image'             => $image,
        ]);
    }

    /**
     * Send a document (PDF, etc.) by public URL.
     */
    public function sendDocument(string $to, string $documentUrl, ?string $filename = null, ?string $caption = null): array
    {
        $document = ['link' => $documentUrl];
        if ($filename) {
            $document['filename'] = $filename;
        }
        if ($caption) {
            $document['caption'] = $caption;
        }
        return $this->callMessagesApi([
            'messaging_product' => 'whatsapp',
            'to'                => self::normalizeMobile($to),
            'type'              => 'document',
            'document'          => $document,
        ]);
    }

    private function callMessagesApi(array $payload): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp is not configured.', 'response' => null];
        }

        try {
            $response = Http::withToken($this->settings->getRawOriginal('access_token'))
                ->timeout(30)
                ->post(self::GRAPH_URL . '/' . $this->settings->phone_number_id . '/messages', $payload);

            return $this->parseSendResponse($response);
        } catch (\Throwable $e) {
            Log::error('WhatsAppService send error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'response' => null];
        }
    }

    private function parseSendResponse(Response $response): array
    {
        $json = $response->json();
        if ($response->successful() && !empty($json['messages'][0]['id'])) {
            return [
                'success'         => true,
                'meta_message_id' => $json['messages'][0]['id'],
                'response'        => $json,
            ];
        }
        $error = $json['error']['message'] ?? ('HTTP ' . $response->status());
        $hint = self::errorHint($json['error']['code'] ?? null);
        return [
            'success'  => false,
            'error'    => $hint ? $error . ' — ' . $hint : $error,
            'response' => $json,
        ];
    }

    /**
     * Actionable guidance for the Meta error codes school admins hit most,
     * appended to the raw API error shown in the panel and the logs.
     */
    private static function errorHint(?int $code): ?string
    {
        return match ($code) {
            131030 => trans('whatsapp_hint_recipient_not_allowed'),
            132001 => trans('whatsapp_hint_template_not_found'),
            132000 => trans('whatsapp_hint_parameter_mismatch'),
            131053 => trans('whatsapp_hint_media_not_fetched'),
            default => null,
        };
    }

    /* =====================================================
     |  Connection
     ===================================================== */

    /**
     * Verify the credentials by fetching the phone number resource.
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Phone Number ID and Access Token are required.'];
        }

        try {
            $response = Http::withToken($this->settings->getRawOriginal('access_token'))
                ->timeout(20)
                ->get(self::GRAPH_URL . '/' . $this->settings->phone_number_id, ['fields' => 'display_phone_number,verified_name,quality_rating']);

            $json = $response->json();
            if ($response->successful()) {
                return ['success' => true, 'data' => $json];
            }
            return ['success' => false, 'error' => $json['error']['message'] ?? ('HTTP ' . $response->status())];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /* =====================================================
     |  Webhook
     ===================================================== */

    /**
     * Meta webhook GET verification handshake.
     */
    public function verifyWebhook(?string $mode, ?string $token, ?string $challenge): ?string
    {
        if ($mode === 'subscribe' && !empty($token) && hash_equals((string) $this->settings->verify_token, $token)) {
            return $challenge;
        }
        return null;
    }

    /**
     * Process the webhook payload: update the delivery status of the logs.
     */
    public function processWebhook(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['statuses'] ?? [] as $statusEvent) {
                    $metaMessageId = $statusEvent['id'] ?? null;
                    $status = $statusEvent['status'] ?? null; // sent | delivered | read | failed
                    if (!$metaMessageId || !$status) {
                        continue;
                    }

                    $log = WhatsAppLog::where('meta_message_id', $metaMessageId)->first();
                    if (!$log) {
                        continue;
                    }

                    // Never downgrade a read status back to delivered/sent
                    $order = ['pending' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 3];
                    if (($order[$status] ?? 0) >= ($order[$log->status] ?? 0)) {
                        $log->status = $status;
                        if ($status === 'failed' && !empty($statusEvent['errors'])) {
                            $log->response = json_encode($statusEvent['errors']);
                        }
                        $log->save();
                    }
                }
            }
        }
    }

    public function getMessageStatus(string $metaMessageId): ?string
    {
        return WhatsAppLog::where('meta_message_id', $metaMessageId)->value('status');
    }

    /* =====================================================
     |  Monthly limits
     ===================================================== */

    /**
     * Global WhatsApp feature configuration set by the Super Admin on the
     * Features page. Stored in system_settings (main database).
     */
    public static function globalSettings(): array
    {
        $defaults = [
            'whatsapp_allow_bulk'      => '0',
            'whatsapp_allow_templates' => '1',
            'whatsapp_allow_media'     => '0',
            'whatsapp_allow_documents' => '0',
            'whatsapp_allow_logs'      => '1',
            'whatsapp_allow_test'      => '1',
        ];
        try {
            // getSystemSettings('*') returns a Collection; the array-key variant
            // has a Collection/array type mismatch upstream, so filter here instead.
            $stored = collect(app(CachingService::class)->getSystemSettings())
                ->only(array_keys($defaults))
                ->filter(static fn($value) => !is_null($value) && $value !== '')
                ->toArray();
            return array_merge($defaults, $stored);
        } catch (\Throwable $e) {
            Log::error('WhatsAppService globalSettings error: ' . $e->getMessage());
            return $defaults;
        }
    }

    /**
     * No platform-side limit: every school runs its own Meta account and Meta
     * enforces its own messaging tiers. Kept for API compatibility.
     */
    public function effectiveMonthlyLimit(): ?int
    {
        return null;
    }

    /**
     * Reset the usage counter when a new month starts.
     */
    public function syncUsageMonth(): void
    {
        $currentMonth = now()->format('Y-m');
        if ($this->settings->usage_month !== $currentMonth) {
            $this->settings->update(['usage_month' => $currentMonth, 'messages_sent' => 0]);
            $this->settings->refresh();
        }
    }

    public function remainingLimit(): ?int
    {
        $limit = $this->effectiveMonthlyLimit();
        if (is_null($limit)) {
            return null; // unlimited
        }
        $this->syncUsageMonth();
        return max(0, $limit - (int) $this->settings->messages_sent);
    }

    public function canSend(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        $remaining = $this->remainingLimit();
        return is_null($remaining) || $remaining > 0;
    }

    /**
     * Live account insights straight from the Meta API: the messaging tier
     * (Meta's own sending limit), number quality rating and the real
     * conversation cost Meta will bill for the current month. Cached for
     * 30 minutes; every block degrades gracefully when the token lacks the
     * whatsapp_business_management permission.
     */
    public function metaInsights(): array
    {
        $this->syncUsageMonth();
        $insights = [
            'used'            => (int) $this->settings->messages_sent,
            'tier'            => null,
            'tier_label'      => null,
            'quality_rating'  => null,
            'phone_number'    => null,
            'cost'            => null,
            'cost_currency'   => null,
            'conversations'   => null,
        ];

        if (!$this->isConfigured() || empty($this->settings->business_account_id)) {
            return $insights;
        }

        $cacheKey = 'wa_meta_insights_' . $this->settings->school_id . '_' . now()->format('Y-m');
        $fetched = Cache::remember($cacheKey, 1800, function () {
            $data = ['tier' => null, 'quality_rating' => null, 'phone_number' => null, 'cost' => null, 'cost_currency' => null, 'conversations' => null];
            $token = $this->settings->getRawOriginal('access_token');

            // Phone number: messaging tier + quality rating
            try {
                $response = Http::withToken($token)->timeout(20)->get(
                    self::GRAPH_URL . '/' . $this->settings->business_account_id . '/phone_numbers',
                    ['fields' => 'id,display_phone_number,quality_rating,messaging_limit_tier']
                );
                if ($response->successful()) {
                    foreach ($response->json('data') ?? [] as $phone) {
                        if (($phone['id'] ?? null) == $this->settings->phone_number_id || count($response->json('data')) === 1) {
                            $data['tier'] = $phone['messaging_limit_tier'] ?? null;
                            $data['quality_rating'] = $phone['quality_rating'] ?? null;
                            $data['phone_number'] = $phone['display_phone_number'] ?? null;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error('WhatsAppService metaInsights phone_numbers: ' . $e->getMessage());
            }

            // Real conversation cost billed by Meta for the current month
            try {
                $start = now()->startOfMonth()->timestamp;
                $end = now()->timestamp;
                $field = "conversation_analytics.start($start).end($end).granularity(MONTHLY).phone_numbers([\"{$this->settings->phone_number_id}\"])";
                $response = Http::withToken($token)->timeout(20)->get(
                    self::GRAPH_URL . '/' . $this->settings->business_account_id,
                    ['fields' => $field]
                );
                if ($response->successful()) {
                    $cost = 0.0;
                    $conversations = 0;
                    foreach ($response->json('conversation_analytics.data.0.data_points') ?? [] as $point) {
                        $cost += (float) ($point['cost'] ?? 0);
                        $conversations += (int) ($point['conversation'] ?? 0);
                    }
                    $data['cost'] = round($cost, 2);
                    $data['conversations'] = $conversations;
                }
            } catch (\Throwable $e) {
                Log::error('WhatsAppService metaInsights analytics: ' . $e->getMessage());
            }

            return $data;
        });

        $tierLabels = [
            'TIER_50'        => '50 / 24h',
            'TIER_250'       => '250 / 24h',
            'TIER_1K'        => '1,000 / 24h',
            'TIER_10K'       => '10,000 / 24h',
            'TIER_100K'      => '100,000 / 24h',
            'TIER_UNLIMITED' => 'Unlimited',
        ];
        $fetched['tier_label'] = $fetched['tier'] ? ($tierLabels[$fetched['tier']] ?? $fetched['tier']) : null;

        return array_merge($insights, $fetched);
    }

    public function incrementUsage(): void
    {
        $this->syncUsageMonth();
        $this->settings->increment('messages_sent');
    }

    /* =====================================================
     |  Bulk dispatch
     ===================================================== */

    /**
     * Queue one message per recipient using a template — shared by the manual
     * bulk send and the scheduled message processor. Must run on the school
     * database connection.
     *
     * @return array{queued:int, skipped:int, batch_id:string}
     */
    public static function dispatchBulk(School $school, WhatsAppTemplate $template, array $userIds, array $sharedData = [], ?array $media = null): array
    {
        $users = User::whereIn('id', $userIds)
            ->where('school_id', $school->id)
            ->whereNotNull('mobile')
            ->whereNotNull('country_code')
            ->get(['id', 'first_name', 'last_name', 'mobile', 'country_code']);

        $batchId = (string) Str::uuid();
        $queued = 0;
        $skipped = 0;
        foreach ($users as $user) {
            if (empty($user->mobile)) {
                $skipped++;
                continue;
            }
            $recipientName = trim($user->first_name . ' ' . $user->last_name);
            SendWhatsAppJob::dispatch(
                $school,
                (string) $user->country_code . $user->mobile,
                $user->id,
                $template->event,
                array_merge($sharedData, [
                    'recipient_name' => $recipientName,
                    'parent_name'    => $sharedData['parent_name'] ?? $recipientName,
                    'teacher_name'   => $sharedData['teacher_name'] ?? $recipientName,
                ]),
                null,
                $batchId,
                null,
                $media
            );
            $queued++;
        }

        return ['queued' => $queued, 'skipped' => $skipped, 'batch_id' => $batchId];
    }

    /**
     * Queue one message per EXTERNAL contact (imported from CSV — alumni,
     * inquiries, ...). Same pipeline as dispatchBulk but without user rows.
     *
     * @param array $contacts Each item: ['mobile' => string, 'name' => string|null]
     * @return array{queued:int, skipped:int, batch_id:string}
     */
    public static function dispatchBulkContacts(School $school, WhatsAppTemplate $template, array $contacts, array $sharedData = [], ?array $media = null): array
    {
        $batchId = (string) Str::uuid();
        $queued = 0;
        $skipped = 0;
        $seen = [];
        foreach ($contacts as $contact) {
            $mobile = self::normalizeMobile((string) ($contact['mobile'] ?? ''));
            if (strlen($mobile) < 8 || strlen($mobile) > 15 || isset($seen[$mobile])) {
                $skipped++;
                continue;
            }
            $seen[$mobile] = true;
            $name = trim((string) ($contact['name'] ?? ''));
            SendWhatsAppJob::dispatch(
                $school,
                $mobile,
                null,
                $template->event,
                array_merge($sharedData, [
                    'recipient_name' => $name,
                    'parent_name'    => $sharedData['parent_name'] ?? $name,
                    'teacher_name'   => $sharedData['teacher_name'] ?? $name,
                ]),
                null,
                $batchId,
                null,
                $media
            );
            $queued++;
        }

        return ['queued' => $queued, 'skipped' => $skipped, 'batch_id' => $batchId];
    }

    /* =====================================================
     |  Templates
     ===================================================== */

    /**
     * Replace {{variable}} placeholders with the given data.
     * Unknown variables are left untouched so no information silently disappears.
     */
    public static function renderTemplate(string $body, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', static function ($matches) use ($data) {
            return array_key_exists($matches[1], $data) ? (string) $data[$matches[1]] : $matches[0];
        }, $body);
    }

    /**
     * Extract the {{variables}} present in a template body.
     */
    public static function extractVariables(string $body): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $body, $matches);
        return array_values(array_unique($matches[1]));
    }

    /**
     * Send a message using a local template row. When the template is linked
     * to an approved Meta template (meta_template_name) that one is sent —
     * guaranteed delivery even outside the 24h customer-service window.
     * Otherwise the rendered text body is sent (delivered inside the window only).
     *
     * Returns the Meta API result plus the rendered message for logging.
     */
    public function sendUsingTemplate(WhatsAppTemplate $template, string $to, array $data = [], ?array $media = null): array
    {
        $parts = array_filter([$template->header, $template->body, $template->footer]);
        $message = self::renderTemplate(implode("\n", $parts), $data);

        return $this->sendRouted($to, $message, $template, $media);
    }

    /**
     * Deliver a pre-rendered message through the school's routing rules.
     *
     * Without an attachment: per template Meta link -> school default relay
     * template -> free text.
     *
     * With an attachment: the relay template matching the Meta header format
     * (IMAGE / DOCUMENT) is used so the file arrives as a native WhatsApp
     * attachment. When that template is not configured the message is never
     * dropped — the file URL is appended to the body and the message goes out
     * through the normal text routing above.
     *
     * The returned array carries 'media_mode': 'attachment' when the file was
     * sent natively, 'link' when it was degraded to a URL in the body, null
     * when there was no attachment.
     */
    public function sendRouted(string $to, string $message, ?WhatsAppTemplate $template = null, ?array $media = null): array
    {
        $media = self::normalizeMedia($media);

        if ($media) {
            $relay = $this->mediaRelayTemplate($media['type']);
            if ($relay) {
                $result = $this->sendMediaTemplate($to, $message, $media, $relay);
                $result['message'] = $message;
                $result['media_mode'] = 'attachment';
                return $result;
            }
            // No approved media template for this header format: keep the message
            // deliverable by handing the recipient the file URL instead.
            $message = self::appendMediaLink($message, $media);

            if ($template && !empty($template->meta_template_name) && !$template->meta_body_param) {
                // That Meta template delivers its own fixed approved content, so
                // the download link would never reach the recipient. Route through
                // the school relay / free text instead.
                $template = null;
            }
        }

        $result = $this->sendTextRouted($to, $message, $template);
        $result['message'] = $message;
        $result['media_mode'] = $media ? 'link' : null;
        return $result;
    }

    /**
     * Text-only routing: per template Meta link -> school default relay
     * template -> free text (24h window only).
     */
    private function sendTextRouted(string $to, string $message, ?WhatsAppTemplate $template = null): array
    {
        if ($template && !empty($template->meta_template_name)) {
            // Per template Meta link (overrides the school default relay)
            $components = [];
            if ($template->meta_body_param) {
                // Deliver the school's own rendered text through the Meta template's
                // {{1}} body parameter. Meta rejects newlines/tabs inside parameters,
                // so the message is flattened to a single line.
                $components[] = [
                    'type'       => 'body',
                    'parameters' => [['type' => 'text', 'text' => self::sanitizeTemplateParam($message)]],
                ];
            }
            $result = $this->sendTemplate($to, $template->meta_template_name, $template->language ?: 'en_US', $components);
        } elseif (!empty($this->settings->default_meta_template)) {
            // School wide relay template: ONE approved Meta template with a {{1}}
            // body variable delivers every panel-managed message.
            $components = [[
                'type'       => 'body',
                'parameters' => [['type' => 'text', 'text' => self::sanitizeTemplateParam($message)]],
            ]];
            $result = $this->sendTemplate($to, $this->settings->default_meta_template, $this->settings->default_meta_language ?: 'en_US', $components);
        } else {
            $result = $this->sendText($to, $message);
        }

        $result['message'] = $message;
        return $result;
    }

    /**
     * Deliver a message WITH a native attachment through the relay template of
     * the matching Meta header format. The relay templates carry a {{1}} body
     * variable, so the panel-rendered text is always delivered with the file.
     *
     * @param array $media ['type' => 'document'|'image', 'url' => string, 'filename' => string|null]
     * @param array $relay ['name' => string, 'language' => string]
     */
    private function sendMediaTemplate(string $to, string $message, array $media, array $relay): array
    {
        if ($media['type'] === 'image') {
            $headerParameter = ['type' => 'image', 'image' => ['link' => $media['url']]];
        } else {
            $headerParameter = ['type' => 'document', 'document' => ['link' => $media['url']]];
            if (!empty($media['filename'])) {
                // What the recipient sees as the file name in the chat
                $headerParameter['document']['filename'] = $media['filename'];
            }
        }

        return $this->sendTemplate($to, $relay['name'], $relay['language'], [
            ['type' => 'header', 'parameters' => [$headerParameter]],
            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => self::sanitizeTemplateParam($message)]]],
        ]);
    }

    /**
     * The approved Meta relay template for an attachment type, or null when the
     * school has not configured one yet (caller then falls back to a link).
     *
     * A Meta template header format is fixed when the template is created and
     * cannot be changed per message, hence one relay template per format.
     *
     * @return array{name:string, language:string}|null
     */
    private function mediaRelayTemplate(string $type): ?array
    {
        $name = $type === 'image'
            ? $this->settings->default_meta_image_template
            : $this->settings->default_meta_media_template;

        if (empty($name)) {
            return null;
        }
        return ['name' => $name, 'language' => $this->settings->default_meta_language ?: 'en_US'];
    }

    /**
     * Resolve the attachment descriptor into a canonical shape. The header
     * format Meta expects follows the real file extension, never the caller's
     * label — a .pdf handed over as 'image' would be rejected by Meta.
     *
     * @return array{type:string, url:string, filename:string|null}|null
     */
    public static function normalizeMedia(?array $media): ?array
    {
        if (empty($media['url'])) {
            return null;
        }

        $filename = trim((string) ($media['filename'] ?? ''));
        if ($filename === '') {
            $filename = basename((string) parse_url($media['url'], PHP_URL_PATH));
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension !== '') {
            $type = in_array($extension, self::IMAGE_EXTENSIONS, true) ? 'image' : 'document';
        } else {
            // Extension-less URL: trust the caller, default to document
            $type = ($media['type'] ?? 'document') === 'image' ? 'image' : 'document';
        }

        return [
            'type'     => $type,
            'url'      => $media['url'],
            'filename' => $filename !== '' ? $filename : null,
        ];
    }

    /**
     * Fallback used when no media relay template is configured: the file stays
     * reachable through its public URL appended to the message body.
     */
    public static function appendMediaLink(string $message, array $media): string
    {
        $label = $media['type'] === 'image' ? trans('view_image') : trans('download_attachment');
        return rtrim($message) . "\n" . $label . ': ' . $media['url'];
    }

    /**
     * Remove the log-only attachment marker so a retry never delivers it as text.
     */
    public static function stripAttachmentMarker(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }
        return rtrim(preg_replace('/\R📎 .*$/u', '', $message));
    }

    /**
     * The active local template of the school for an automatic event.
     */
    public static function templateForEvent(int $schoolId, string $event): ?WhatsAppTemplate
    {
        return WhatsAppTemplate::where('school_id', $schoolId)
            ->where('event', $event)
            ->where('status', 1)
            ->first();
    }

    /* =====================================================
     |  Helpers
     ===================================================== */

    /**
     * Realistic sample values used when testing a template from the panel,
     * so the rendered message looks like a real event message.
     */
    public static function sampleVariableData(array $variables, string $schoolName = ''): array
    {
        $samples = [
            'school_name'    => $schoolName ?: 'Demo School',
            'student_name'   => 'Aarav Sharma',
            'parent_name'    => 'Ramesh Sharma',
            'teacher_name'   => 'Priya Patel',
            'recipient_name' => 'Ramesh Sharma',
            'class'          => '5 - A',
            'section'        => 'A',
            'amount'         => '1,500',
            'due_date'       => now()->addDays(7)->format('d-m-Y'),
            'date'           => now()->format('d-m-Y'),
            'from_date'      => now()->format('d-m-Y'),
            'to_date'        => now()->addDays(2)->format('d-m-Y'),
            'exam_name'      => 'Mid Term Examination',
            'result'         => 'Passed with 82%',
            'subject'        => 'Mathematics',
            'holiday_name'   => 'Diwali',
            'gr_number'      => 'GR-1024',
        ];

        $data = [];
        foreach ($variables as $variable) {
            $data[$variable] = $samples[$variable] ?? ('[' . $variable . ']');
        }
        return $data;
    }

    /**
     * Meta template parameters must not contain newlines, tabs or 4+ spaces.
     */
    public static function sanitizeTemplateParam(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Meta expects the number in international format without +, spaces or dashes.
     */
    public static function normalizeMobile(string $mobile): string
    {
        return preg_replace('/[^0-9]/', '', $mobile);
    }

    /**
     * Meta template languages are codes like "en", "en_US" or "hi", but admins
     * tend to type language names ("English") or web locales ("en-us").
     * Returns the corrected code, or null when the value cannot be one.
     */
    public static function normalizeLanguageCode(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $names = [
            'english'   => 'en',
            'hindi'     => 'hi',
            'gujarati'  => 'gu',
            'marathi'   => 'mr',
            'bengali'   => 'bn',
            'tamil'     => 'ta',
            'telugu'    => 'te',
            'kannada'   => 'kn',
            'malayalam' => 'ml',
            'punjabi'   => 'pa',
            'urdu'      => 'ur',
            'arabic'    => 'ar',
            'spanish'   => 'es',
            'french'    => 'fr',
            'german'    => 'de',
            'indonesian' => 'id',
        ];
        $lower = strtolower($value);
        if (isset($names[$lower])) {
            return $names[$lower];
        }

        if (!preg_match('/^([a-z]{2,3})(?:[_-]([a-z]{2}))?$/i', $value, $matches)) {
            return null;
        }
        return strtolower($matches[1]) . (empty($matches[2]) ? '' : '_' . strtoupper($matches[2]));
    }

    /**
     * WhatsApp fee receipt: generates the paid-fees PDF, publishes it for the
     * Meta API to fetch, and raises the fee_paid event with the document
     * attached. Never breaks the payment flow.
     */
    public function sendFeePaidWhatsApp($feesId, $studentUserId, $amount)
    {
        try {
            $student = Students::with('user:id,first_name,last_name', 'class_section.class', 'class_section.section')
                ->where('user_id', $studentUserId)->with('school')->first();
            if (!$student || !$student->guardian_id) {
                return;
            }

            $userService = app(UserService::class);
            $cache = app(CachingService::class);

            $schoolSettings = $cache->getSchoolSettings('*', $student->school_id);

            // Attach the receipt PDF when it can be generated
            $media = null;
            $receipt = $userService->generateSchoolFeesReceiptPDF($feesId, $studentUserId, 'whatsapp');
            if (is_array($receipt) && empty($receipt['error']) && !empty($receipt['pdf'])) {
                // Unguessable storage name, human readable name in the chat
                $storedName = 'fee-receipt-' . $feesId . '-' . $studentUserId . '-' . Str::random(24) . '.pdf';
                $path = 'whatsapp/receipts/' . $student->school_id . '/' . $storedName;
                Storage::disk('public')->put($path, base64_decode($receipt['pdf']));
                $media = [
                    'type'     => 'document',
                    'url'      => url(Storage::url($path)),
                    'filename' => 'Fee-Receipt-' . Str::slug($student->user->full_name ?? 'student') . '-' . now()->format('d-m-Y') . '.pdf',
                ];
            }

            NotificationService::sendToUsers('fee_paid', [$student->guardian_id], [
                'student_name' => trim($student->user->full_name ?? ''),
                'class'        => $student->class_section->full_name ?? '',
                'amount'       => trim(($schoolSettings['currency_symbol'] ?: '') . ' ' . $amount),
                'school_name'  => $schoolSettings['school_name'] ?? '',
            ], $student->school, null, $media);
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Fee paid WhatsApp notification failed: ' . $e->getMessage());
        }
    }
}
