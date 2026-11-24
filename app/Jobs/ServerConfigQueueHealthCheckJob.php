<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ServerConfigQueueHealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $marker = 'server-config-queue-health'
    ) {}

    public function handle(): void
    {
        $path = storage_path('logs/server-config-queue-health.log');
        $payload = '[' . now()->toDateTimeString() . '] queue health check executed successfully.' . PHP_EOL;
        file_put_contents($path, $payload, FILE_APPEND | LOCK_EX);
        Log::info('ServerConfigQueueHealthCheckJob completed.', ['marker' => $this->marker]);
    }
}
