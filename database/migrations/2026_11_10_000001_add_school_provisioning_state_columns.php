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
        Schema::table('schools', function (Blueprint $table) {
            if (!Schema::hasColumn('schools', 'provision_status')) {
                $table->string('provision_status')->nullable()->after('provision_step');
            }

            if (!Schema::hasColumn('schools', 'provision_progress')) {
                $table->unsignedTinyInteger('provision_progress')->default(0)->after('provision_status');
            }

            if (!Schema::hasColumn('schools', 'provision_retry_count')) {
                $table->unsignedTinyInteger('provision_retry_count')->default(0)->after('provision_progress');
            }

            if (!Schema::hasColumn('schools', 'provision_last_error')) {
                $table->text('provision_last_error')->nullable()->after('provision_retry_count');
            }

            if (!Schema::hasColumn('schools', 'provision_last_retry_at')) {
                $table->timestamp('provision_last_retry_at')->nullable()->after('provision_last_error');
            }

            if (!Schema::hasColumn('schools', 'provision_started_at')) {
                $table->timestamp('provision_started_at')->nullable()->after('provision_last_retry_at');
            }

            if (!Schema::hasColumn('schools', 'provision_completed_at')) {
                $table->timestamp('provision_completed_at')->nullable()->after('provision_started_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'provision_completed_at')) {
                $table->dropColumn('provision_completed_at');
            }
            if (Schema::hasColumn('schools', 'provision_started_at')) {
                $table->dropColumn('provision_started_at');
            }
            if (Schema::hasColumn('schools', 'provision_last_retry_at')) {
                $table->dropColumn('provision_last_retry_at');
            }
            if (Schema::hasColumn('schools', 'provision_last_error')) {
                $table->dropColumn('provision_last_error');
            }
            if (Schema::hasColumn('schools', 'provision_retry_count')) {
                $table->dropColumn('provision_retry_count');
            }
            if (Schema::hasColumn('schools', 'provision_progress')) {
                $table->dropColumn('provision_progress');
            }
            if (Schema::hasColumn('schools', 'provision_status')) {
                $table->dropColumn('provision_status');
            }
        });
    }
};
