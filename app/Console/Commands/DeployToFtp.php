<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use phpseclib3\Net\SFTP;
use Symfony\Component\Process\Process;

class DeployToFtp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Examples:
     *
     * php artisan deploy:ftp ABC123
     * php artisan deploy:ftp ABC123 DEF456
     * php artisan deploy:ftp ABC123 DEF456 --profile=stage
     * php artisan deploy:ftp ABC123 DEF456 --dry
     * php artisan deploy:ftp current
     * php artisan deploy:ftp current --profile=stage
     * php artisan deploy:ftp current --dry
     */
    protected $signature = 'deploy:ftp
        {commit_from? : Starting commit hash or "current"}
        {commit_to? : Ending commit hash}
        {--profile=dev : Profile key from .vscode/sftp.json (dev|stage)}
        {--dry : List files that would upload, without uploading}';

    /**
     * The console command description.
     */
    protected $description = 'Upload files changed in a git commit, commit range, or current working tree to an FTP/SFTP server';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $commitFrom = $this->argument('commit_from');
        $commitTo = $this->argument('commit_to');

        $profileKey = $this->option('profile');

        /*
         * Load SFTP/FTP configuration.
         */
        $configPath = base_path('.vscode/sftp.json');

        if (! file_exists($configPath)) {
            $this->error(
                ".vscode/sftp.json not found at {$configPath}"
            );

            return self::FAILURE;
        }

        $config = json_decode(
            file_get_contents($configPath),
            true
        );

        if (! is_array($config)) {
            $this->error(
                'Unable to parse .vscode/sftp.json'
            );

            return self::FAILURE;
        }

        $profile = $config['profiles'][$profileKey] ?? null;

        if (! $profile) {
            $this->error(
                "Profile '{$profileKey}' not found in sftp.json"
            );

            return self::FAILURE;
        }

        /*
         * Get changed files.
         *
         * "current" mode:
         * - Modified files
         * - Staged files
         * - Untracked/new files
         *
         * No commit or push is required.
         */
        if ($commitFrom === 'current') {
            $this->info(
                'Checking current working tree changes...'
            );

            $files = $this->currentChangedFiles();

            $rangeDescription = 'current working tree';
        } elseif ($commitTo) {
            $this->info(
                "Checking changes between {$commitFrom} and {$commitTo}..."
            );

            $files = $this->changedFilesBetweenCommits(
                $commitFrom,
                $commitTo
            );

            $rangeDescription = "{$commitFrom}..{$commitTo}";
        } else {
            /*
             * Keep existing behavior:
             * if no commit is provided, use HEAD.
             */
            $commitFrom = $commitFrom ?: 'HEAD';

            $this->info(
                "Checking changes in commit {$commitFrom}..."
            );

            $files = $this->changedFiles($commitFrom);

            $rangeDescription = $commitFrom;
        }

        if (empty($files)) {
            $this->info(
                "No changed files found for {$rangeDescription}"
            );

            return self::SUCCESS;
        }

        /*
         * Display profile information.
         */
        $host = $profile['host'] ?? 'unknown';
        $port = (int) ($profile['port'] ?? 22);

        $protocol = strtolower(
            $profile['protocol']
                ?? ($port === 21 ? 'ftp' : 'sftp')
        );

        $this->info(
            'Profile: ' .
                ($profile['name'] ?? $profileKey)
                . " ({$host}:{$port})"
        );

        $this->info(
            'Protocol: ' . strtoupper($protocol)
        );

        /*
         * Display files.
         */
        $this->info(
            'Files (' . count($files) . '):'
        );

        foreach ($files as $file) {
            $this->line('  ' . $file);
        }

        /*
         * Dry run.
         */
        if ($this->option('dry')) {
            $this->info('Dry run completed.');

            return self::SUCCESS;
        }

        /*
         * Confirmation.
         */
        if (! $this->confirm(
            'Upload these files to ' .
                ($profile['name'] ?? $profileKey) .
                '?',
            true
        )) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        /*
         * Upload.
         */
        return $this->upload(
            $profile,
            $files
        );
    }

    /**
     * Get files currently modified, staged, or untracked.
     *
     * Uses:
     *
     * git status --short --untracked-files=all
     *
     * Supported:
     * - Modified files
     * - Staged files
     * - Staged + modified files
     * - Newly created/untracked files
     *
     * Deleted files are ignored because they do not exist
     * locally and therefore cannot be uploaded.
     */
    private function currentChangedFiles(): array
    {
        $process = new Process([
            'git',
            'status',
            '--short',
            '--untracked-files=all',
        ]);

        $process->setWorkingDirectory(
            base_path()
        );

        $process->run();

        if (! $process->isSuccessful()) {
            $this->error(
                $process->getErrorOutput()
            );

            return [];
        }

        $files = [];

        $lines = array_filter(
            explode(
                "\n",
                trim($process->getOutput())
            )
        );

        foreach ($lines as $line) {
            $line = rtrim($line);

            if ($line === '') {
                continue;
            }

            /*
             * Git status --short format:
             *
             * XY filename
             *
             * Examples:
             *
             *  M file.php
             * M  file.php
             * MM file.php
             * ?? new-file.php
             *  D deleted.php
             * D  deleted.php
             */
            $status = substr($line, 0, 2);
            $file = trim(substr($line, 2));

            if ($file === '') {
                continue;
            }

            /*
             * Ignore deleted files.
             */
            if (str_contains($status, 'D')) {
                continue;
            }

            /*
             * Handle renamed files.
             *
             * Git may return:
             *
             * R  old.php -> new.php
             */
            if (str_contains($file, ' -> ')) {
                $file = trim(
                    substr(
                        $file,
                        strrpos($file, ' -> ') + 4
                    )
                );
            }

            $files[] = $file;
        }

        return $this->filterDeployableFiles($files);
    }

    /**
     * Get files changed in a single commit.
     */
    private function changedFiles(string $commit): array
    {
        $process = new Process([
            'git',
            'diff-tree',
            '--no-commit-id',
            '--name-only',
            '-r',
            $commit,
        ]);

        $process->setWorkingDirectory(
            base_path()
        );

        $process->run();

        if (! $process->isSuccessful()) {
            $this->error(
                $process->getErrorOutput()
            );

            return [];
        }

        $files = array_filter(
            explode(
                "\n",
                trim($process->getOutput())
            )
        );

        return $this->filterDeployableFiles($files);
    }

    /**
     * Get all files changed between two commits.
     *
     * Example:
     *
     * A -> B -> C -> D
     *
     * deploy:ftp A D
     *
     * Returns all files whose final content differs
     * between A and D.
     */
    private function changedFilesBetweenCommits(
        string $commitFrom,
        string $commitTo
    ): array {
        /*
         * Validate commits before running diff.
         */
        if (! $this->isValidCommit($commitFrom)) {
            $this->error(
                "Invalid starting commit: {$commitFrom}"
            );

            return [];
        }

        if (! $this->isValidCommit($commitTo)) {
            $this->error(
                "Invalid ending commit: {$commitTo}"
            );

            return [];
        }

        $process = new Process([
            'git',
            'diff',
            '--name-only',
            $commitFrom,
            $commitTo,
        ]);

        $process->setWorkingDirectory(
            base_path()
        );

        $process->run();

        if (! $process->isSuccessful()) {
            $this->error(
                $process->getErrorOutput()
            );

            return [];
        }

        $files = array_filter(
            explode(
                "\n",
                trim($process->getOutput())
            )
        );

        return $this->filterDeployableFiles($files);
    }

    /**
     * Check whether a git commit/ref exists.
     */
    private function isValidCommit(string $commit): bool
    {
        $process = new Process([
            'git',
            'rev-parse',
            '--verify',
            $commit,
        ]);

        $process->setWorkingDirectory(
            base_path()
        );

        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Filter files that can be deployed.
     */
    private function filterDeployableFiles(
        array $files
    ): array {
        $files = array_map(
            static fn($file) => trim($file),
            $files
        );

        $files = array_filter(
            $files,
            function ($file) {
                /*
                 * Empty file.
                 */
                if ($file === '') {
                    return false;
                }

                /*
                 * Never upload .env files.
                 *
                 * Supports:
                 * .env
                 * .env.local
                 * .env.production
                 * .env.testing
                 */
                if (
                    $file === '.env' ||
                    str_starts_with($file, '.env.')
                ) {
                    return false;
                }

                /*
                 * Deleted files do not exist locally.
                 */
                $fullPath = base_path($file);

                if (! is_file($fullPath)) {
                    return false;
                }

                return true;
            }
        );

        /*
         * Remove duplicate files.
         */
        $files = array_unique($files);

        /*
         * Sort files so output is predictable.
         */
        sort($files);

        return array_values($files);
    }

    /**
     * Determine upload protocol and upload files.
     */
    private function upload(
        array $profile,
        array $files
    ): int {
        $port = (int) ($profile['port'] ?? 22);

        /*
         * Protocol can explicitly be:
         *
         * sftp
         * ftp
         * ftps
         *
         * If protocol isn't configured:
         *
         * 21 -> FTP
         * everything else -> SFTP
         */
        $protocol = strtolower(
            $profile['protocol']
                ?? ($port === 21 ? 'ftp' : 'sftp')
        );

        return match ($protocol) {
            'sftp' => $this->uploadViaSftp(
                $profile,
                $files
            ),

            'ftp' => $this->uploadViaFtp(
                $profile,
                $files
            ),

            'ftps' => $this->uploadViaFtps(
                $profile,
                $files
            ),

            default => $this->unsupportedProtocol(
                $protocol
            ),
        };
    }

    /**
     * Upload files using SFTP.
     */
    private function uploadViaSftp(
        array $profile,
        array $files
    ): int {
        $host = $profile['host'];
        $port = (int) ($profile['port'] ?? 22);
        $username = $profile['username'];
        $password = $profile['password'];

        $remoteRoot = rtrim(
            $profile['remotePath'] ?? '/',
            '/'
        );

        $this->info(
            "Connecting to {$host}:{$port} via SFTP..."
        );

        try {
            $sftp = new SFTP(
                $host,
                $port
            );

            if (! $sftp->login(
                $username,
                $password
            )) {
                $this->error(
                    'SFTP login failed. Please verify your credentials.'
                );

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error(
                'SFTP connection failed: ' .
                    $e->getMessage()
            );

            return self::FAILURE;
        }

        $this->info(
            'Connected successfully. Starting SFTP upload...'
        );

        $failed = [];

        foreach ($files as $file) {
            $localPath = base_path($file);

            $remotePath =
                $remoteRoot . '/' . $file;

            $remoteDir =
                dirname($remotePath);

            /*
             * Create remote directory.
             */
            if (! $sftp->is_dir($remoteDir)) {
                if (! $sftp->mkdir(
                    $remoteDir,
                    -1,
                    true
                )) {
                    $this->line(
                        "<fg=red>failed</> {$file} - unable to create directory"
                    );

                    $failed[] = $file;

                    continue;
                }
            }

            /*
             * Upload file.
             */
            if ($sftp->put(
                $remotePath,
                $localPath,
                SFTP::SOURCE_LOCAL_FILE
            )) {
                $this->line(
                    "<fg=green>uploaded</> {$file}"
                );
            } else {
                $this->line(
                    "<fg=red>failed</> {$file}"
                );

                $failed[] = $file;
            }
        }

        $sftp->disconnect();

        return $this->uploadResult($failed);
    }

    /**
     * Upload files using normal FTP.
     */
    private function uploadViaFtp(
        array $profile,
        array $files
    ): int {
        $host = $profile['host'];
        $port = (int) ($profile['port'] ?? 21);
        $username = $profile['username'];
        $password = $profile['password'];

        $remoteRoot = rtrim(
            $profile['remotePath'] ?? '/',
            '/'
        );

        $this->info(
            "Connecting to {$host}:{$port} via FTP..."
        );

        if (! function_exists('ftp_connect')) {
            $this->error(
                'PHP FTP extension is not installed/enabled.'
            );

            return self::FAILURE;
        }

        $ftp = @ftp_connect(
            $host,
            $port,
            30
        );

        if ($ftp === false) {
            $this->error(
                "FTP connection failed: {$host}:{$port}"
            );

            return self::FAILURE;
        }

        /*
         * Login.
         */
        if (! @ftp_login(
            $ftp,
            $username,
            $password
        )) {
            @ftp_close($ftp);

            $this->error(
                'FTP login failed. Please verify your credentials.'
            );

            return self::FAILURE;
        }

        /*
         * Passive mode is normally required
         * for servers behind firewalls/NAT.
         */
        if (! @ftp_pasv($ftp, true)) {
            $this->warn(
                'Unable to enable FTP passive mode.'
            );
        }

        $this->info(
            'Connected successfully. Starting FTP upload...'
        );

        $failed = [];

        foreach ($files as $file) {
            $localPath = base_path($file);

            $remotePath =
                $remoteRoot . '/' . $file;

            $remoteDir =
                dirname($remotePath);

            /*
             * Create remote directory.
             */
            if (! $this->ensureFtpDirectory(
                $ftp,
                $remoteDir
            )) {
                $this->line(
                    "<fg=red>failed</> {$file} - unable to create directory"
                );

                $failed[] = $file;

                continue;
            }

            /*
             * Upload file.
             */
            if (@ftp_put(
                $ftp,
                $remotePath,
                $localPath,
                FTP_BINARY
            )) {
                $this->line(
                    "<fg=green>uploaded</> {$file}"
                );
            } else {
                $this->line(
                    "<fg=red>failed</> {$file}"
                );

                $failed[] = $file;
            }
        }

        @ftp_close($ftp);

        return $this->uploadResult($failed);
    }

    /**
     * Upload files using FTPS.
     *
     * FTPS is FTP over TLS.
     */
    private function uploadViaFtps(
        array $profile,
        array $files
    ): int {
        $host = $profile['host'];
        $port = (int) ($profile['port'] ?? 21);
        $username = $profile['username'];
        $password = $profile['password'];

        $remoteRoot = rtrim(
            $profile['remotePath'] ?? '/',
            '/'
        );

        $this->info(
            "Connecting to {$host}:{$port} via FTPS..."
        );

        if (! function_exists('ftp_ssl_connect')) {
            $this->error(
                'PHP FTP extension with SSL support is not available.'
            );

            return self::FAILURE;
        }

        $ftp = @ftp_ssl_connect(
            $host,
            $port,
            30
        );

        if ($ftp === false) {
            $this->error(
                "FTPS connection failed: {$host}:{$port}"
            );

            return self::FAILURE;
        }

        /*
         * Login.
         */
        if (! @ftp_login(
            $ftp,
            $username,
            $password
        )) {
            @ftp_close($ftp);

            $this->error(
                'FTPS login failed. Please verify your credentials.'
            );

            return self::FAILURE;
        }

        /*
         * Enable passive mode.
         */
        if (! @ftp_pasv($ftp, true)) {
            $this->warn(
                'Unable to enable FTPS passive mode.'
            );
        }

        $this->info(
            'Connected successfully. Starting FTPS upload...'
        );

        $failed = [];

        foreach ($files as $file) {
            $localPath = base_path($file);

            $remotePath =
                $remoteRoot . '/' . $file;

            $remoteDir =
                dirname($remotePath);

            /*
             * Create remote directory.
             */
            if (! $this->ensureFtpDirectory(
                $ftp,
                $remoteDir
            )) {
                $this->line(
                    "<fg=red>failed</> {$file} - unable to create directory"
                );

                $failed[] = $file;

                continue;
            }

            /*
             * Upload file.
             */
            if (@ftp_put(
                $ftp,
                $remotePath,
                $localPath,
                FTP_BINARY
            )) {
                $this->line(
                    "<fg=green>uploaded</> {$file}"
                );
            } else {
                $this->line(
                    "<fg=red>failed</> {$file}"
                );

                $failed[] = $file;
            }
        }

        @ftp_close($ftp);

        return $this->uploadResult($failed);
    }

    /**
     * Create FTP directory recursively.
     */
    private function ensureFtpDirectory(
        $ftp,
        string $directory
    ): bool {
        if (
            $directory === '' ||
            $directory === '.' ||
            $directory === '/'
        ) {
            return true;
        }

        /*
         * Remember current FTP directory.
         */
        $originalDirectory =
            @ftp_pwd($ftp);

        if ($originalDirectory === false) {
            $originalDirectory = '/';
        }

        /*
         * Convert directory into parts.
         */
        $isAbsolute =
            str_starts_with(
                $directory,
                '/'
            );

        $directory = trim(
            $directory,
            '/'
        );

        if ($directory === '') {
            return true;
        }

        $parts = explode(
            '/',
            $directory
        );

        /*
         * Start from root for absolute paths.
         */
        if ($isAbsolute) {
            if (! @ftp_chdir(
                $ftp,
                '/'
            )) {
                return false;
            }
        }

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            /*
             * Try to enter existing directory.
             */
            if (@ftp_chdir(
                $ftp,
                $part
            )) {
                continue;
            }

            /*
             * Directory doesn't exist.
             * Try to create it.
             */
            if (! @ftp_mkdir(
                $ftp,
                $part
            )) {
                /*
                 * It may have been created by
                 * another process. Try again.
                 */
                if (! @ftp_chdir(
                    $ftp,
                    $part
                )) {
                    /*
                     * Restore original directory.
                     */
                    @ftp_chdir(
                        $ftp,
                        $originalDirectory
                    );

                    return false;
                }

                continue;
            }

            /*
             * Enter newly created directory.
             */
            if (! @ftp_chdir(
                $ftp,
                $part
            )) {
                @ftp_chdir(
                    $ftp,
                    $originalDirectory
                );

                return false;
            }
        }

        /*
         * Restore original directory.
         */
        @ftp_chdir(
            $ftp,
            $originalDirectory
        );

        return true;
    }

    /**
     * Return upload result.
     */
    private function uploadResult(
        array $failed
    ): int {
        if (! empty($failed)) {
            $this->error(
                count($failed) .
                    ' file(s) failed to upload.'
            );

            /*
             * Show failed files.
             */
            foreach ($failed as $file) {
                $this->line(
                    "  - {$file}"
                );
            }

            return self::FAILURE;
        }

        $this->info(
            'Deploy complete.'
        );

        return self::SUCCESS;
    }

    /**
     * Unsupported protocol handler.
     */
    private function unsupportedProtocol(
        string $protocol
    ): int {
        $this->error(
            "Unsupported protocol '{$protocol}'. " .
                "Supported protocols: sftp, ftp, ftps."
        );

        return self::FAILURE;
    }
}
