<?php

namespace App\Http\Controllers;

use dacoto\LaravelWizardInstaller\Controllers\InstallFolderController;
use dacoto\LaravelWizardInstaller\Controllers\InstallServerController;
use Illuminate\Routing\Controller;

class InstallerController extends Controller {
    public function phpFunctionIndex() {
        if (!(new InstallServerController())->check()) {
            return redirect()->route('LaravelWizardInstaller::install.server');
        }

        if (!(new InstallFolderController())->check()) {
            return redirect()->route('LaravelWizardInstaller::install.folders');
        }

        return view('vendor.installer.steps.symlink_basedir_check', [
            'result' => $this->checkSymlink(),
            'execResult' => function_exists('exec'),
            'shellExecResult' => function_exists('shell_exec'),
            'procOpenResult' => function_exists('proc_open'),
            // 'baseDir' =>$this->checkBaseDir()
        ]);
    }

    public function checkSymlink(): bool
    {
        return function_exists('symlink');
    }
    public function checkBaseDir(): bool
    {
        $openBaseDir = ini_get('open_basedir');
        if ($openBaseDir) {
            return false;
        }
        return true;
    }

}
