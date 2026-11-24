<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\School;
use App\Models\WhatsAppLog;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * @param School         $school The school ID from the main database
     * @param string      $mobile   Recipient mobile in international format
     * @param int|null    $userId   Recipient user id in the school database
     * @param string|null $event    Template event key (null = free text message)
     * @param array       $data     Template variables
     * @param string|null $rawMessage Pre-rendered message (used when $event is null)
     */
    public function __construct(
        private readonly School $school,
        private readonly string $mobile,
        private readonly ?int $userId,
        private readonly ?string $event,
        private readonly array $data = [],
        private readonly ?string $rawMessage = null,
        private readonly ?string $batchId = null,
        private readonly ?int $logId = null,
        private readonly ?array $media = null
    ) {}

    public function handle(): void
    {
        $schoolId = $this->school->id;
        // Ensure we start with main database connection
        DB::setDefaultConnection('mysql');

        try {
            if (!$this->school || empty($this->school->database_name)) {
                return;
            }

            // Switch to the school database
            Config::set('database.connections.school.database', $this->school->database_name);
            DB::purge('school');
            DB::connection('school')->reconnect();
            DB::setDefaultConnection('school');

            $service = WhatsAppService::forSchool($schoolId);
            if (!$service || !$service->isEnabled()) {
                // A retry must never stay stuck on "pending" when sending is impossible
                if ($this->logId) {
                    $this->writeLog(['status' => 'failed', 'response' => 'WhatsApp is not enabled.']);
                }
                return;
            }

            // Resolve the template / message. Retries carry the already rendered
            // message (rawMessage) and use the template only for delivery routing.
            $template = null;
            $templateName = null;
            $message = $this->rawMessage;
            if ($this->event) {
                $template = WhatsAppService::templateForEvent($schoolId, $this->event);
                if (!$template && empty($message)) {
                    return;
                }
                $templateName = $template->name ?? null;
            } elseif (empty($message)) {
                return;
            }

            // Monthly limit enforcement
            if (!$service->canSend()) {
                $this->writeLog([
                    'template_name' => $templateName,
                    'message'       => $message ?: ($template->body ?? null),
                    'status'        => 'failed',
                    'response'      => 'Monthly WhatsApp limit reached.',
                ]);
                return;
            }

            if (!empty($message)) {
                $result = $service->sendRouted($this->mobile, $message, $template, $this->media);
            } else {
                $result = $service->sendUsingTemplate($template, $this->mobile, $this->data, $this->media);
            }
            // The routed message already carries the download link when the send
            // degraded to link mode, so the file marker is only added for a real
            // native attachment.
            $message = $result['message'] ?? $message;
            if (($result['media_mode'] ?? null) === 'attachment') {
                $message .= WhatsAppService::ATTACHMENT_LOG_MARKER . ($this->media['filename'] ?? basename($this->media['url']));
            }

            $this->writeLog([
                'template_name'   => $templateName,
                'message'         => $message,
                'meta_message_id' => $result['meta_message_id'] ?? null,
                'status'          => $result['success'] ? 'sent' : 'failed',
                'response'        => $result['success'] ? null : ($result['error'] ?? 'Unknown error'),
            ]);

            if ($result['success']) {
                $service->incrementUsage();
            }
        } catch (Throwable $e) {
            Log::error('SendWhatsAppJob failed: ' . $e->getMessage(), [
                'school_id' => $schoolId,
                'event'     => $this->event,
            ]);
            throw $e; // let the queue retry
        } finally {
            // Always restore the main connection for the next job on this worker
            DB::setDefaultConnection('mysql');
        }
    }

    /**
     * Create the log row — or, on retries (logId set), update the original
     * row in place so a message keeps a single history entry.
     */
    private function writeLog(array $attributes): void
    {
        if ($this->logId) {
            $log = WhatsAppLog::find($this->logId);
            if ($log) {
                $log->update($attributes);
                return;
            }
        }
        WhatsAppLog::create(array_merge([
            'school_id' => $this->school->id,
            'user_id'   => $this->userId,
            'mobile'    => $this->mobile,
            'event'     => $this->event,
            'batch_id'  => $this->batchId,
        ], $attributes));
    }
}
