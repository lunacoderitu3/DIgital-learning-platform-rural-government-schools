<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\WhatsAppScheduledMessage;
use App\Services\FeaturesService;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProcessWhatsAppScheduledMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch due scheduled WhatsApp messages for every school (once / weekly / monthly)';

    public function handle()
    {
        $schools = School::on('mysql')->whereNotNull('database_name')->get(['id', 'name', 'database_name']);

        foreach ($schools as $school) {
            try {
                Config::set('database.connections.school.database', $school->database_name);
                DB::purge('school');
                DB::connection('school')->reconnect();
                DB::setDefaultConnection('school');

                if (!Schema::connection('school')->hasTable('whatsapp_scheduled_messages')) {
                    continue;
                }

                $due = WhatsAppScheduledMessage::where('status', 'pending')
                    ->where('next_run_at', '<=', now())
                    ->with('template')
                    ->get();
                if ($due->isEmpty()) {
                    continue;
                }

                // The package must still include the feature; otherwise leave the rows pending
                $features = FeaturesService::getFeatures($school->id);
                if (!in_array(WhatsAppService::FEATURE, $features)) {
                    continue;
                }

                foreach ($due as $scheduled) {
                    $this->processScheduled($school, $scheduled);
                }
            } catch (Throwable $e) {
                Log::error('whatsapp:process-scheduled failed for school ' . $school->id . ': ' . $e->getMessage());
            } finally {
                DB::setDefaultConnection('mysql');
            }
        }

        return Command::SUCCESS;
    }

    private function processScheduled(School $school, WhatsAppScheduledMessage $scheduled): void
    {
        // Template deleted or deactivated meanwhile → nothing sensible to send anymore
        if (!$scheduled->template || !$scheduled->template->status) {
            $scheduled->update(['status' => 'cancelled']);
            return;
        }

        $queued = 0;
        $skipped = 0;
        if (!empty($scheduled->userIdsArray())) {
            $result = WhatsAppService::dispatchBulk($school, $scheduled->template, $scheduled->userIdsArray(), $scheduled->variables ?? [], $scheduled->media);
            $queued += $result['queued'];
            $skipped += $result['skipped'];
        }
        if (!empty($scheduled->contacts)) {
            $result = WhatsAppService::dispatchBulkContacts($school, $scheduled->template, $scheduled->contacts, $scheduled->variables ?? [], $scheduled->media);
            $queued += $result['queued'];
            $skipped += $result['skipped'];
        }

        $this->info("School $school->id schedule #{$scheduled->id}: queued $queued, skipped $skipped");

        $updates = ['last_run_at' => now()];
        if ($scheduled->schedule_type === 'weekly') {
            $updates['next_run_at'] = $scheduled->next_run_at->addWeek();
        } elseif ($scheduled->schedule_type === 'monthly') {
            $updates['next_run_at'] = $scheduled->next_run_at->addMonthNoOverflow();
        } else {
            $updates['status'] = 'completed';
        }
        $scheduled->update($updates);
    }
}
