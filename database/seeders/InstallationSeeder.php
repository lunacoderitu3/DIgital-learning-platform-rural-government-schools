<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Language;
use App\Services\CachingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\SystemSetting;

class InstallationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        /**** Create All the Permission ****/
        $this->createPermissions();

        $this->createSuperAdminRole();

        // System Features
        $this->systemFeatures();

        // Plan Expiry Email Template default

        $this->planExpiryEmailTemplate();

        //Change system version here
        Language::updateOrCreate(['id' => 1], ['name' => 'English', 'code' => 'en', 'file' => 'en.json', 'student_app_file' => 'en.json', 'staff_app_file' => 'en.json', 'web_file' => 'en.json', 'status' => 1, 'is_rtl' => 0]);

        Artisan::call('migrate:school');
        Artisan::call('db:seed --class=SchoolInstallationSeeder');
        //clear cache
        Artisan::call('view:clear');
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
    }


    /**
     * Seed default Plan Expiry Email Template only if it doesn't already exist
     */
    public function planExpiryEmailTemplate()
    {
        $exists = SystemSetting::where('name', 'plan_expiry_email_template')->exists();

        if (!$exists) {
            SystemSetting::create([
                'name' => 'plan_expiry_email_template',
                'data' => '&lt;p&gt;Dear {school_name},&lt;/p&gt;' .
                    '&lt;p&gt;This is a friendly reminder that your current subscription plan &lt;strong&gt;{current_plan}&lt;/strong&gt; on &lt;strong&gt;{system_name}&lt;/strong&gt; will expire on &lt;strong&gt;{expiry_date} &lt;/strong&gt;.&lt;/p&gt;' .
                    '&lt;hr&gt;' .
                    '&lt;p&gt;&lt;strong&gt;School Details:&lt;/strong&gt;&lt;/p&gt;' .
                    '&lt;p&gt;&lt;strong&gt;School Name:&lt;/strong&gt; {school_name}&lt;br&gt;&lt;strong&gt;Email:&lt;/strong&gt; {school_email}&lt;br&gt;&lt;strong&gt;Contact:&lt;/strong&gt; {contact}&lt;br&gt;&lt;strong&gt;Address:&lt;/strong&gt; {address}&lt;/p&gt;' .
                    '&lt;hr&gt;' .
                    '&lt;p&gt;Please renew your subscription to avoid any interruption in school management services.&lt;/p&gt;' .
                    '&lt;p&gt;Best regards,&lt;br&gt;{system_name} Team&lt;/p&gt;',
                'type' => 'text'
            ]);
        }
    }


    public function createPermissions()
    {

        $permissions = [
            ...self::permission('role'),
            ...self::permission('schools'),
            ...self::permission('package'),
            ...self::permission('addons'),
            ...self::permission('guidance'),
            ['name' => 'system-setting-manage'],
            ['name' => 'fcm-setting-create'],
            ['name' => 'email-setting-create'],
            ['name' => 'privacy-policy'],
            ['name' => 'contact-us'],
            ['name' => 'about-us'],
            ['name' => 'terms-condition'],
            ['name' => 'app-settings'],
            ['name' => 'subscription-view'],
            ...self::permission('staff'),
            ...self::permission('faqs'),
            ['name' => 'fcm-setting-manage'],
            ['name' => 'front-site-setting'],
            ['name' => 'payment-settings'],

            ['name' => 'subscription-settings'],
            ['name' => 'subscription-change-bills'],
            ['name' => 'school-terms-condition'],
            ['name' => 'subscription-bill-payment'],
            ['name' => 'web-settings'],
            ['name' => 'email-template'],
            ['name' => 'custom-school-email'],
            ['name' => 'database-backup'],
            ...self::permission('school-custom-field'),

            ['name' => 'contact-inquiry-list'],
            ...self::permission('academy-setup'),
            ...self::permission('language'),



        ];
        $permissions = array_map(static function ($data) {
            $data['guard_name'] = 'web';
            return $data;
        }, $permissions);
        Permission::upsert($permissions, ['name'], ['name']);
    }


    public function createSuperAdminRole()
    {
        $role = Role::withoutGlobalScope('school')->updateOrCreate(['name' => 'Super Admin', 'custom_role' => 0, 'editable' => 0]);
        $superAdminHasAccessTo = [
            'schools-list',
            'schools-create',
            'schools-edit',
            'schools-delete',

            'package-list',
            'package-create',
            'package-edit',
            'package-delete',

            'email-setting-create',
            'privacy-policy',
            'terms-condition',
            'contact-us',
            'about-us',
            'fcm-setting-create',
            'system-setting-manage',
            'app-settings',

            'role-list',
            'role-create',
            'role-edit',
            'role-delete',

            'staff-list',
            'staff-create',
            'staff-edit',
            'staff-delete',

            'addons-list',
            'addons-create',
            'addons-edit',
            'addons-delete',

            'subscription-view',

            'faqs-list',
            'faqs-create',
            'faqs-edit',
            'faqs-delete',

            'fcm-setting-manage',

            // 'front-site-setting',

            // 'update-admin-profile',
            'subscription-settings',
            'subscription-change-bills',
            'school-terms-condition',

            'guidance-list',
            'guidance-create',
            'guidance-edit',
            'guidance-delete',

            'subscription-bill-payment',
            'web-settings',
            'custom-school-email',

            'database-backup',

            'school-custom-field-list',
            'school-custom-field-create',
            'school-custom-field-edit',
            'school-custom-field-delete',

            'contact-inquiry-list',

            'academy-setup-list',
            'academy-setup-create',
            'academy-setup-edit',
            'academy-setup-delete',

            'language-list',
            'language-create',
            'language-edit',
            'language-delete',

        ];
        $role->syncPermissions($superAdminHasAccessTo);
    }


    /**
     * Generate List , Create , Edit , Delete Permissions
     * @param $prefix
     * @param array $customPermissions - Prefix will be set Automatically
     * @return string[]
     */
    public static function permission($prefix, array $customPermissions = [])
    {

        $list = [["name" => $prefix . '-list']];
        $create = [["name" => $prefix . '-create']];
        $edit = [["name" => $prefix . '-edit']];
        $delete = [["name" => $prefix . '-delete']];

        $finalArray = array_merge($list, $create, $edit, $delete);
        foreach ($customPermissions as $customPermission) {
            $finalArray[] = ["name" => $prefix . "-" . $customPermission];
        }
        return $finalArray;
    }

    // System Features
    public function systemFeatures()
    {
        $features = [
            ['name' => 'Student Management', 'is_default' => 1, 'status' => 1],
            ['name' => 'Academics Management', 'is_default' => 1, 'status' => 1],
            ['name' => 'Slider Management', 'is_default' => 0, 'status' => 1],
            ['name' => 'Teacher Management', 'is_default' => 1, 'status' => 1],
            ['name' => 'Session Year Management', 'is_default' => 1, 'status' => 1],
            ['name' => 'Holiday Management', 'is_default' => 0, 'status' => 1],
            ['name' => 'Timetable Management', 'is_default' => 0, 'status' => 1],
            ['name' => 'Attendance Management', 'is_default' => 0, 'status' => 1],
            ['name' => 'Exam Management', 'is_default' => 0, 'status' => 1],
            ['name' => 'Lesson Management', 'is_default' => 0, 'status' => 1],
            ['name' => 'Assignment Management', 'is_default' => 0, 'status' => 1],
            ['name' => 'Announcement Management', 'is_default' => 0, 'status' => 1],
            ['name' => 'Staff Management', 'is_default' => 0, 'status' => 1],
            ['name' => 'Finance Management', 'is_default' => 0, 'status' => 1],
            ['name' => 'Staff Leave Management', 'is_default' => 0, 'status' => 1],
            ['name' => 'Fees Management', 'is_default' => 0, 'status' => 1],
            ['name' => 'School Gallery Management', 'is_default' => 0, 'status' => 1],
            ['name' => 'ID Card - Certificate Generation', 'is_default' => 0, 'status' => 1],
            ['name' => 'Website Management', 'is_default' => 0, 'status' => 1, 'required_vps' => 0],
            ['name' => 'Chat Module', 'is_default' => 0, 'status' => 1, 'required_vps' => 0],
            ['name' => 'Transportation Module', 'is_default' => 0, 'status' => 1],
            ['name' => 'Staff Attendance Management', 'is_default' => 0, 'status' => 1],
            ['name' => 'WhatsApp Messaging', 'is_default' => 0, 'status' => 1],
            ['name' => 'Student Leave Management', 'is_default' => 0, 'status' => 1],
        ];

        foreach ($features as $key => $feature) {
            Feature::updateOrCreate(['id' => ($key + 1)], $feature);
        }
        // remove feature cache
        app(CachingService::class)->removeSystemCache(config('constants.CACHE.SYSTEM.FEATURES'));
    }
}
