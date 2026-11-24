<?php

namespace App\Console\Commands;

use App\Jobs\MigrateSchoolDatabaseJob;
use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrateSchoolTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:school {--school_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        $schoolId = $this->option('school_id');

        $query = School::where('installed', 1);

        if ($schoolId) {
            $schoolIds = explode(',', $schoolId);
            $query->whereIn('id', $schoolIds);
        }

        $query->chunk(100, function ($schools) {
            foreach ($schools as $school) {
                MigrateSchoolDatabaseJob::dispatch($school);
            }
        });
    }
}
