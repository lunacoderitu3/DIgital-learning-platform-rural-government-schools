<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class InstallerChecks
{
    public static function phpVersion(): bool
    {
        return version_compare(PHP_VERSION, '8.1.0', '>=');
    }

    public static function pdoMysqlAvailable(): bool
    {
        return extension_loaded('pdo_mysql');
    }

    public static function mbstringAvailable(): bool
    {
        return extension_loaded('mbstring');
    }

    public static function fileinfoAvailable(): bool
    {
        return extension_loaded('fileinfo');
    }

    public static function opensslAvailable(): bool
    {
        return extension_loaded('openssl');
    }

    public static function tokenizerAvailable(): bool
    {
        return extension_loaded('tokenizer');
    }

    public static function jsonAvailable(): bool
    {
        return extension_loaded('json');
    }

    public static function curlAvailable(): bool
    {
        return extension_loaded('curl');
    }

    public static function zipAvailable(): bool
    {
        return extension_loaded('zip');
    }

    public static function storageFrameworkWritable(): bool
    {
        return (int) File::chmod(base_path() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework') >= 755;
    }

    public static function storageLogsWritable(): bool
    {
        return (int) File::chmod(base_path() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs') >= 755;
    }

    public static function bootstrapCacheWritable(): bool
    {
        return (int) File::chmod(base_path() . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache') >= 755;
    }
}
