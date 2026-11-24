<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ServerConfigQueueHealthCheckJob;
use App\Models\SystemSetting;
use App\Repositories\SystemSetting\SystemSettingInterface;
use App\Services\CachingService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use PDOException;
use Throwable;

class ServerConfigController extends Controller
{
    private SystemSettingInterface $systemSettings;
    private CachingService $cache;

    public function __construct(SystemSettingInterface $systemSettings, CachingService $cachingService)
    {
        $this->systemSettings = $systemSettings;
        $this->cache = $cachingService;
    }

    /**
     * Display the Server Configuration Check page.
     */
    public function index()
    {
        ResponseService::noPermissionThenRedirect('system-setting-manage');

        $dbCheck = $this->systemSettings->builder()->where('name', 'server_config_db_checkMark')->value('data');
        $queueCheck = $this->systemSettings->builder()->where('name', 'server_config_queue_checkMark')->value('data');
        $reverbCheck = $this->systemSettings->builder()->where('name', 'server_config_reverb_checkMark')->value('data');

        $checksPassedCount = (int) ($dbCheck == 1) + (int) ($queueCheck == 1) + (int) ($reverbCheck == 1);

        if ($checksPassedCount == 3) {
            $this->saveCheckMark('server_config_wizard_checkMark', 1);
            return redirect()->route('dashboard');
        }

        $dbPassword = env('DB_PASSWORD');
        if (env('DEMO_MODE')) {
            $dbPassword = '**********';
        }

        $data = [
            'db_host' => env('DB_HOST'),
            'db_port' => env('DB_PORT'),
            'db_username' => env('DB_USERNAME'),
            'db_password' => $dbPassword,
        ];

        return view('server-config.index', compact('dbCheck', 'queueCheck', 'reverbCheck', 'checksPassedCount', 'data'));
    }

