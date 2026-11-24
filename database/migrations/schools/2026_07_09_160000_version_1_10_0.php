<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. whatsapp_settings
        if (!Schema::hasTable('whatsapp_settings')) {
            Schema::create('whatsapp_settings', static function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->boolean('enabled')->default(0);
                $table->string('phone_number_id')->nullable();
                $table->string('business_account_id')->nullable();
                $table->text('access_token')->nullable();
                $table->string('verify_token')->nullable();
                $table->string('default_meta_template')->nullable()
                    ->comment('Approved Meta relay template with a {{1}} body variable. All templates without an own Meta link are delivered through it.');
                $table->string('default_meta_language', 10)->nullable();
                $table->string('default_meta_media_template')->nullable()
                    ->comment('Approved Meta template with a DOCUMENT/IMAGE header + {{1}} body. Delivers media outside the 24h window.');
                $table->string('default_meta_image_template')->nullable()
                    ->comment('Approved Meta template with an IMAGE header + {{1}} body. Used when the attachment is an image.');
                $table->unsignedInteger('monthly_limit')->nullable()->comment('NULL = use package limit');
                $table->unsignedInteger('messages_sent')->default(0);
                $table->string('usage_month', 7)->nullable()->comment('Y-m month the counter belongs to');
                $table->string('status', 20)->default('disconnected');
                $table->timestamps();
            });
        }

        // 2. whatsapp_templates
        if (!Schema::hasTable('whatsapp_templates')) {
            Schema::create('whatsapp_templates', static function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->string('event');
                $table->string('type', 20)->default('automatic')->index()
                    ->comment('automatic = system event template, manual = admin created for bulk sends');
                $table->string('category')->nullable();
                $table->string('name');
                $table->string('language', 10)->default('en');
                $table->string('meta_template_name')->nullable()
                    ->comment('Approved Meta template name. When set, it is sent instead of the text body.');
                $table->boolean('meta_body_param')->default(0)
                    ->comment('Send the rendered message text as the {{1}} body parameter of the Meta template');
                $table->string('header')->nullable();
                $table->text('body');
                $table->string('footer')->nullable();
                $table->boolean('status')->default(1);
                $table->timestamps();
                $table->unique(['school_id', 'event']);
            });
        }

        // 3. whatsapp_logs
        if (!Schema::hasTable('whatsapp_logs')) {
            Schema::create('whatsapp_logs', static function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('mobile');
                $table->string('event')->nullable();
                $table->string('template_name')->nullable();
                $table->text('message')->nullable();
                $table->string('meta_message_id')->nullable()->index();
                $table->string('batch_id', 36)->nullable()->index()
                    ->comment('Groups the recipients of one bulk send');
                $table->string('status', 20)->default('pending')->index();
                $table->text('response')->nullable();
                $table->timestamps();
            });
        }

        // 4. whatsapp_scheduled_messages
        if (!Schema::hasTable('whatsapp_scheduled_messages')) {
            Schema::create('whatsapp_scheduled_messages', static function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('template_id')->constrained('whatsapp_templates')->cascadeOnDelete();
                $table->text('user_ids')->comment('Comma separated recipient user ids');
                $table->text('contacts')->nullable()
                    ->comment('JSON list of external contacts [{mobile, name}] imported from CSV');
                $table->text('variables')->nullable()->comment('JSON of shared template variable values');
                $table->text('media')->nullable()
                    ->comment('JSON {type, url, filename} attachment for the scheduled send');
                $table->string('schedule_type', 10)->default('once')->comment('once | weekly | monthly');
                $table->dateTime('next_run_at');
                $table->dateTime('last_run_at')->nullable();
                $table->string('status', 12)->default('pending')->comment('pending | completed | cancelled');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['status', 'next_run_at']);
            });
        }

        // 5. whatsapp_contacts
        if (!Schema::hasTable('whatsapp_contacts')) {
            Schema::create('whatsapp_contacts', static function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->string('name')->nullable();
                $table->string('mobile', 20);
                $table->string('tag')->nullable()->comment('Free grouping label, e.g. Alumni 2020');
                $table->string('source', 10)->default('manual')->comment('manual | csv');
                $table->timestamps();
                $table->unique(['school_id', 'mobile']);
            });
        }

        // Add country code column in users table after last_name
        if (!Schema::hasColumn('users', 'country_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('country_code', 10)->nullable()->after('last_name');
            });
        }

        // Add country code column in schools table after address
        if (!Schema::hasColumn('schools', 'country_code')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->string('country_code', 10)->nullable()->after('address');
            });
        }

        // two_factor_enabled, need to change default value 1 to 0 in users table
        if (Schema::hasColumn('users', 'two_factor_enabled')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('two_factor_enabled')->default(0)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        // Drop country code column in users table
        if (Schema::hasColumn('users', 'country_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('country_code');
            });
        }

        // Drop country code column in schools table
        if (Schema::hasColumn('schools', 'country_code')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->dropColumn('country_code');
            });
        }

        // 5. whatsapp_contacts
        Schema::dropIfExists('whatsapp_contacts');

        // 4. whatsapp_scheduled_messages
        Schema::dropIfExists('whatsapp_scheduled_messages');

        // 3. whatsapp_logs
        Schema::dropIfExists('whatsapp_logs');

        // 2. whatsapp_templates
        Schema::dropIfExists('whatsapp_templates');

        // 1. whatsapp_settings
        Schema::dropIfExists('whatsapp_settings');
    }
};
