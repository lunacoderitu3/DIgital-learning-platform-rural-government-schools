<?php

namespace App\Services;

use App\Models\School;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use ZipArchive;
use App\Models\DatabaseBackup;

class DatabaseBackupService
{
    /**
     * Delete every script-like file below $path.
     *
     * A restore archive is user supplied content. Its "storage" folder is
     * copied straight into the publicly served storage/app/public directory,
     * so a crafted backup ZIP would otherwise be an arbitrary file write into
     * a web reachable path (remote code execution).
     */
    private function purgeExecutableFiles(string $path): void
    {
        if (!File::isDirectory($path)) {
            return;
        }

        foreach (File::allFiles($path, true) as $file) {
            $extension = strtolower($file->getExtension());
            if ($extension === '' || in_array($extension, UploadService::BLOCKED_EXTENSIONS, true)) {
                File::delete($file->getPathname());
                Log::warning('Blocked executable file removed from restored backup: ' . $file->getPathname());
            }
        }
    }

    /**
     * Perform Super Admin backup: main DB + all school DBs + all school storage.
     */
    public function superAdminBackup(int $userId, DatabaseBackup $backupRecord)
    {
        try {
            $backupRecord->update(['status' => 'processing']);

            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $tempDirName = "backup_temp_superadmin_{$userId}_{$timestamp}";
            $tempDirPath = storage_path("app/public/{$tempDirName}");
            $databasesDirPath = "{$tempDirPath}/databases";
            $storageDirPath = "{$tempDirPath}/storage";

            // Create temp directories
            File::makeDirectory($databasesDirPath, 0755, true);
            File::makeDirectory($storageDirPath, 0755, true);

            // 1. Dump main database
            $mainConnection = 'mysql';
            $mainSqlPath = "{$databasesDirPath}/main_database.sql";
            $this->runPhpDump($mainConnection, $mainSqlPath);

            // 2. Fetch all schools and loop
            $schools = School::withTrashed()->whereNotNull('database_name')->get();

            foreach ($schools as $school) {
                // Setup dynamic connection
                Config::set("database.connections.school_{$school->id}", [
                    'driver' => 'mysql',
                    'host' => config('database.connections.school.host', '127.0.0.1'),
                    'port' => config('database.connections.school.port', '3306'),
                    'database' => $school->database_name,
                    'username' => config('database.connections.school.username', 'root'),
                    'password' => config('database.connections.school.password', ''),
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                    'strict' => false,
                    'engine' => 'InnoDB',
                ]);

                // Dump school database
                $schoolSqlPath = "{$databasesDirPath}/school_{$school->id}.sql";
                $this->runPhpDump("school_{$school->id}", $schoolSqlPath);

                // Copy school storage folder
                $schoolStorageSource = storage_path("app/public/{$school->id}");
                $schoolStorageDest = "{$storageDirPath}/{$school->id}";

                if (File::isDirectory($schoolStorageSource)) {
                    File::copyDirectory($schoolStorageSource, $schoolStorageDest);
                }
            }

            // 3. Generate ZIP
            $zipFileName = "database_backup_{$userId}_{$timestamp}.zip";
            // The final path should be public/database-backup/super-admin/{userId}
            $finalBackupFolder = "public/database-backup/super-admin/{$userId}";
            if (!Storage::exists($finalBackupFolder)) {
                Storage::makeDirectory($finalBackupFolder);
            }
            $finalZipPath = storage_path("app/{$finalBackupFolder}/{$zipFileName}");

            $this->createZip($tempDirPath, $finalZipPath);

            // 4. Cleanup temp files
            File::deleteDirectory($tempDirPath);

            // 5. Update Record
            $relativePath = "database-backup/super-admin/{$userId}/{$zipFileName}";
            $backupRecord->update([
                'status' => 'completed',
                'file_path' => $relativePath,
            ]);
        } catch (\Exception $e) {
            Log::error("Super Admin Backup Failed: " . $e->getMessage());
            $backupRecord->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
            // Attempt cleanup
            if (isset($tempDirPath) && File::isDirectory($tempDirPath)) {
                File::deleteDirectory($tempDirPath);
            }
        }
    }

