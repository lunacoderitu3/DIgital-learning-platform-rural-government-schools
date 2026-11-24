<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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

        // Add country code column in school_inquiries table after school_address
        if (!Schema::hasColumn('school_inquiries', 'country_code')) {
            Schema::table('school_inquiries', function (Blueprint $table) {
                $table->string('country_code', 10)->nullable()->after('school_address');
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
     */
    public function down(): void
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

        // Drop country code column in school_inquiries table
        if (Schema::hasColumn('school_inquiries', 'country_code')) {
            Schema::table('school_inquiries', function (Blueprint $table) {
                $table->dropColumn('country_code');
            });
        }
    }
};
