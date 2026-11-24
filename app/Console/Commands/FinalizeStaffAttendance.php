<?php

namespace App\Console\Commands;

use App\Jobs\SendMissingPunchOutReport;
use App\Models\School;
use App\Models\StaffAttendanceReportLog;
use App\Services\StaffAttendance\AttendanceFinalizationService;
use App\Services\StaffAttendance\QrAttendanceSettingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Closes the staff attendance day for every school whose finalization time has
 * passed.
 *
 * Runs every minute because schools finish at different times. Each school is
 * processed against its own database and its own school-end setting; a failure
 * in one school never stops the rest.
 */
class FinalizeStaffAttendance extends Command
{
    protected $signature = 'staff-attendance:finalize
                            {--school= : Limit to one school id}
                            {--date= : Attendance date (Y-m-d), defaults to today}
                            {--force : Ignore the due-time check}';

    protected $description = 'Finalize QR staff attendance for schools whose school end time has passed';

    public function handle(AttendanceFinalizationService $finalization, QrAttendanceSettingService $settings): int
    {
        $requestedDate = $this->option('date');
        $force         = (bool) $this->option('force');

        $schools = School::on('mysql')
            ->where('status', 1)
            ->where('installed', 1)
            ->when($this->option('school'), fn($q, $id) => $q->where('id', $id))
            ->get();

        $processedSchools = 0;

        foreach ($schools as $school) {
            $date = $requestedDate;

            try {
                $this->connectToSchool($school->database_name);

                if (!$settings->isEnabled($school->id)) {
                    continue;
                }

                // Today is resolved per school, so a school whose local date has
                // not rolled over yet still finalizes the right day.
                $date = $requestedDate ?: schoolNow($school->id)->toDateString();

                if (!$force && !$finalization->isDue($school->id, $date)) {
                    continue;
                }

                $summary = $finalization->finalizeSchool($school->id, $date);
                $processedSchools++;

                $this->line(sprintf(
                    '[%s] %s — %d processed, %d present, %d half day, %d absent, %d skipped, %d missing punch out',
                    $school->name,
                    $date,
                    $summary['processed'],
                    $summary['present'],
                    $summary['half_day'],
                    $summary['absent'],
                    $summary['skipped'],
                    count($summary['missing_punch_out'])
                ));

                // Finalization is due on every run from school end + 1 hour
                // until midnight, so the report is dispatched only while no
                // send has been recorded for the school on this date. The job
                // claims the same row again before mailing, which is what
                // actually settles a race between workers.
                if (!empty($summary['missing_punch_out'])
                    && !StaffAttendanceReportLog::alreadyHandled($school->id, $date)) {
                    // Queued so SMTP latency never blocks finalization.
                    SendMissingPunchOutReport::dispatch(
                        $school->id,
                        $date,
                        $summary['missing_punch_out']
                    );
                }
            } catch (\Throwable $e) {
                // One school's failure must not stop the others.
                Log::error('Staff QR attendance finalization failed for a school.', [
                    'school_id' => $school->id,
                    'date'      => $date,
                    'operation' => 'finalize_school',
                    'exception' => $e,
                ]);
                $this->error(sprintf('[%s] %s', $school->name, $e->getMessage()));
            } finally {
                $this->releaseSchoolConnection();
            }
        }

        $this->info(sprintf('Finalization finished for %d school(s).', $processedSchools));

        return self::SUCCESS;
    }

    private function connectToSchool(string $databaseName): void
    {
        Config::set('database.connections.school.database', $databaseName);
        DB::purge('school');
        DB::connection('school')->reconnect();
        DB::setDefaultConnection('school');
    }

    /**
     * Never leave a school connection active while the next school is picked up.
     */
    private function releaseSchoolConnection(): void
    {
        DB::setDefaultConnection('mysql');
        DB::purge('school');
    }
}
