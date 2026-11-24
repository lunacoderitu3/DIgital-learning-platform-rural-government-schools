<?php

use Illuminate\Support\Facades\File;

return [
    'icon' => 'assets/ritu-school-logo.svg',

    //    'background' => 'assets/logo.svg',

    'support_url' => 'ritu.school',

    'server' => [
        'php'       => [
            'name'    => 'PHP Version',
            'version' => '>= 8.1.0',
            'check'   => ['App\\Support\\InstallerChecks', 'phpVersion'],
        ],
        'pdo'       => [
            'name'  => 'PDO',
            'check' => ['App\\Support\\InstallerChecks', 'pdoMysqlAvailable'],
        ],
        'mbstring'  => [
            'name'  => 'Mbstring extension',
            'check' => ['App\\Support\\InstallerChecks', 'mbstringAvailable'],
        ],
        'fileinfo'  => [
            'name'  => 'Fileinfo extension',
            'check' => ['App\\Support\\InstallerChecks', 'fileinfoAvailable'],
        ],
        'openssl'   => [
            'name'  => 'OpenSSL extension',
            'check' => ['App\\Support\\InstallerChecks', 'opensslAvailable'],
        ],
        'tokenizer' => [
            'name'  => 'Tokenizer extension',
            'check' => ['App\\Support\\InstallerChecks', 'tokenizerAvailable'],
        ],
        'json'      => [
            'name'  => 'Json extension',
            'check' => ['App\\Support\\InstallerChecks', 'jsonAvailable'],
        ],
        'curl'      => [
            'name'  => 'Curl extension',
            'check' => ['App\\Support\\InstallerChecks', 'curlAvailable'],
        ],
        'zip'       => [
            'name'  => 'Zip extension',
            'check' => ['App\\Support\\InstallerChecks', 'zipAvailable'],
        ],
    ],

    'folders' => [
        'storage.framework' => [
            'name'  => base_path() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework',
            'check' => ['App\\Support\\InstallerChecks', 'storageFrameworkWritable'],
        ],
        'storage.logs'      => [
            'name'  => base_path() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs',
            'check' => ['App\\Support\\InstallerChecks', 'storageLogsWritable'],
        ],
        'storage.cache'     => [
            'name'  => base_path() . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache',
            'check' => ['App\\Support\\InstallerChecks', 'bootstrapCacheWritable'],
        ],
    ],

    'database' => [
        'seeders' => false
    ],

    'commands' => [
        'db:seed --class=InstallationSeeder',
        'db:seed --class=AddSuperAdminSeeder',
    ],

    'admin_area' => [
        'user' => [
            'email'    => 'superadmin@gmail.com',
            'password' => 'superadmin'
        ]
    ]
];
