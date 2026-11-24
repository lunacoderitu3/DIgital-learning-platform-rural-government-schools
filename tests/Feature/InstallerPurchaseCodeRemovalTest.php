<?php

namespace Tests\Feature;

use Tests\TestCase;

class InstallerPurchaseCodeRemovalTest extends TestCase
{
    public function test_installation_flows_from_folder_checks_to_database_without_purchase_code(): void
    {
        $this->get(route('LaravelWizardInstaller::install.index'))
            ->assertOk()
            ->assertSee('RITU SCHOOL - SCHOOL MANAGEMENT SYSTEM INSTALLATION')
            ->assertDontSee('Purchase Code');

        $this->get(route('LaravelWizardInstaller::install.folders'))
            ->assertOk()
            ->assertSee(route('install.php-function.index'));

        $this->get(route('install.php-function.index'))
            ->assertOk()
            ->assertSee(route('LaravelWizardInstaller::install.database'));

        $this->get(route('LaravelWizardInstaller::install.database'))
            ->assertOk()
            ->assertDontSee('Purchase Code');

        $this->get('/install/purchase-code')->assertNotFound();
    }
}
