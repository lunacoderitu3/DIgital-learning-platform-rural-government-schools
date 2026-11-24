<?php

use App\Models\CompulsoryFee;
use App\Models\OptionalFee;
use App\Models\PaymentTransaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QR Staff Attendance.
 *
 * Extends the existing shifts / staffs / staff_attendances tables and adds the
 * three tables the feature genuinely needs: the shift assignment history, the
 * school QR code, and the raw punch log.
 */
return new class extends Migration
{
    public function up()
    {
        // ------------------------------------------------------------------
        // 1. shifts — attendance thresholds on the existing shift master
        // ------------------------------------------------------------------
        if (Schema::hasTable('shifts')) {
            Schema::table('shifts', static function (Blueprint $table) {
                if (!Schema::hasColumn('shifts', 'full_day_minutes')) {
                    $table->unsignedInteger('full_day_minutes')->nullable()->after('end_time')
                        ->comment('Counted minutes required for a full day');
                }
                if (!Schema::hasColumn('shifts', 'half_day_minutes')) {
                    $table->unsignedInteger('half_day_minutes')->nullable()->after('full_day_minutes')
                        ->comment('Counted minutes required for a half day');
                }
            });
        }

        // ------------------------------------------------------------------
        // 2. staff_shift_assignments — one active shift per staff, effective dated
        //    staff_id references users.id, matching staff_attendances.
        // ------------------------------------------------------------------
        if (!Schema::hasTable('staff_shift_assignments')) {
            Schema::create('staff_shift_assignments', static function (Blueprint $table) {
                $table->id();
                $table->foreignId('staff_id')->comment('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
                $table->date('effective_from');
                $table->date('effective_to')->nullable()->comment('NULL = ongoing');
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['staff_id', 'effective_from', 'effective_to'], 'ssa_staff_period_index');
                $table->index(['school_id', 'shift_id']);
            });
        }

        // ------------------------------------------------------------------
        // 3. staff_attendance_qr_codes — one code per school, end date extendable
        // ------------------------------------------------------------------
        if (!Schema::hasTable('staff_attendance_qr_codes')) {
            Schema::create('staff_attendance_qr_codes', static function (Blueprint $table) {
                $table->id();
                $table->uuid('token')->unique();
                $table->string('status', 20)->default('active')->comment('active, disabled');
                $table->date('end_date')->nullable()->comment('Inclusive, in school timezone');
                $table->timestamp('generated_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->timestamps();

                $table->index(['school_id', 'status']);
            });
        }

        // ------------------------------------------------------------------
        // 4. staff_attendance_punches — raw scan events, never overwritten
        // ------------------------------------------------------------------
        if (!Schema::hasTable('staff_attendance_punches')) {
            Schema::create('staff_attendance_punches', static function (Blueprint $table) {
                $table->id();
                $table->foreignId('staff_id')->comment('user_id')->constrained('users')->cascadeOnDelete();
                $table->date('date');
                $table->string('type', 3)->comment('IN, OUT');
                $table->timestamp('punched_at');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedInteger('distance')->nullable()->comment('Metres from the school point');
                $table->unsignedBigInteger('qr_code_id')->nullable();
                $table->uuid('request_uuid')->nullable()->comment('Client scan id, makes a retry idempotent');
                $table->string('source', 10)->default('QR')->comment('QR, MANUAL');
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->timestamps();

                $table->index(['staff_id', 'date']);
                $table->index(['school_id', 'date']);
                $table->index('punched_at');
                $table->index('qr_code_id');
                $table->unique(['school_id', 'request_uuid'], 'sap_school_request_unique');

                $table->foreign('qr_code_id')->references('id')->on('staff_attendance_qr_codes')->nullOnDelete();
            });
        }

        // ------------------------------------------------------------------
        // 5. staff_attendances — extend the existing final attendance row with
        //    the calculation result and the shift snapshot used to produce it
        // ------------------------------------------------------------------
        if (Schema::hasTable('staff_attendances')) {
            Schema::table('staff_attendances', static function (Blueprint $table) {
                if (!Schema::hasColumn('staff_attendances', 'working_minutes')) {
                    $table->unsignedInteger('working_minutes')->nullable()->after('type');
                }
                if (!Schema::hasColumn('staff_attendances', 'completed_sessions')) {
                    $table->unsignedSmallInteger('completed_sessions')->nullable()->after('working_minutes');
                }
                if (!Schema::hasColumn('staff_attendances', 'incomplete_sessions')) {
                    $table->unsignedSmallInteger('incomplete_sessions')->nullable()->after('completed_sessions');
                }

                // Shift snapshot — frozen at finalization so later shift edits
                // never rewrite a finished day.
                if (!Schema::hasColumn('staff_attendances', 'shift_id')) {
                    $table->unsignedBigInteger('shift_id')->nullable()->after('incomplete_sessions');
                }
                if (!Schema::hasColumn('staff_attendances', 'shift_name')) {
                    $table->string('shift_name')->nullable()->after('shift_id');
                }
                if (!Schema::hasColumn('staff_attendances', 'shift_start_time')) {
                    $table->time('shift_start_time')->nullable()->after('shift_name');
                }
                if (!Schema::hasColumn('staff_attendances', 'shift_end_time')) {
                    $table->time('shift_end_time')->nullable()->after('shift_start_time');
                }
                if (!Schema::hasColumn('staff_attendances', 'full_day_minutes')) {
                    $table->unsignedInteger('full_day_minutes')->nullable()->after('shift_end_time');
                }
                if (!Schema::hasColumn('staff_attendances', 'half_day_minutes')) {
                    $table->unsignedInteger('half_day_minutes')->nullable()->after('full_day_minutes');
                }
                if (!Schema::hasColumn('staff_attendances', 'calculation_mode')) {
                    $table->string('calculation_mode', 20)->nullable()->after('half_day_minutes')
                        ->comment('SHIFT_BASED, DAY_BASED');
                }
                if (!Schema::hasColumn('staff_attendances', 'source')) {
                    $table->string('source', 10)->default('MANUAL')->after('calculation_mode')
                        ->comment('QR, MANUAL');
                }
                if (!Schema::hasColumn('staff_attendances', 'finalized_at')) {
                    $table->timestamp('finalized_at')->nullable()->after('source');
                }
            });

            // 2 was unused; QR finalization needs a plain half day distinct from
            // the existing first-half / second-half values.
            DB::statement("ALTER TABLE `staff_attendances` MODIFY `type` TINYINT NOT NULL COMMENT '0=Absent, 1=Present, 2=Half day, 3=Holiday, 4=First half present, 5=Second half present'");

            $this->addIndexIfMissing('staff_attendances', ['staff_id', 'date'], 'staff_attendances_staff_id_date_index');
            $this->addIndexIfMissing('staff_attendances', ['school_id', 'date'], 'staff_attendances_school_id_date_index');
        }

        // ------------------------------------------------------------------
        // 6. staff_attendance_corrections — audit trail for manual overrides
        // ------------------------------------------------------------------
        if (!Schema::hasTable('staff_attendance_corrections')) {
            Schema::create('staff_attendance_corrections', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('staff_attendance_id')->nullable();
                $table->foreignId('staff_id')->comment('user_id')->constrained('users')->cascadeOnDelete();
                $table->date('date');
                $table->tinyInteger('old_type')->nullable();
                $table->tinyInteger('new_type')->nullable();
                $table->unsignedInteger('old_working_minutes')->nullable();
                $table->unsignedInteger('new_working_minutes')->nullable();
                $table->string('reason')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->timestamps();

                $table->index(['school_id', 'date']);
                $table->index(['staff_id', 'date']);

                $table->foreign('staff_attendance_id')->references('id')->on('staff_attendances')->nullOnDelete();
            });
        }

        // ------------------------------------------------------------------
        // 7. pickup_points — latitude and longitude
        // ------------------------------------------------------------------
        if (Schema::hasTable('pickup_points')) {
            Schema::table('pickup_points', function (Blueprint $table) {
                if (!Schema::hasColumn('pickup_points', 'latitude')) {
                    $table->string('latitude')->nullable(true)->after('name');
                }
                if (!Schema::hasColumn('pickup_points', 'longitude')) {
                    $table->string('longitude')->nullable(true)->after('latitude');
                }
            });
        }


        Schema::table('subjects', function (Blueprint $table) {
            $table->string('type', 64)->nullable()->change();
        });


        Schema::table('compulsory_fees', function (Blueprint $table) {
            if (!Schema::hasColumn('compulsory_fees', 'notes')) {
                $table->text('notes')->nullable()->after('amount');
            }
        });

        Schema::table('optional_fees', function (Blueprint $table) {
            if (!Schema::hasColumn('optional_fees', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }
        });

        DB::statement("
            ALTER TABLE compulsory_fees
            MODIFY COLUMN mode
            ENUM('Cash', 'Cheque', 'Online', 'Manual')
            NULL
        ");



        $column = DB::selectOne("
            SELECT COLUMN_COMMENT
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'attendances'
            AND COLUMN_NAME = 'type'
        ");

        if ($column && !str_contains($column->COLUMN_COMMENT ?? '', '2=Leave')) {
            DB::statement("
            ALTER TABLE attendances
            MODIFY type TINYINT NOT NULL COMMENT '0=Absent, 1=Present, 2=Leave'
        ");
        }

        // First, remove existing duplicates, keeping the most recently updated row
        DB::statement("
            DELETE a1 FROM attendances a1
            INNER JOIN attendances a2
            ON a1.class_section_id = a2.class_section_id
            AND a1.student_id = a2.student_id
            AND a1.session_year_id = a2.session_year_id
            AND a1.date = a2.date
            AND a1.id < a2.id
        ");

        // Only add the unique index if it doesn't already exist
        if (!$this->indexExists('attendances', 'attendances_unique_daily_record')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->unique(
                    ['class_section_id', 'student_id', 'session_year_id', 'date'],
                    'attendances_unique_daily_record'
                );
            });
        }

        Schema::table('leaves', function (Blueprint $table) {
            //

            if (!Schema::hasColumn('leaves', 'added_by')) {
                $table->foreignId('added_by')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('leaves', 'note')) {
                $table->text('note')
                    ->nullable()
                    ->after('reason');
            }
        });


        Schema::table('compulsory_fees', function (Blueprint $table) {
            if (Schema::hasColumn('compulsory_fees', 'mode')) {
                $table->enum('mode', ['Cash', 'Cheque', 'Online', 'Manual'])
                    ->change();
            }
        });

        Schema::table('compulsory_fees', function (Blueprint $table) {
            if (!Schema::hasColumn('compulsory_fees', 'reason')) {
                $table->text('reason')->nullable()->after('notes');
            }
        });

        Schema::table('optional_fees', function (Blueprint $table) {
            if (!Schema::hasColumn('optional_fees', 'reason')) {
                $table->text('reason')->nullable();
            }
        });

        Schema::table('payment_configurations', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_configurations', 'details')) {
                $table->text('details')->nullable()->after('currency_code');
            }
        });

        // For compulsory fees
        $compulsoryFees = CompulsoryFee::whereNull('payment_transaction_id')->get();
        foreach ($compulsoryFees as $fee) {
            $gateway = 'cash';
            if ($fee->mode == 2 || $fee->mode === 'Cheque') {
                $gateway = 'cheque';
            }

            $paymentStatus = 'succeed';
            if ($fee->status === 'Pending') {
                $paymentStatus = 'pending';
            } elseif ($fee->status === 'Failed') {
                $paymentStatus = 'failed';
            }

            $transaction = PaymentTransaction::create([
                'user_id' => $fee->student_id,
                'amount' => $fee->amount + $fee->due_charges,
                'payment_gateway' => $gateway,
                'payment_status' => $paymentStatus,
                'school_id' => $fee->school_id
            ]);

            $fee->payment_transaction_id = $transaction->id;
            $fee->save();
        }

        // For optional fees
        $optionalFees = OptionalFee::whereNull('payment_transaction_id')->get();
        foreach ($optionalFees as $fee) {
            $gateway = 'cash';
            if ($fee->mode == 2 || $fee->mode === 'Cheque') {
                $gateway = 'cheque';
            }

            $paymentStatus = 'succeed';
            if ($fee->status === 'Pending') {
                $paymentStatus = 'pending';
            } elseif ($fee->status === 'Failed') {
                $paymentStatus = 'failed';
            }

            $transaction = PaymentTransaction::create([
                'user_id' => $fee->student_id,
                'amount' => $fee->amount,
                'payment_gateway' => $gateway,
                'payment_status' => $paymentStatus,
                'school_id' => $fee->school_id
            ]);

            $fee->payment_transaction_id = $transaction->id;
            $fee->save();
        }

        if (!Schema::hasTable('staff_attendance_report_logs')) {
            Schema::create('staff_attendance_report_logs', static function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->date('date')->comment('Attendance date the report covers');
                $table->string('report_type', 50)->default('missing_punch_out');
                $table->unsignedInteger('staff_count')->default(0);
                $table->text('recipients')->nullable()->comment('Comma separated addresses the report went to');
                $table->timestamp('sent_at')->nullable()->comment('NULL = claimed but not yet delivered');
                $table->timestamps();

                // The dedupe guard: a second send for the same school/date/type
                // cannot be inserted.
                $table->unique(['school_id', 'date', 'report_type'], 'sarl_school_date_type_unique');
                $table->index(['school_id', 'date']);
            });
        }

        if (Schema::hasTable('optional_fees') && Schema::hasColumn('optional_fees', 'mode')) {
            DB::statement("
                ALTER TABLE optional_fees
                MODIFY COLUMN mode
                ENUM('Cash', 'Cheque', 'Online', 'Manual')
                NULL
            ");
        }
    }

    public function down()
    {

        if (Schema::hasTable('optional_fees') && Schema::hasColumn('optional_fees', 'mode')) {
            DB::statement("
                ALTER TABLE optional_fees
                MODIFY COLUMN mode
                ENUM('Cash', 'Cheque', 'Online')
                NULL
            ");
        }

        Schema::dropIfExists('staff_attendance_report_logs');

        Schema::dropIfExists('staff_attendance_corrections');
        Schema::dropIfExists('staff_attendance_punches');
        Schema::dropIfExists('staff_attendance_qr_codes');
        Schema::dropIfExists('staff_shift_assignments');

        // ------------------------------------------------------------------
        // 7. pickup_points — latitude and longitude
        // ------------------------------------------------------------------
        if (Schema::hasTable('pickup_points')) {
            Schema::table('pickup_points', function (Blueprint $table) {
                if (Schema::hasColumn('pickup_points', 'latitude')) {
                    $table->dropColumn('latitude');
                }
                if (Schema::hasColumn('pickup_points', 'longitude')) {
                    $table->dropColumn('longitude');
                }
            });
        }

        if (Schema::hasTable('staff_attendances')) {
            Schema::table('staff_attendances', static function (Blueprint $table) {
                foreach (
                    [
                        'working_minutes',
                        'completed_sessions',
                        'incomplete_sessions',
                        'shift_id',
                        'shift_name',
                        'shift_start_time',
                        'shift_end_time',
                        'full_day_minutes',
                        'half_day_minutes',
                        'calculation_mode',
                        'source',
                        'finalized_at',
                    ] as $column
                ) {
                    if (Schema::hasColumn('staff_attendances', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('shifts')) {
            Schema::table('shifts', static function (Blueprint $table) {
                foreach (['full_day_minutes', 'half_day_minutes'] as $column) {
                    if (Schema::hasColumn('shifts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::table('optional_fees', function (Blueprint $table) {
            if (Schema::hasColumn('optional_fees', 'notes')) {
                $table->dropColumn('notes');
            }
        });

        Schema::table('compulsory_fees', function (Blueprint $table) {
            if (Schema::hasColumn('compulsory_fees', 'notes')) {
                $table->dropColumn('notes');
            }
        });


        $column = DB::selectOne("
        SELECT COLUMN_COMMENT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'attendances'
        AND COLUMN_NAME = 'type'
    ");

        if ($column && str_contains($column->COLUMN_COMMENT ?? '', '2=Leave')) {
            DB::statement("
            ALTER TABLE attendances
            MODIFY type TINYINT NOT NULL COMMENT '0=Absent, 1=Present'
        ");
        }


        if ($this->indexExists('attendances', 'attendances_unique_daily_record')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropUnique('attendances_unique_daily_record');
            });
        }

        Schema::table('leaves', function (Blueprint $table) {
            //
            if (Schema::hasColumn('leaves', 'added_by')) {
                $table->dropForeign(['added_by']);
                $table->dropColumn('added_by');
            }
            if (Schema::hasColumn('leaves', 'note')) {
                $table->dropColumn('note');
            }
        });

        Schema::table('payment_configurations', function (Blueprint $table) {
            if (Schema::hasColumn('payment_configurations', 'details')) {
                $table->dropColumn(['details']);
            }
        });

        Schema::table('optional_fees', function (Blueprint $table) {
            if (Schema::hasColumn('optional_fees', 'reason')) {
                $table->dropColumn('reason');
            }
        });

        Schema::table('compulsory_fees', function (Blueprint $table) {
            if (Schema::hasColumn('compulsory_fees', 'reason')) {
                $table->dropColumn('reason');
            }
        });

        Schema::table('compulsory_fees', function (Blueprint $table) {
            //
            if (Schema::hasColumn('compulsory_fees', 'mode')) {
                $table->enum('mode', ['Cash', 'Cheque', 'Online'])
                    ->change();
            }
        });
    }

    /**
     * Older school databases may already carry an equivalent index, and MySQL
     * errors on a duplicate name rather than skipping it.
     */
    private function addIndexIfMissing(string $table, array $columns, string $name): void
    {
        $exists = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->contains($name);

        if (!$exists) {
            Schema::table($table, static function (Blueprint $blueprint) use ($columns, $name) {
                $blueprint->index($columns, $name);
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $dbName = DB::getDatabaseName();

        $result = DB::select("
            SELECT COUNT(1) AS cnt
            FROM information_schema.statistics
            WHERE table_schema = ?
            AND table_name = ?
            AND index_name = ?
        ", [$dbName, $table, $indexName]);

        return $result[0]->cnt > 0;
    }
};
