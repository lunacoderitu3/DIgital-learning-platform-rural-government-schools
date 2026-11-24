<?php

namespace App\Services;

use App\Models\Language;
use Illuminate\Http\UploadedFile;

class AppLocalizationService
{

    /**
     * Platform wise configuration for the language translation files.
     * column    => languages table column that stores the file name
     * directory => sub directory inside resources/lang where the file is stored (empty = resources/lang itself)
     * sample    => sample file name inside resources/lang/sample_file
     */
    public const PLATFORMS = [
        'student' => ['column' => 'student_app_file', 'directory' => 'student_app', 'sample' => 'student_app_en.json'],
        'staff'   => ['column' => 'staff_app_file', 'directory' => 'staff_app', 'sample' => 'staff_app_en.json'],
        'web'     => ['column' => 'web_file', 'directory' => 'web', 'sample' => 'web_en.json'],
        'panel'   => ['column' => 'file', 'directory' => '', 'sample' => 'admin_en.json'],
    ];

    /** Aliases accepted from API clients */
    public const TYPE_ALIASES = [
        'app'   => 'student',
        'admin' => 'panel',
    ];

    public static function types(): array
    {
        return array_keys(self::PLATFORMS);
    }

    public static function normalizeType(?string $type): ?string
    {
        if (empty($type)) {
            return null;
        }
        $type = strtolower(trim($type));
        $type = self::TYPE_ALIASES[$type] ?? $type;
        return array_key_exists($type, self::PLATFORMS) ? $type : null;
    }

    public static function column(string $type): string
    {
        return self::PLATFORMS[$type]['column'];
    }

    public static function directory(string $type): string
    {
        $directory = self::PLATFORMS[$type]['directory'];
        return base_path('resources/lang' . ($directory ? '/' . $directory : ''));
    }

    /**
     * Absolute path of the stored translation file of the given platform. NULL when not uploaded yet.
     */
    public static function filePath(Language $language, string $type): ?string
    {
        $fileName = $language->{self::column($type)};
        if (empty($fileName)) {
            return null;
        }
        $filePath = self::directory($type) . '/' . $fileName;
        return file_exists($filePath) ? $filePath : null;
    }

    public static function samplePath(string $type): ?string
    {
        $filePath = '';
        // panel, student, staff, web
        if ($type == 'panel') {
            $filePath = base_path('resources/lang/en.json');
        } else if ($type == 'student') {
            $filePath = base_path('resources/lang/student_app/en.json');
        } else if ($type == 'staff') {
            $filePath = base_path('resources/lang/staff_app/en.json');
        } else if ($type == 'web') {
            $filePath = base_path('resources/lang/web/en.json');
        }
        return file_exists($filePath) ? $filePath : null;
    }

    /**
     * Store the uploaded translation file as {code}.json inside the platform directory and return the file name.
     */
    public static function storeFile(UploadedFile $file, string $code, string $type): string
    {
        $directory = self::directory($type);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $fileName = $code . '.json';
        $file->move($directory, $fileName);
        return $fileName;
    }

    public static function deleteFile(Language $language, string $type): void
    {
        $filePath = self::filePath($language, $type);
        if ($filePath && file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Rename stored files when the language code changes. Returns column => new file name pairs.
     */
    public static function renameFiles(Language $language, string $newCode): array
    {
        $updates = [];
        foreach (self::types() as $type) {
            $oldPath = self::filePath($language, $type);
            if ($oldPath) {
                $newFileName = $newCode . '.json';
                $newPath = self::directory($type) . '/' . $newFileName;
                if ($oldPath !== $newPath) {
                    rename($oldPath, $newPath);
                }
                $updates[self::column($type)] = $newFileName;
            }
        }
        return $updates;
    }

    /**
     * Decoded translations of the given platform. NULL when the file is missing or unreadable.
     */
    public static function translations(Language $language, string $type): ?array
    {
        $filePath = self::filePath($language, $type);
        if (!$filePath) {
            return null;
        }
        $translations = json_decode(file_get_contents($filePath), true);
        return is_array($translations) ? $translations : null;
    }

    /**
     * Validate that the uploaded file contains a valid, non-empty JSON key/value object.
     */
    public static function isValidJsonFile(UploadedFile $file): bool
    {
        $contents = json_decode(file_get_contents($file->getRealPath()), true);
        return json_last_error() === JSON_ERROR_NONE && is_array($contents) && !empty($contents);
    }
}
