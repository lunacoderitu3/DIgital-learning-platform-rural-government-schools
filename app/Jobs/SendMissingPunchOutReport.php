<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\School;
use App\Models\StaffAttendanceReportLog;
use App\Models\User;
use App\Services\CachingService;
use App\Services\StaffAttendance\QrAttendanceSettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * One consolidated email per school per day listing the staff members whose
 * punch in never got a matching punch out.
 *
 * Queued, so SMTP latency never blocks finalization. No automatic punch out is
 * ever created — the admin resolves each case by hand.
 *
 * Finalization is due every minute from school end + 1 hour until midnight, so
 * this job would otherwise mail the same report on every run. A row in
 * staff_attendance_report_logs, claimed before the send, keeps it to one email
 * per school per date; the claim is released again if the send fails so a
 * retry can still deliver it.
 */
final class SendMissingPunchOutReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $backoff = 120;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        private readonly int $schoolId,
        private readonly string $date,
        private readonly array $rows
    ) {}

    public function handle(CachingService $cache, QrAttendanceSettingService $settings): void
    {
        if (empty($this->rows)) {
            return;
        }

        DB::setDefaultConnection('mysql');

        try {
            $school = School::on('mysql')->find($this->schoolId);
            if (!$school) {
                Log::error('Missing punch out report skipped, school not found.', ['school_id' => $this->schoolId]);
                return;
            }

            $this->connectToSchool($school->database_name);

            $config = $settings->all($this->schoolId);
            if ((int) $config[QrAttendanceSettingService::NOTIFY_MPO] !== 1) {
                return;
            }

            $recipients = $this->recipients($config[QrAttendanceSettingService::NOTIFY_EMAILS]);
            if (empty($recipients)) {
                Log::warning('Missing punch out report has no recipients.', ['school_id' => $this->schoolId]);
                return;
            }

            $schoolSettings = $cache->getSchoolSettings('*', $this->schoolId);
            if ($schoolSettings instanceof \Illuminate\Support\Collection) {
                $schoolSettings = $schoolSettings->toArray();
            }

            $schoolName = $schoolSettings['school_name'] ?? $school->name;

            // Once-per-school-per-date guard. Claimed before the send so a
            // second run — or a second worker — finds the row and stops.
            $log = $this->claimReport($recipients);
            if (!$log) {
                Log::info('Missing punch out report skipped, already sent for this date.', [
                    'school_id' => $this->schoolId,
                    'date'      => $this->date,
                ]);
                return;
            }

            $data = [
                'school_name' => $schoolName,
                'date'        => $this->date,
                'rows'        => $this->withStaffDetails(),
            ];

            $subject = trans('missing_punch_out_report_subject', [
                'date' => $this->date,
            ]);

            try {
                Mail::send('emails.missing_punch_out_report', $data, static function ($message) use ($recipients, $subject) {
                    $message->to($recipients)->subject($subject);
                });
            } catch (Throwable $e) {
                // Release the claim, otherwise a retry would see the day as
                // already reported and the email would never go out.
                $log->delete();
                throw $e;
            }

            $log->update(['sent_at' => schoolNow($this->schoolId)]);

            Log::info('Missing punch out report sent.', [
                'school_id' => $this->schoolId,
                'date'      => $this->date,
                'staff'     => count($this->rows),
            ]);
        } catch (Throwable $e) {
            Log::error('Missing punch out report failed.', [
                'school_id' => $this->schoolId,
                'date'      => $this->date,
                'operation' => 'missing_punch_out_report',
                'exception' => $e,
            ]);

            throw $e;
        } finally {
            DB::setDefaultConnection('mysql');
            DB::purge('school');
        }
    }

    /**
     * Take ownership of today's report, or return null when someone already
     * has it.
     *
     * The unique index on (school_id, date, report_type) is the real guard:
     * even if two workers pass the existence check at the same instant, only
     * one insert survives. No cleanup is needed for the next day — a new date
     * simply has no row yet.
     *
     * @param  array<int, string>  $recipients
     */
    private function claimReport(array $recipients): ?StaffAttendanceReportLog
    {
        $existing = StaffAttendanceReportLog::query()
            ->forReport($this->schoolId, $this->date)
            ->first();

        if ($existing) {
            return $this->takeOverStaleClaim($existing, $recipients);
        }

        try {
            return StaffAttendanceReportLog::create([
                'school_id'   => $this->schoolId,
                'date'        => $this->date,
                'report_type' => StaffAttendanceReportLog::TYPE_MISSING_PUNCH_OUT,
                'staff_count' => count($this->rows),
                'recipients'  => implode(', ', $recipients),
                'sent_at'     => null,
            ]);
        } catch (QueryException $e) {
            // Duplicate key: another worker claimed it a moment ago.
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * A row already exists. It is reusable only when it is an abandoned claim —
     * nothing delivered, older than the stale window. The conditional update is
     * what settles the takeover: the second worker's WHERE no longer matches.
     *
     * @param  array<int, string>  $recipients
     */
    private function takeOverStaleClaim(StaffAttendanceReportLog $existing, array $recipients): ?StaffAttendanceReportLog
    {
        if ($existing->sent_at !== null) {
            return null;
        }

        $cutoff = now()->subMinutes(StaffAttendanceReportLog::STALE_CLAIM_MINUTES);

        $taken = StaffAttendanceReportLog::query()
            ->whereKey($existing->getKey())
            ->whereNull('sent_at')
            ->where('created_at', '<', $cutoff)
            ->update([
                'created_at'  => now(),
                'updated_at'  => now(),
                'staff_count' => count($this->rows),
                'recipients'  => implode(', ', $recipients),
            ]);

        if ($taken === 0) {
            return null;
        }

        Log::warning('Missing punch out report claim was stale, resending.', [
            'school_id' => $this->schoolId,
            'date'      => $this->date,
        ]);

        return $existing->refresh();
    }

    /**
     * Fill in the staff name and type the email needs.
     *
     * @return array<int, array<string, mixed>>
     */
    private function withStaffDetails(): array
    {
        $staffIds = array_column($this->rows, 'staff_id');

        $users = User::with('roles')
            ->whereIn('id', $staffIds)
            ->get()
            ->keyBy('id');

        return array_map(function (array $row) use ($users) {
            $user = $users->get($row['staff_id']);

            $row['staff_name'] = $user?->full_name ?? '—';
            $row['staff_type'] = $user?->roles->pluck('name')->first() ?? '—';

            return $row;
        }, $this->rows);
    }

    /**
     * Configured recipients, falling back to the school admins.
     *
     * @return array<int, string>
     */
    private function recipients(?string $configured): array
    {
        $emails = collect(explode(',', (string) $configured))
            ->map(static fn($email) => trim($email))
            ->filter(static fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();

        if (!empty($emails)) {
            return $emails;
        }

        return User::where('school_id', $this->schoolId)
            ->whereHas('roles', static fn($q) => $q->where('name', 'School Admin'))
            ->pluck('email')
            ->filter()
            ->values()
            ->all();
    }

    private function connectToSchool(string $databaseName): void
    {
        DB::setDefaultConnection('school');
        Config::set('database.connections.school.database', $databaseName);
        DB::purge('school');
        DB::connection('school')->reconnect();
        DB::setDefaultConnection('school');
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Missing punch out report job failed permanently.', [
            'school_id' => $this->schoolId,
            'date'      => $this->date,
            'exception' => $exception,
        ]);
    }
}
