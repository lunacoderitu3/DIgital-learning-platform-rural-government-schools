<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\DatabaseBackupService;
use App\Models\DatabaseBackup;

class GenerateSuperAdminBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour

    protected $userId;
    protected $backupRecord;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, DatabaseBackup $backupRecord)
    {
        $this->userId = $userId;
        $this->backupRecord = $backupRecord;
    }

    /**
     * Execute the job.
     */
    public function handle(DatabaseBackupService $backupService): void
    {
        $backupService->superAdminBackup($this->userId, $this->backupRecord);
    }
}