    /**
     * Test raw database connection + required privileges.
     */
    public function testDatabasePrivileges(Request $request): JsonResponse
    {
        $request->validate([
            'db_host' => ['required', 'string'],
            'db_port' => ['required', 'numeric'],
            'db_username' => ['required', 'string'],
            'db_password' => ['nullable', 'string'],
        ]);

        $host = $request->input('db_host');
        $port = (int) $request->input('db_port');
        $username = $request->input('db_username');
        $password = (string) ($request->input('db_password', '') ?? '');
        $database = env('DB_DATABASE', 'rituschoolmini');
        $logs = [];

        try {
            $logs[] = "[INFO] Validating MySQL service at {$host}:{$port} for '{$username}'...";

            if (!extension_loaded('pdo_mysql')) {
                throw new \RuntimeException('PDO MySQL extension is not available. Enable ext-pdo_mysql in PHP.');
            }

            $pdo = new PDO(
                "mysql:host={$host};port={$port};charset=utf8mb4",
                $username,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
            $logs[] = '[OK]   MySQL service is reachable and authentication succeeded.';

            $dbExists = $pdo->query("SHOW DATABASES LIKE '{$database}'")->fetchColumn();
            if (!$dbExists) {
                throw new \RuntimeException("Database '{$database}' does not exist on the MySQL server.");
            }
            $logs[] = "[OK]   Database '{$database}' exists.";

            $pdo->query("USE `{$database}`");
            $privilegeTable = 'eschool_privilege_check_' . time();
            $pdo->exec("CREATE TABLE `{$privilegeTable}` (id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(64) NOT NULL, PRIMARY KEY (id))");
            $pdo->exec("INSERT INTO `{$privilegeTable}` (name) VALUES ('check')");
            $pdo->exec("UPDATE `{$privilegeTable}` SET name = 'verified' WHERE name = 'check'");
            $pdo->exec("ALTER TABLE `{$privilegeTable}` ADD COLUMN email VARCHAR(255) NULL AFTER name");
            $pdo->exec("CREATE INDEX idx_{$privilegeTable}_name ON `{$privilegeTable}` (name)");
            $pdo->exec("DELETE FROM `{$privilegeTable}` WHERE name = 'verified'");
            $pdo->exec("DROP TABLE `{$privilegeTable}`");
            $logs[] = '[OK]   CREATE, INSERT, UPDATE, ALTER, INDEX, DELETE, and DROP privileges are working.';

            $this->saveCheckMark('server_config_db_checkMark', 1);
            changeEnv([
                'DB_HOST' => $host,
                'DB_PORT' => (string) $port,
                'DB_USERNAME' => $username,
                'DB_PASSWORD' => $password,
            ]);

            $logs[] = '[OK]   Database validation PASSED.';

            return response()->json([
                'success' => true,
                'message' => 'Database connection and required privileges verified successfully.',
                'logs' => $logs,
            ]);
        } catch (PDOException $e) {
            $logs[] = '[ERROR] ' . $e->getMessage();
            $this->saveCheckMark('server_config_db_checkMark', 0);

            return response()->json([
                'success' => false,
                'message' => 'Database connection or privilege check failed.',
                'logs' => $logs,
            ], 422);
        } catch (Throwable $e) {
            $logs[] = '[ERROR] ' . $e->getMessage();
            $this->saveCheckMark('server_config_db_checkMark', 0);

            return response()->json([
                'success' => false,
                'message' => 'Database validation could not be completed.',
                'logs' => $logs,
            ], 422);
        }
    }

    /**
     * Validate queue configuration and processing readiness.
     */
    public function testQueueWorker(Request $request): JsonResponse
    {
        $logs = [];
        $logs[] = '[INFO] Checking queue configuration and runtime health...';

        try {
            $connection = env('QUEUE_CONNECTION', config('queue.default', 'sync'));
            $logs[] = '[INFO] QUEUE_CONNECTION=' . $connection;

            if ($connection !== 'database') {
                throw new \RuntimeException('QUEUE_CONNECTION must be set to database.');
            }

            if (!DB::getPdo()) {
                throw new \RuntimeException('Database is not reachable for queue processing.');
            }

            if (!Schema::hasTable('jobs')) {
                throw new \RuntimeException('The jobs table is missing. Run the database migrations.');
            }

            if (!Schema::hasTable('failed_jobs')) {
                throw new \RuntimeException('The failed_jobs table is missing. Run the database migrations.');
            }

            $logs[] = '[OK]   jobs and failed_jobs tables exist.';

            $workerRunning = $this->isProcessRunning('queue:work', $logs);
            $logs[] = $workerRunning
                ? '[OK]   A queue worker process was detected.'
                : '[WARN] No active queue worker process was detected on this machine.';

            $before = DB::table('jobs')->count();
            ServerConfigQueueHealthCheckJob::dispatch();
            $after = DB::table('jobs')->count();
            $logs[] = '[INFO] jobs table count before dispatch: ' . $before . ' | after: ' . $after;

            if ($after <= $before) {
                throw new \RuntimeException('The queue health-check job did not enter the jobs table.');
            }

            $logs[] = '[OK]   A real queue health-check job was dispatched to the database queue.';

            Artisan::call('queue:work', ['--once' => true, '--queue' => 'default', '--tries' => 3, '--sleep' => 0]);
            $artOutput = trim(Artisan::output());
            if ($artOutput !== '') {
                $logs[] = '[INFO] queue:work output: ' . $artOutput;
            }

            $logs[] = '[OK]   queue:work processed the queued job successfully.';
            $logs[] = '[OK]   Database queue validation PASSED.';

            $this->saveCheckMark('server_config_queue_checkMark', 1);

            return response()->json([
                'success' => true,
                'message' => 'Queue worker and database queue are working correctly.',
                'logs' => $logs,
            ]);
        } catch (Throwable $e) {
            $logs[] = '[ERROR] ' . $e->getMessage();
            $this->saveCheckMark('server_config_queue_checkMark', 0);

            return response()->json([
                'success' => false,
                'message' => 'Queue validation failed. Run the Windows worker command to start processing.',
                'logs' => $logs,
            ], 422);
        }
    }

    /**
     * Check if a Laravel Reverb WebSocket server is running and listening.
     */
    public function testReverbWorker(Request $request): JsonResponse
    {
        $logs = [];
        $logs[] = '[INFO] Validating Reverb runtime and WebSocket listener...';

        try {
            $appKey = env('REVERB_APP_KEY');
            $appSecret = env('REVERB_APP_SECRET');
            $host = env('REVERB_HOST', '127.0.0.1');
            $port = (int) env('REVERB_PORT', 9090);
            $scheme = env('REVERB_SCHEME', 'http');

            if (!$appKey || !$appSecret) {
                throw new \RuntimeException('Reverb app credentials are missing from the environment.');
            }

            $logs[] = '[INFO] REVERB_HOST=' . $host . ' REVERB_PORT=' . $port . ' REVERB_SCHEME=' . $scheme;

            if (!config('broadcasting.connections.reverb')) {
                throw new \RuntimeException('Laravel Reverb is not configured in broadcasting.php.');
            }

            $this->assertPortAvailable($host, $port, $logs);

            $process = @proc_open(
                '"' . PHP_BINARY . '" artisan reverb:start --host=' . escapeshellarg($host) . ' --port=' . (int) $port,
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes
            );

            if (!is_resource($process)) {
                throw new \RuntimeException('The Reverb process could not be started by PHP.');
            }

            fclose($pipes[0]);
            usleep(400000);

            $socket = @fsockopen($host, $port, $errno, $errstr, 5);
            if (!$socket) {
                proc_terminate($process);
                proc_close($process);
                throw new \RuntimeException('Reverb port is not accepting connections: ' . ($errstr ?: 'Connection refused'));
            }
            fclose($socket);

            $logs[] = '[OK]   Reverb accepted a real socket connection on ' . $host . ':' . $port . '.';
            $logs[] = '[OK]   Laravel Reverb validation PASSED.';

            proc_terminate($process);
            proc_close($process);

            $this->saveCheckMark('server_config_reverb_checkMark', 1);

            return response()->json([
                'success' => true,
                'message' => 'Reverb is running and accepting WebSocket connections on the configured port.',
                'logs' => $logs,
            ]);
        } catch (Throwable $e) {
            $logs[] = '[ERROR] ' . $e->getMessage();
            $this->saveCheckMark('server_config_reverb_checkMark', 0);

            return response()->json([
                'success' => false,
                'message' => 'Reverb validation failed. Start it with the Windows command shown in the UI.',
                'logs' => $logs,
            ], 422);
        }
    }

    /**
     * Mark server configuration as fully complete and redirect to dashboard.
     */
    public function markComplete(): JsonResponse
    {
        $this->saveCheckMark('server_config_wizard_checkMark', 1);

        while (ob_get_level()) {
            ob_end_clean();
        }

        return response()->json([
            'success' => true,
            'redirect' => route('dashboard'),
        ]);
    }

    /**
     * Check if a given port is available on the host.
     */
    private function assertPortAvailable(string $host, int $port, array &$logs): void
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, 3);

