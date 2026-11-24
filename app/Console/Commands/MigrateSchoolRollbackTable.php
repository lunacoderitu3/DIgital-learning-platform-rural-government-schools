<?php

namespace App\Console\Commands;

use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class MigrateSchoolRollbackTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:school:rollback {--school_id=}';

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

        $query = School::withTrashed();
        if ($schoolId) {
            $schoolIds = explode(',', $schoolId);
            $query->whereIn('id', $schoolIds);
        }
        $schools = $query->get();

        foreach ($schools as $key => $school) {
            Config::set('database.connections.school.database', $school->database_name);
            DB::purge('school');
            DB::connection('school')->reconnect();
            DB::setDefaultConnection('school');

            Artisan::call('migrate:rollback', [
                '--database' => 'school',
                '--path' => 'database/migrations/schools'
            ]);

            $this->info('School migrated rollback successfully: ' . $school->name);
        }
    }
}
