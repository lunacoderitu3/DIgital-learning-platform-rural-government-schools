<?php

use App\Models\ClassSubject;
use App\Models\OnlineExamQuestion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations on each school (tenant) database.
     *
     * Mirrors the Expense module tables (expense_categories / expenses).
     * Applied to existing schools via `php artisan migrate:school` and to
     * new schools via SchoolDataService::createDatabaseMigration().
     */
    public function up(): void
    {
        if (!Schema::hasTable('income_categories')) {
            Schema::create('income_categories', static function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('description')->nullable(true);
                $table->tinyInteger('status')->default(1);
                $table->foreignId('school_id')->references('id')->on('schools')->onDelete('cascade');
                $table->unsignedBigInteger('created_by')->nullable(true);
                $table->unsignedBigInteger('updated_by')->nullable(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('incomes')) {
            Schema::create('incomes', static function (Blueprint $table) {
                $table->id();
                $table->foreignId('income_category_id')->nullable(true)->references('id')->on('income_categories')->onDelete('cascade');
                $table->string('title', 512);
                $table->string('reference_no')->nullable(true);
                $table->double('amount', 16, 2);
                $table->date('date');
                $table->string('description')->nullable(true);
                $table->foreignId('school_id')->references('id')->on('schools')->onDelete('cascade');
                $table->foreignId('session_year_id')->references('id')->on('session_years')->onDelete('cascade');
                $table->unsignedBigInteger('created_by')->nullable(true);
                $table->unsignedBigInteger('updated_by')->nullable(true);
                $table->timestamps();

                $table->index(['school_id', 'session_year_id'], 'incomes_school_session_index');
                $table->index('reference_no', 'incomes_reference_no_index');
                $table->index('date', 'incomes_date_index');
            });
        }



        if (!Schema::hasTable('fee_discounts')) {
            Schema::create('fee_discounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('fees_id')->constrained('fees')->cascadeOnDelete();

                $table->string('discount_scope'); // 'compulsory', 'optional', 'entire'
                $table->string('discount_name');
                $table->string('discount_type'); // 'fixed', 'percentage'
                $table->decimal('discount_value', 10, 2);

                $table->date('discount_date');
                $table->text('description')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('fee_discount_items')) {
            Schema::create('fee_discount_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fee_discount_id')->constrained('fee_discounts')->cascadeOnDelete();
                $table->string('fee_type'); // 'optional', 'installment'
                $table->unsignedBigInteger('fee_type_id'); // ID of OptionalFee/FeesClassType or FeesInstallment

                $table->decimal('original_amount', 10, 2)->default(0);
                $table->decimal('discount_amount', 10, 2)->default(0);
                $table->decimal('final_amount', 10, 2)->default(0);

                $table->timestamps();
            });
        }

        if (!Schema::hasTable('fee_discount_logs')) {
            Schema::create('fee_discount_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fee_discount_id')->constrained('fee_discounts')->cascadeOnDelete();
                $table->string('action'); // 'created', 'rollback'
                $table->json('old_data')->nullable();
                $table->json('new_data')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // first check if column exists or not
        Schema::table('online_exam_questions', function (Blueprint $table) {
            if (!Schema::hasColumn('online_exam_questions', 'syllabus_id')) {
                $table->foreignId('syllabus_id')->nullable()->after('class_id')->constrained('syllabus')->cascadeOnDelete();
            }
        });

        $questions = OnlineExamQuestion::get();

        foreach ($questions as $question) {
            if ($question->class_subject_id) {
                $classSubject = ClassSubject::where('id', $question->class_subject_id)->first();
                if ($classSubject) {
                    $question->syllabus_id = $classSubject->syllabus_id;
                    $question->save();
                }
            }
        }

        // remove class_subject_id column
        Schema::table('online_exam_questions', function (Blueprint $table) {
            $table->dropForeign(['class_subject_id']);
            $table->dropColumn('class_subject_id');
        });

        Schema::table('timetables', function (Blueprint $table) {
            if (!Schema::hasColumn('timetables', 'start_date')) {
                // Date from which the online class becomes active
                $table->date('start_date')->nullable()->after('day');
            }
            if (!Schema::hasColumn('timetables', 'end_date')) {
                // Last date until the class repeats (null for "No Repeat")
                $table->date('end_date')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('timetables', 'repeat')) {
                // Repeat type : No Repeat / Every Class Period / Weekly
                $table->string('repeat')->nullable()->after('end_date');
            }
            if (!Schema::hasColumn('timetables', 'meeting_link')) {
                // Google Meet / Zoom / Microsoft Teams / any meeting URL
                $table->string('meeting_link')->nullable()->after('repeat');
            }
        });

        // Make image column nullable true in subjects table
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('image')->nullable(true)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incomes');
        Schema::dropIfExists('income_categories');

        Schema::table('online_exam_questions', function (Blueprint $table) {
            //
            $table->dropForeign(['syllabus_id']);
            $table->dropColumn('syllabus_id');
        });

        // create class_subject_id column
        Schema::table('online_exam_questions', function (Blueprint $table) {
            $table->foreignId('class_subject_id')->nullable()->after('class_id')->constrained('class_subjects')->cascadeOnDelete();
        });

        Schema::table('timetables', function (Blueprint $table) {
            foreach (['start_date', 'end_date', 'repeat', 'meeting_link'] as $column) {
                if (Schema::hasColumn('timetables', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('fee_discount_logs');
        Schema::dropIfExists('fee_discount_items');
        Schema::dropIfExists('fee_discounts');
    }
};