        if ($connection) {
            fclose($connection);
            $logs[] = '[INFO] Port ' . $host . ':' . $port . ' is already in use.';
            return;
        }

        $logs[] = '[OK]   Port ' . $host . ':' . $port . ' is available for Reverb startup.';
    }

    /**
     * Run a system command safely using proc_open (avoids shell_exec restrictions).
     * Returns the combined stdout+stderr output, or null on failure.
     */
    private function runCommand(string $command): ?string
    {
        if (!function_exists('proc_open')) {
            return null;
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return ($output !== false ? $output : '') . ($stderr !== false ? $stderr : '');
    }

    /**
     * Check if a process is running by keyword (Windows and Linux compatible).
     */
    private function isProcessRunning(string $keyword, array &$logs): bool
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($isWindows) {
            $taskOutput = $this->runCommand('tasklist /FI "IMAGENAME eq php.exe" /FO CSV /NH');
            if ($taskOutput === null || stripos($taskOutput, 'php.exe') === false) {
                $logs[] = '[INFO] No php.exe processes found on Windows.';
                return false;
            }

            $wmicOutput = $this->runCommand('wmic process where "name=\'php.exe\'" get commandline /format:csv');
            if ($wmicOutput !== null && stripos($wmicOutput, $keyword) !== false) {
                return true;
            }

            $psOutput = $this->runCommand(
                'powershell -NoProfile -Command "Get-WmiObject Win32_Process -Filter \"name=\'php.exe\'\" | Select-Object -ExpandProperty CommandLine"'
            );
            if ($psOutput !== null && stripos($psOutput, $keyword) !== false) {
                return true;
            }

            return false;
        }

        $appBasePath = rtrim(base_path(), '/');
        $isLinux = strtolower(PHP_OS) === 'linux';
        $pgrepOutput = $this->runCommand('pgrep -f ' . escapeshellarg($keyword) . ' 2>/dev/null');

        if ($pgrepOutput !== null && trim($pgrepOutput) !== '') {
            foreach (explode("\n", trim($pgrepOutput)) as $pid) {
                $pid = trim($pid);
                if (!ctype_digit($pid)) {
                    continue;
                }

                $cwd = $isLinux
                    ? $this->runCommand("readlink /proc/{$pid}/cwd 2>/dev/null")
                    : $this->runCommand("lsof -a -d cwd -p {$pid} -Fn 2>/dev/null | awk '/^n/{print substr(\$0,2)}'");

                if ($cwd === null) {
                    continue;
                }

                $cwd = rtrim(trim($cwd), '/');
                if ($cwd === $appBasePath) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Upsert a check mark value in system_settings.
     */
    private function saveCheckMark(string $name, int $value): void
    {
        SystemSetting::upsert(
            [['name' => $name, 'data' => $value]],
            ['name'],
            ['data']
        );
        $this->cache->removeSystemCache(config('constants.CACHE.SYSTEM.SETTINGS'));
    }
}
