<?php

namespace Tests\Feature;

use App\Jobs\SetupSchoolDatabase;
use App\Models\School;
use App\Services\CachingService;
use App\Services\SchoolDataService;
use App\Services\SchoolUrlService;
use App\Services\SubscriptionService;
use App\Repositories\SystemSetting\SystemSettingInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class SchoolProvisioningLifecycleTest extends TestCase
{
    public function test_setup_school_database_marks_failed_when_tenant_setup_throws(): void
    {
        $school = School::create([
            'name' => 'Provisioning Failure Test ' . uniqid(),
            'address' => 'Test Street',
            'support_phone' => '9888888888',
            'support_email' => 'provision-fail-' . uniqid() . '@example.com',
            'tagline' => 'Test Tagline',
            'logo' => 'dummy_logo.jpg',
            'domain' => 'provision-fail-' . uniqid(),
            'database_name' => 'eschool_saas_test_' . uniqid(),
            'code' => 'TEST' . uniqid(),
            'type' => 'custom',
            'domain_type' => 'default',
            'installed' => 0,
            'status' => 0,
        ]);

        $schoolService = Mockery::mock(SchoolDataService::class);
        $schoolService->shouldReceive('createDatabaseMigration')->once()->andThrow(new \RuntimeException('database migration failed'));

        $this->app->instance(SchoolDataService::class, $schoolService);

        $job = new SetupSchoolDatabase($school->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('database migration failed');

        $job->handle(
            $schoolService,
            app(SubscriptionService::class),
            app(CachingService::class),
            app(SystemSettingInterface::class)
        );

        $school->refresh();
        $this->assertSame('failed', $school->provision_status);
        $this->assertSame(0, (int) $school->installed);
        $this->assertNotNull($school->provision_last_error);
    }

    public function test_school_url_service_avoids_invalid_localhost_host_builds(): void
    {
        $this->assertSame('http://demo.localhost:8000', SchoolUrlService::buildDefaultSchoolUrl('demo', '127.0.0.1', 8000));
        $this->assertSame('http://demo.localhost', SchoolUrlService::buildDefaultSchoolUrl('demo', 'localhost', 80));
        $this->assertSame('https://demo.example.com', SchoolUrlService::buildDefaultSchoolUrl('demo', 'example.com', 443));
        $this->assertStringNotContainsString('127.0.0.1', SchoolUrlService::buildDefaultSchoolUrl('demo', '127.0.0.1', 8000));
    }
}
