<?php

use App\Models\ClassSubject;
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
        // if roll_number column not exist in promote_students table add it as default null
        if (!Schema::hasColumn('promote_students', 'roll_number')) {
            Schema::table('promote_students', function (Blueprint $table) {
                $table->string('roll_number')->nullable(true)->after('status');
            });
        }

        // Delete class_subjects records if deleted_at is not null
        ClassSubject::whereNotNull('deleted_at')->forceDelete();


        // add domain_type column if not there in schools table
        if (!Schema::hasColumn('schools', 'domain_type')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->string('domain_type')->nullable(true)->after('domain');
            });
        }

        if (!Schema::hasColumn('schools', 'type')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->string('type')->nullable(true)->after('domain_type');
            });
        }


        // student_online_exam_statuses in this table add column if not there total_questions, obtained_marks, total_marks
        // First need to check if not there then add and update existing data with default values 0
        Schema::table('student_online_exam_statuses', function (Blueprint $table) {
            if (!Schema::hasColumn('student_online_exam_statuses', 'total_questions')) {
                $table->integer('total_questions')->default(0)->after('status');
            }
            if (!Schema::hasColumn('student_online_exam_statuses', 'obtained_marks')) {
                $table->integer('obtained_marks')->default(0)->after('total_questions');
            }
            if (!Schema::hasColumn('student_online_exam_statuses', 'total_marks')) {
                $table->integer('total_marks')->default(0)->after('obtained_marks');
            }
        });

        // Now we need to update and calculate total_questions, obtained_marks, total_marks from online_exam_student_answers table and get data from online_exam_student_answers, online_exam_question_choices, online_exams. Calulation logic like this get online_exam_id from student_online_exam_statuses and fetch all the data from online_exam_student_answers with online_exam_id and calcualte obtained_marks. Get total questions and total marks from online_exam_question_choices with online_exam_id condition. 
        DB::table('student_online_exam_statuses')->orderBy('id')->chunk(100, function ($statuses) {
            foreach ($statuses as $status) {
                $onlineExamId = $status->online_exam_id;
                $studentId = $status->student_id;

                $totalQuestions = DB::table('online_exam_question_choices')
                    ->where('online_exam_id', $onlineExamId)
                    ->count();

                $totalMarks = DB::table('online_exam_question_choices')
                    ->where('online_exam_id', $onlineExamId)
                    ->sum('marks');

                $obtainedMarks = DB::table('online_exam_student_answers')
                    ->where('online_exam_id', $onlineExamId)
                    ->where('student_id', $studentId)
                    ->where('true_answer', 1)
                    ->sum('question_marks');

                DB::table('student_online_exam_statuses')
                    ->where('id', $status->id)
                    ->update([
                        'total_questions' => $totalQuestions,
                        'total_marks' => $totalMarks,
                        'obtained_marks' => $obtainedMarks,
                    ]);
            }
        });


        // If class_subject_id found in assignments table then i need to assign nullable value true

        if (Schema::hasColumn('assignments', 'class_subject_id')) {
            Schema::table('assignments', function (Blueprint $table) {
                $table->unsignedBigInteger('class_subject_id')->nullable(true)->change();
            });
        }

        if (Schema::hasColumn('assignments', 'class_section_id')) {
            Schema::table('assignments', function (Blueprint $table) {
                $table->unsignedBigInteger('class_section_id')->nullable(true)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {


        // drop the column if there total_questions, obtained_marks, total_marks
        Schema::table('student_online_exam_statuses', function (Blueprint $table) {
            if (Schema::hasColumn('student_online_exam_statuses', 'total_questions')) {
                $table->dropColumn('total_questions');
            }
            if (Schema::hasColumn('student_online_exam_statuses', 'obtained_marks')) {
                $table->dropColumn('obtained_marks');
            }
            if (Schema::hasColumn('student_online_exam_statuses', 'total_marks')) {
                $table->dropColumn('total_marks');
            }
        });


        // remove domain_type column if there in schools table
        if (Schema::hasColumn('schools', 'domain_type')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->dropColumn('domain_type');
            });
        }

        if (Schema::hasColumn('schools', 'type')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }


        // if roll_number column exist in promote_students table remove it
        if (Schema::hasColumn('promote_students', 'roll_number')) {
            Schema::table('promote_students', function (Blueprint $table) {
                $table->dropColumn('roll_number');
            });
        }
    }
};
