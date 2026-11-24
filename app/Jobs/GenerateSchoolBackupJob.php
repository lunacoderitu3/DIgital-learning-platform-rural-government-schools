<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\DatabaseBackupService;
use App\Models\DatabaseBackup;

class GenerateSchoolBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour

    protected $schoolId;
    protected $backupRecord;

    /**
     * Create a new job instance.
     */
    public function __construct(int $schoolId, DatabaseBackup $backupRecord)
    {
        $this->schoolId = $schoolId;
        $this->backupRecord = $backupRecord;
    }

    /**
     * Execute the job.
     */
    public function handle(DatabaseBackupService $backupService): void
    {
        $backupService->schoolAdminBackup($this->schoolId, $this->backupRecord);
    }
}
