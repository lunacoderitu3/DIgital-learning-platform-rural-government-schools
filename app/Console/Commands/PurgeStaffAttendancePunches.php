<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\StaffAttendancePunch;
use App\Services\StaffAttendance\QrAttendanceSettingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Clears raw punch in / punch out rows once they are older than the retention
 * window.
 *
 * Only the raw scan log is removed. Finalized attendance — status, working
 * minutes, shift snapshot and every correction — is never deleted, so all
 * attendance reports stay complete.
 */
class PurgeStaffAttendancePunches extends Command
{
    protected $signature = 'staff-attendance:purge-punches
                            {--months= : Retention window in months}
                            {--school= : Limit to one school id}';

    protected $description = 'Delete staff QR punch records older than the retention window';

    public function handle(): int
    {
        $months = (int) ($this->option('months') ?: QrAttendanceSettingService::PUNCH_RETENTION_MONTHS);
        $cutoff = now()->subMonths(max(1, $months))->toDateString();

        $schools = School::on('mysql')
            ->where('installed', 1)
            ->when($this->option('school'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        $totalDeleted = 0;

        foreach ($schools as $school) {
            try {
                $this->connectToSchool($school->database_name);

                // Chunked so a large school never builds one huge delete.
                $deleted = 0;
                do {
                    $batch = StaffAttendancePunch::where('school_id', $school->id)
                        ->whereDate('date', '<', $cutoff)
                        ->limit(1000)
                        ->delete();
                    $deleted += $batch;
                } while ($batch > 0);

                $totalDeleted += $deleted;

                if ($deleted > 0) {
                    $this->line(sprintf('[%s] %d punch rows older than %s removed.', $school->name, $deleted, $cutoff));
                }
            } catch (\Throwable $e) {
                Log::error('Staff QR punch retention job failed for a school.', [
                    'school_id' => $school->id,
                    'operation' => 'purge_punches',
                    'cutoff'    => $cutoff,
                    'exception' => $e,
                ]);
                $this->error(sprintf('[%s] %s', $school->name, $e->getMessage()));
            } finally {
                DB::setDefaultConnection('mysql');
                DB::purge('school');
            }
        }

        $this->info(sprintf('Retention finished. %d punch rows removed.', $totalDeleted));

        return self::SUCCESS;
    }

    private function connectToSchool(string $databaseName): void
    {
        Config::set('database.connections.school.database', $databaseName);
        DB::purge('school');
        DB::connection('school')->reconnect();
        DB::setDefaultConnection('school');
    }
}
