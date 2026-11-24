<?php

namespace App\Jobs;

use App\Services\DatabaseBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RestoreSuperAdminBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour

    protected $zipFilePath;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $zipFilePath, int $userId)
    {
        $this->zipFilePath = $zipFilePath;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(DatabaseBackupService $backupService): void
    {
        $backupService->restoreSuperAdminBackup($this->zipFilePath, $this->userId);
    }
}
