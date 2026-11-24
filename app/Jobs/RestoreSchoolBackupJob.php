<?php

namespace App\Jobs;

use App\Services\DatabaseBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RestoreSchoolBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour

    protected $zipFilePath;
    protected $schoolId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $zipFilePath, int $schoolId)
    {
        $this->zipFilePath = $zipFilePath;
        $this->schoolId = $schoolId;
    }

    /**
     * Execute the job.
     */
    public function handle(DatabaseBackupService $backupService): void
    {
        $backupService->restoreSchoolAdminBackup($this->zipFilePath, $this->schoolId);
    }
}
