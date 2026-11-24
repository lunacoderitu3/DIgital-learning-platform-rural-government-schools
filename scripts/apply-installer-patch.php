<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$patches = [
    'vendor/sagar/laravel-wizard-installer/src/Controllers/InstallDatabaseController.php' => [
        'before' => <<<'PHP'
        if (!env('APPSECRET') ||
            !(new InstallServerController())->check() ||
            !(new InstallFolderController())->check()
        ) {
            return redirect()->route('install.purchase-code.index');
        }
PHP,
        'after' => <<<'PHP'
        if (!(new InstallServerController())->check() ||
            !(new InstallFolderController())->check()
        ) {
            return redirect()->route('LaravelWizardInstaller::install.folders');
        }
PHP,
        'applied' => "return redirect()->route('LaravelWizardInstaller::install.folders');",
    ],
    'vendor/sagar/laravel-wizard-installer/src/Controllers/InstallMigrationsController.php' => [
        'before' => <<<'PHP'
        if (!env('APPSECRET')) {
            return redirect()->route('install.purchase-code.index');
        }
PHP,
        'after' => '',
        'applied' => "        return view('installer::steps.migrations');",
    ],
    'vendor/sagar/laravel-wizard-installer/src/Controllers/InstallKeysController.php' => [
        'before' => <<<'PHP'
        if (!env('APPSECRET')) {
            return redirect()->route('install.purchase-code.index');
        }
PHP,
        'after' => '',
        'applied' => "        return view('installer::steps.keys');",
    ],
    'vendor/sagar/laravel-wizard-installer/src/Controllers/InstallFinishController.php' => [
        'before' => <<<'PHP'
        if (!env('APPSECRET')) {
            return redirect()->route('install.purchase-code.index');
        }
PHP,
        'after' => '',
        'applied' => "            empty(EnvSet::getValue('APP_KEY')) ||",
    ],
    'vendor/sagar/laravel-wizard-installer/src/Controllers/InstallSetDatabaseController.php' => [
        'before' => <<<'PHP'
            EnvSet::setKey('DB_PASSWORD', $request->input('database_password'));
PHP,
        'after' => <<<'PHP'
            EnvSet::setKey('DB_PASSWORD', $request->input('database_password'));
            EnvSet::setKey('APP_URL', (string)url('/'));
PHP,
        'applied' => "            EnvSet::setKey('APP_URL', (string)url('/'));",
    ],
];

foreach ($patches as $relativePath => $patch) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($path)) {
        fwrite(STDERR, "Cannot apply installer patch: missing {$relativePath}\n");
        exit(1);
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        fwrite(STDERR, "Cannot read installer file: {$relativePath}\n");
        exit(1);
    }

    $usesWindowsLineEndings = str_contains($contents, "\r\n");
    $contents = str_replace(["\r\n", "\r"], "\n", $contents);
    $beforeCount = substr_count($contents, $patch['before']);
    $appliedCount = substr_count($contents, $patch['applied']);

    $sourceIsPartOfPatch = $patch['after'] !== ''
        && str_contains($patch['after'], $patch['before']);

    if (($beforeCount === 0 && $appliedCount === 1)
        || ($sourceIsPartOfPatch && $beforeCount === 1 && $appliedCount === 1)
    ) {
        continue;
    }

    if ($beforeCount !== 1 || $appliedCount !== 0) {
        fwrite(
            STDERR,
            "Installer patch does not match expected package contents: {$relativePath} "
            . "(source matches: {$beforeCount}, applied markers: {$appliedCount}, file: {$path})\n"
        );
        exit(1);
    }

    $updated = str_replace($patch['before'], $patch['after'], $contents);
    if ($usesWindowsLineEndings) {
        $updated = str_replace("\n", "\r\n", $updated);
    }

    if (file_put_contents($path, $updated, LOCK_EX) === false) {
        fwrite(STDERR, "Cannot write installer patch: {$relativePath}\n");
        exit(1);
    }

    fwrite(STDOUT, "Patched {$relativePath}\n");
}
