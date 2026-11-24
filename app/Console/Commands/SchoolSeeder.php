<?php

namespace App\Console\Commands;

use App\Jobs\SchoolDatabaseSeederJob;
use App\Models\School;
use App\Services\SchoolDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class SchoolSeeder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:seed:school {--school_id=}';

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
                SchoolDatabaseSeederJob::dispatch($school);
            }
        });
    }
}
