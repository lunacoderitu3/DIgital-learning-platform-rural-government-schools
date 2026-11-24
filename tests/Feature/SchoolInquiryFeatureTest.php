<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\FeaturesService;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolInquiryFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'schools-list']);
        Role::firstOrCreate(['name' => 'Super Admin']);
    }

    protected function createSuperAdminUser(): User
    {
        $role = Role::where('name', 'Super Admin')->firstOrFail();
        $role->givePermissionTo('schools-list');

        $user = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin.' . uniqid() . '@example.com',
            'mobile' => '9999999999',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'school_id' => null,
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function createRegularUser(): User
    {
        return User::create([
            'first_name' => 'John',
            'last_name' => 'User',
            'email' => 'user.' . uniqid() . '@example.com',
            'mobile' => '8888888888',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'school_id' => null,
        ]);
    }

    public function test_features_service_returns_empty_array_for_unauthenticated_users(): void
    {
        $this->assertSame([], FeaturesService::getFeatures());
        $this->assertFalse(FeaturesService::hasFeature('Website Management'));
        $this->assertFalse(FeaturesService::hasAnyFeature(['Website Management', 'Finance Management']));
        $this->assertFalse(FeaturesService::hasAllFeature(['Website Management']));
    }

    public function test_features_service_handles_authenticated_super_admin_without_school_context(): void
    {
        $user = $this->createSuperAdminUser();
        $this->actingAs($user);

        $this->assertSame([], FeaturesService::getFeatures());
        $this->assertFalse(FeaturesService::hasFeature('Website Management'));
    }

    public function test_features_service_handles_authenticated_school_admin_with_missing_or_invalid_school_context(): void
    {
        $user = $this->createRegularUser();
        $this->actingAs($user);

        $this->assertSame([], FeaturesService::getFeatures());
        $this->assertSame([], FeaturesService::getFeatures(999999));
    }

    public function test_inquiry_index_returns_200_for_authorized_super_admin_when_enabled(): void
    {
        $user = $this->createSuperAdminUser();
        SystemSetting::updateOrCreate(
            ['name' => 'school_inquiry'],
            ['data' => '1', 'type' => 'string']
        );

        $this->actingAs($user);

        $response = $this->get(route('inquiry.index'));

        $response->assertStatus(200);
    }

    public function test_inquiry_index_redirects_for_non_authorized_user(): void
    {
        $user = $this->createRegularUser();
        $this->actingAs($user);

        $response = $this->get(route('inquiry.index'));

        $response->assertStatus(302);
    }

    public function test_inquiry_toggle_persists_setting_for_authorized_super_admin(): void
    {
        $user = $this->createSuperAdminUser();
        SystemSetting::updateOrCreate(
            ['name' => 'school_inquiry_template'],
            ['data' => 'Welcome template', 'type' => 'text']
        );
        SystemSetting::updateOrCreate(
            ['name' => 'school_inquiry'],
            ['data' => '0', 'type' => 'string']
        );

        $this->actingAs($user);

        $response = $this->post(route('inquiry.toggle'), ['enabled' => true]);

        $response->assertRedirect(route('inquiry.index'));
        $this->assertSame('1', SystemSetting::where('name', 'school_inquiry')->value('data'));
    }
}
