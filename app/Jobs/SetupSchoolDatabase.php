<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\SchoolProvisionProgressUpdated;
use App\Models\School;
use App\Services\SchoolDataService;
use App\Services\SubscriptionService;
use App\Services\CachingService;
use App\Repositories\SystemSetting\SystemSettingInterface;
use App\Services\GeneralFunctionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use PDO;

final class SetupSchoolDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;
    public int $backoff = 120;

    private array $currentProgress = [];

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly int     $schoolId,
        private readonly ?int    $packageId = null,
        private readonly ?string $schoolCodePrefix = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        SchoolDataService    $schoolService,
        SubscriptionService  $subscriptionService,
        CachingService       $cache,
        SystemSettingInterface $systemSettings
    ): void {
        DB::setDefaultConnection('mysql');

        // Get school data and reset progress
        $school = School::withTrashed()->whereKey($this->schoolId)->lockForUpdate()->firstOrFail();

        if (($school->installed ?? false) && ($school->provision_status ?? null) === 'completed') {
            return;
        }

        $this->recordRetryState($school);
        $this->updateProvisionState($school, 'pending', 0, 'Provisioning started.');

        try {
            $this->updateProvisionState($school, 'creating_database', 10, 'Creating tenant database...');
            $this->ensureSchoolDatabase($school);

            $this->updateProvisionState($school, 'migrating', 35, 'Running tenant migrations...');
            $schoolService->createDatabaseMigration($school);

            $this->updateProvisionState($school, 'seeding', 55, 'Seeding roles, permissions and default settings...');
            $schoolService->preSettingsSetup($school);

            // ── Step: Update School Code Prefix ───────────────────────────
            if ($this->schoolCodePrefix) {
                $settings = $cache->getSystemSettings();
                if (($settings['school_prefix'] ?? '') != $this->schoolCodePrefix) {
                    $settingsData = [[
                        'name' => 'school_prefix',
                        'data' => $this->schoolCodePrefix,
                        'type' => 'text',
                    ]];
                    $systemSettings->upsert($settingsData, ['name'], ['data']);
                    $cache->removeSystemCache(config('constants.CACHE.SYSTEM.SETTINGS'));
                }
            }

            // ── Step: Activate School ─────────────────────────────────────
            // (We set installed=1 at the very end to ensure everything is ready)

            DB::setDefaultConnection('school');
            Config::set('database.connections.school.database', $school->database_name);
            DB::purge('school');
            DB::connection('school')->reconnect();
            DB::setDefaultConnection('school');

            $this->updateProvisionState($school, 'creating_admin', 75, 'Creating tenant administrator account...');
            $this->ensureSchoolAdminRecord($school);

            $this->updateProvisionState($school, 'configuring_tenant', 90, 'Verifying tenant configuration...');
            $this->verifyTenantSetup($school);

            DB::setDefaultConnection('mysql');
            $school = School::withTrashed()->with('user')->findOrFail($this->schoolId);
            $this->updateProvisionState($school, 'completed', 100, 'School provisioned successfully.');

            School::on('mysql')->withTrashed()->whereKey($this->schoolId)->update([
                'installed' => 1,
                'status' => 1,
                'provision_status' => 'completed',
                'provision_completed_at' => now(),
                'provision_last_error' => null,
            ]);

            $this->broadcastStep($school, 'completed', 'completed', 'School provisioned successfully!');

            $settings = $cache->getSystemSettings();
            $emailBody = $this->replacePlaceholders($school, $school->user, $settings, $school->code);
            $data = [
                'subject' => 'Welcome to ' . ($settings['system_name'] ?? 'eSchool Saas'),
                'email' => $school->support_email,
                'email_body' => $emailBody,
            ];

            Mail::send('schools.email', $data, static function ($message) use ($data) {
                $message->to($data['email'])->subject($data['subject']);
            });

            if ($school->user && !$school->user->hasVerifiedEmail()) {
                $school->user->sendEmailVerificationNotification();
            }
        } catch (Throwable $e) {
            $message = app(GeneralFunctionService::class)->getMessage($e) ?: $e->getMessage();
            Log::error("School database setup failed for school ID: {$this->schoolId}", [
                'error' => $message,
                'trace' => $e->getTraceAsString(),
            ]);

            $this->markProvisionFailure($school, $message, $e);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        $school = School::withTrashed()->whereKey($this->schoolId)->first();
        if ($school) {
            $school->forceFill([
                'provision_status' => 'failed',
                'provision_last_error' => $exception->getMessage(),
            ])->save();
        }

        Log::error("School database setup job failed permanently for school ID: {$this->schoolId}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────

    private function ensureSchoolDatabase(School $school): void
    {
        $host = env('DB_HOST');
        $port = env('DB_PORT');
        $username = env('DB_USERNAME');
        $password = env('DB_PASSWORD', '');

        $databaseName = trim((string) ($school->database_name ?? ''));
        if ($databaseName === '') {
            throw new \RuntimeException('Tenant database name is missing.');
        }

        $pdo = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}`");

        $schemaCheck = DB::select('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$databaseName]);
        if (empty($schemaCheck)) {
            throw new \RuntimeException("Tenant database {$databaseName} was not created successfully.");
        }
    }

    private function ensureSchoolAdminRecord(School $school): void
    {
        if (!$school->admin_id) {
            throw new \RuntimeException('School admin identifier is missing.');
        }

        $admin = \App\Models\User::on('school')->whereKey($school->admin_id)->first();
        if (!$admin) {
            throw new \RuntimeException('School administrator record could not be verified in the tenant database.');
        }
    }

    private function verifyTenantSetup(School $school): void
    {
        DB::setDefaultConnection('school');
        Config::set('database.connections.school.database', $school->database_name);
        DB::purge('school');
        DB::connection('school')->reconnect();

        $requiredTables = ['users', 'roles', 'permissions', 'model_has_roles'];
        foreach ($requiredTables as $table) {
            if (!DB::connection('school')->getSchemaBuilder()->hasTable($table)) {
                throw new \RuntimeException("Required tenant table {$table} is missing for school {$school->id}.");
            }
        }

        $hasAdmin = DB::connection('school')->table('users')->where('id', $school->admin_id)->exists();
        if (!$hasAdmin) {
            throw new \RuntimeException("Tenant admin user for school {$school->id} is missing.");
        }
    }

    private function recordRetryState(School $school): void
    {
        $count = (int) ($school->provision_retry_count ?? 0);

        $school->forceFill([
            'provision_status' => $count > 0 ? 'pending' : 'pending',
            'provision_started_at' => $school->provision_started_at ?? now(),
            'provision_retry_count' => $count + 1,
            'provision_last_retry_at' => now(),
            'provision_last_error' => null,
        ])->save();
    }

    private function updateProvisionState(School $school, string $step, int $progressPercent, string $message): void
    {
        $stepInfo = School::STEPS[$step] ?? ['label' => $step, 'progress' => $progressPercent];
        $this->currentProgress[$step] = true;

        School::on('mysql')->withTrashed()->whereKey($school->id)->update([
            'progress' => json_encode($this->currentProgress),
            'provision_step' => $step,
            'provision_status' => $step,
            'provision_progress' => $progressPercent,
            'provision_last_error' => $message,
            'provision_started_at' => $school->provision_started_at ?? now(),
        ]);

        event(new SchoolProvisionProgressUpdated(
            schoolId: $school->id,
            schoolName: $school->name,
            step: $step,
            stepLabel: $stepInfo['label'],
            progress: $progressPercent,
            status: 'installing',
            message: $message,
        ));
    }

    private function markProvisionFailure(School $school, string $errorMessage, Throwable $exception): void
    {
        $lastStep = $school->provision_step ?: 'pending';
        $lastProgress = (int) ($school->provision_progress ?? ($school->progress && is_array($school->progress) ? max(array_values($school->progress)) : 0));

        School::on('mysql')->withTrashed()->whereKey($school->id)->update([
            'provision_step' => 'failed',
            'provision_status' => 'failed',
            'provision_progress' => max(0, $lastProgress),
            'provision_last_error' => $errorMessage,
            'status' => 0,
            'installed' => 0,
        ]);

        $this->broadcastFailure($school, $errorMessage, $lastStep);
    }

    private function broadcastFailure(School $school, string $errorMessage, string $lastStep): void
    {
        $stepInfo = School::STEPS[$lastStep] ?? ['label' => 'Failed', 'progress' => 0];

        event(new SchoolProvisionProgressUpdated(
            schoolId: $school->id,
            schoolName: $school->name,
            step: 'failed',
            stepLabel: 'Setup Failed',
            progress: $stepInfo['progress'],
            status: 'failed',
            message: $errorMessage,
        ));
    }

    private function broadcastStep(School $school, string $stepKey, string $status, string $message): void
    {
        $stepInfo = School::STEPS[$stepKey] ?? ['label' => $stepKey, 'progress' => 0];
        event(new SchoolProvisionProgressUpdated(
            schoolId: $school->id,
            schoolName: $school->name,
            step: $stepKey,
            stepLabel: $stepInfo['label'],
            progress: $stepInfo['progress'],
            status: $status,
            message: $message,
        ));
    }

    private function replacePlaceholders($school, $user, $settings, $schoolCode): string
    {
        $templateContent = $settings['email_template_school_registration'] ?? '';
        $placeholders = [
            '{school_admin_name}' => $user?->full_name ?? $school->name,
            '{code}' => $schoolCode,
            '{email}' => $user?->email ?? $school->support_email,
            '{password}' => $user?->mobile ?? '',
            '{school_name}' => $school->name,
            '{super_admin_name}' => $settings['super_admin_name'] ?? 'Super Admin',
            '{support_email}' => $settings['mail_username'] ?? '',
            '{contact}' => $settings['mobile'] ?? '',
            '{system_name}' => $settings['system_name'] ?? 'eSchool Saas',
            '{url}' => url('/'),
        ];

        foreach ($placeholders as $placeholder => $replacement) {
            $templateContent = str_replace($placeholder, $replacement, $templateContent);
        }

        return $templateContent;
    }
}
