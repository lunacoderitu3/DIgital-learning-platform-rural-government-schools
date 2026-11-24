<?php

use App\Models\Language;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('mysql')->table('languages', static function (Blueprint $table) {
            if (!Schema::connection('mysql')->hasColumn('languages', 'student_app_file')) {
                $table->string('student_app_file')->nullable()->after('file');
            }
            if (!Schema::connection('mysql')->hasColumn('languages', 'staff_app_file')) {
                $table->string('staff_app_file')->nullable()->after('student_app_file');
            }
            if (!Schema::connection('mysql')->hasColumn('languages', 'web_file')) {
                $table->string('web_file')->nullable()->after('staff_app_file');
            }
            if (!Schema::connection('mysql')->hasColumn('languages', 'image')) {
                $table->string('image')->nullable()->after('web_file');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->table('languages', static function (Blueprint $table) {
            $columns = array_filter(['student_app_file', 'staff_app_file', 'web_file', 'image'], static function ($column) {
                return Schema::connection('mysql')->hasColumn('languages', $column);
            });
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