    /**
     * Perform School Admin backup: school DB + school storage.
     */
    public function schoolAdminBackup(int $schoolId, DatabaseBackup $backupRecord)
    {
        try {
            $backupRecord->update(['status' => 'processing']);

            $school = School::withTrashed()->find($schoolId);
            if (!$school || !$school->database_name) {
                throw new \Exception("School or database name not found.");
            }

            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $tempDirName = "backup_temp_school_{$schoolId}_{$timestamp}";
            $tempDirPath = storage_path("app/public/{$tempDirName}");
            $databaseDirPath = "{$tempDirPath}/database";
            $storageDirPath = "{$tempDirPath}/storage";

            // Create temp directories
            File::makeDirectory($databaseDirPath, 0755, true);
            File::makeDirectory($storageDirPath, 0755, true);

            // 1. Configure and dump school database
            Config::set("database.connections.school_{$school->id}", [
                'driver' => 'mysql',
                'host' => config('database.connections.school.host', '127.0.0.1'),
                'port' => config('database.connections.school.port', '3306'),
                'database' => $school->database_name,
                'username' => config('database.connections.school.username', 'root'),
                'password' => config('database.connections.school.password', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => false,
                'engine' => 'InnoDB',
            ]);

            $schoolSqlPath = "{$databaseDirPath}/school_{$school->id}_database.sql";
            $this->runPhpDump("school_{$school->id}", $schoolSqlPath);

            // 2. Copy school storage folder
            $schoolStorageSource = storage_path("app/public/{$schoolId}");
            $schoolStorageDest = "{$storageDirPath}/{$schoolId}";

            if (File::isDirectory($schoolStorageSource)) {
                File::copyDirectory($schoolStorageSource, $schoolStorageDest);
            }

            // 3. Generate ZIP
            $zipFileName = "school-backup-{$timestamp}.zip";
            // Final path for school backup
            $finalBackupFolder = "public/database-backup/schools/{$schoolId}";
            if (!Storage::exists($finalBackupFolder)) {
                Storage::makeDirectory($finalBackupFolder);
            }
            $finalZipPath = storage_path("app/{$finalBackupFolder}/{$zipFileName}");

            $this->createZip($tempDirPath, $finalZipPath);

            // 4. Cleanup temp files
            File::deleteDirectory($tempDirPath);

            // 5. Update Record
            $relativePath = "database-backup/schools/{$schoolId}/{$zipFileName}";
            $backupRecord->update([
                'status' => 'completed',
                'file_path' => $relativePath,
            ]);
        } catch (\Exception $e) {
            Log::error("School Admin Backup Failed: " . $e->getMessage());
            $backupRecord->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
            // Attempt cleanup
            if (isset($tempDirPath) && File::isDirectory($tempDirPath)) {
                File::deleteDirectory($tempDirPath);
            }
        }
    }

    /**
     * Run PHP-based dump for a given connection and save output to path.
     */
    private function runPhpDump(string $connection, string $outputPath)
    {
        $dbConfig = config("database.connections.{$connection}");

        if (!$dbConfig) {
            throw new \Exception("Database connection {$connection} not found.");
        }

        $file = fopen($outputPath, 'w');
        if (!$file) {
            throw new \Exception("Failed to open file for writing: {$outputPath}");
        }

        try {
            // Disable foreign key checks during import
            fwrite($file, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            $tables = DB::connection($connection)->select('SHOW TABLES');

            foreach ($tables as $tableObj) {
                // Determine the table name dynamically
                $tableArray = (array) $tableObj;
                $tableName = array_values($tableArray)[0];

                // Get Create Table Syntax
                $createTableSQL = DB::connection($connection)->select("SHOW CREATE TABLE `{$tableName}`");
                if (isset($createTableSQL[0]->{'Create Table'})) {
                    $createTable = $createTableSQL[0]->{'Create Table'};
                    $createTableWithIfNotExists = str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $createTable);
                    fwrite($file, "\n\n" . $createTableWithIfNotExists . ";\n\n");
                }

                // Use cursor() to iterate over all rows without memory limits and without offset issues
                $pdo = DB::connection($connection)->getPdo();
                foreach (DB::connection($connection)->table($tableName)->cursor() as $row) {
                    $rowArray = (array) $row;
                    $values = array_map(function ($value) use ($pdo) {
                        if (is_null($value)) {
                            return 'NULL';
                        }
                        // Use PDO native quote to securely handle ALL special characters perfectly
                        return $pdo->quote($value);
                    }, $rowArray);

                    $insertSql = "INSERT IGNORE INTO `{$tableName}` VALUES (" . implode(', ', $values) . ");\n";
                    fwrite($file, $insertSql);
                }
            }

            fwrite($file, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($file);
        }
    }

    /**
     * Create a ZIP file from a source directory.
     */
    private function createZip(string $sourceDir, string $zipPath)
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDir),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                // Skip directories (they would be added automatically)
                if (!$file->isDir()) {
                    // Get real and relative path for current file
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($sourceDir) + 1);

                    // Add current file to archive
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();
        } else {
            throw new \Exception("Failed to create ZIP archive at {$zipPath}");
        }
    }

    /**
     * Restore School Admin Backup in background.
     */
    public function restoreSchoolAdminBackup(string $zipFilePath, int $schoolId)
    {
        $extractPath = storage_path("app/public/restore_temp_{$schoolId}_" . time());

        try {
            $zip = new ZipArchive;
            if ($zip->open(storage_path('app/' . $zipFilePath)) === TRUE) {
                $zip->extractTo($extractPath);
                $zip->close();
            } else {
                throw new \Exception("Could not open ZIP file");
            }

            // Restore Storage files
            $storageSource = "{$extractPath}/storage/{$schoolId}";
            $storageDest = storage_path("app/public/{$schoolId}");

            $this->purgeExecutableFiles($storageSource);

            if (File::isDirectory($storageSource)) {
                if (File::isDirectory($storageDest)) {
                    File::deleteDirectory($storageDest);
                }
                File::makeDirectory($storageDest, 0755, true);
                File::copyDirectory($storageSource, $storageDest);
            }

            // Restore Database
            $sqlFile = "{$extractPath}/database/school_{$schoolId}_database.sql";
            if (File::exists($sqlFile)) {
                $school = School::withTrashed()->find($schoolId);
                if ($school && $school->database_name) {
                    Config::set("database.connections.school_{$schoolId}", [
                        'driver' => 'mysql',
                        'host' => config('database.connections.school.host', '127.0.0.1'),
                        'port' => config('database.connections.school.port', '3306'),
                        'database' => $school->database_name,
                        'username' => config('database.connections.school.username', 'root'),
                        'password' => config('database.connections.school.password', ''),
                        'charset' => 'utf8mb4',
                        'collation' => 'utf8mb4_unicode_ci',
                        'prefix' => '',
                        'strict' => false,
                        'engine' => 'InnoDB',
                    ]);

                    $this->importSqlFile("school_{$schoolId}", $sqlFile);
                }
            }
        } catch (\Exception $e) {
            Log::error("Restore School Admin Backup Failed: " . $e->getMessage());
        } finally {
            if (File::isDirectory($extractPath)) {
                File::deleteDirectory($extractPath);
            }
            if (Storage::exists($zipFilePath)) {
                Storage::delete($zipFilePath);
            }
        }
    }

    /**
     * Restore Super Admin Backup in background.
     */
    public function restoreSuperAdminBackup(string $zipFilePath, int $userId)
    {
        $extractPath = storage_path("app/public/restore_temp_{$userId}_" . time());

        try {
            $zip = new ZipArchive;
            if ($zip->open(storage_path('app/' . $zipFilePath)) === TRUE) {
                $zip->extractTo($extractPath);
                $zip->close();
            } else {
                throw new \Exception("Could not open ZIP file");
            }

            // Restore Main DB
            $mainSqlFile = "{$extractPath}/databases/main_database.sql";
            if (File::exists($mainSqlFile)) {
                $this->importSqlFile('mysql', $mainSqlFile);
            }

            // Restore All Schools Storage & DB
            $schools = School::withTrashed()->whereNotNull('database_name')->get();

            foreach ($schools as $school) {
                // Restore Storage
                $storageSource = "{$extractPath}/storage/{$school->id}";
                $storageDest = storage_path("app/public/{$school->id}");
                $this->purgeExecutableFiles($storageSource);
                if (File::isDirectory($storageSource)) {
                    if (File::isDirectory($storageDest)) {
                        File::deleteDirectory($storageDest);
                    }
                    File::makeDirectory($storageDest, 0755, true);
                    File::copyDirectory($storageSource, $storageDest);
                }

                // Restore Database
                $schoolSqlFile = "{$extractPath}/databases/school_{$school->id}.sql";
                if (File::exists($schoolSqlFile)) {
                    Config::set("database.connections.school_{$school->id}", [
                        'driver' => 'mysql',
                        'host' => config('database.connections.school.host', '127.0.0.1'),
                        'port' => config('database.connections.school.port', '3306'),
                        'database' => $school->database_name,
                        'username' => config('database.connections.school.username', 'root'),
                        'password' => config('database.connections.school.password', ''),
                        'charset' => 'utf8mb4',
                        'collation' => 'utf8mb4_unicode_ci',
                        'prefix' => '',
                        'strict' => false,
                        'engine' => 'InnoDB',
                    ]);

                    $this->importSqlFile("school_{$school->id}", $schoolSqlFile);
                }
            }
        } catch (\Exception $e) {
            Log::error("Restore Super Admin Backup Failed: " . $e->getMessage());
        } finally {
            if (File::isDirectory($extractPath)) {
                File::deleteDirectory($extractPath);
            }
            if (Storage::exists($zipFilePath)) {
                Storage::delete($zipFilePath);
            }
        }
    }

    /**
     * Helper to import SQL file into a database connection.
     */
    private function importSqlFile(string $connection, string $sqlFile)
    {
        DB::connection($connection)->statement("SET FOREIGN_KEY_CHECKS = 0;");
        
        $tables = DB::connection($connection)->getDoctrineSchemaManager()->listTableNames();
        $expectedTables = ['database_backups'];
        $tableNames = array_diff($tables, $expectedTables);
        
        foreach ($tableNames as $table) {
            // Using DELETE instead of TRUNCATE to avoid MySQL 1215 Cannot add foreign key constraint errors
            // during the implicit drop/recreate that TRUNCATE does.
            DB::connection($connection)->table($table)->delete();
            Log::info("Table {$table} deleted (emptied) on connection {$connection}.");
        }
        
        $sql = File::get($sqlFile);
        
        // Convert old backups using 'INSERT INTO' to 'INSERT IGNORE INTO' 
        // to gracefully handle tables that were intentionally skipped from truncation (e.g. database_backups)
        $sql = preg_replace('/^INSERT INTO `/m', 'INSERT IGNORE INTO `', $sql);
        
        DB::connection($connection)->unprepared($sql);
        DB::connection($connection)->statement("SET FOREIGN_KEY_CHECKS = 1;");
    }
}
