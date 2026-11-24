<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Public Meta webhook endpoints. Each school registers its own callback URL
 * (api/whatsapp/webhook/{schoolCode}) in its Meta App configuration.
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * GET — Meta verification handshake.
     */
    public function verify(Request $request, string $schoolCode)
    {
        $service = $this->serviceForSchool($schoolCode);
        if (!$service) {
            return response('Not found', 404);
        }

        $challenge = $service->verifyWebhook(
            $request->query('hub_mode'),
            $request->query('hub_verify_token'),
            $request->query('hub_challenge')
        );

        if (!is_null($challenge)) {
            return response($challenge, 200);
        }
        return response('Forbidden', 403);
    }

    /**
     * POST — delivery status updates and inbound messages.
     */
    public function receive(Request $request, string $schoolCode)
    {
        $service = $this->serviceForSchool($schoolCode);
        if ($service) {
            try {
                $service->processWebhook($request->all());
            } catch (\Throwable $e) {
                Log::error('WhatsApp webhook processing failed: ' . $e->getMessage());
            }
        }
        // Always acknowledge, otherwise Meta keeps retrying
        return response()->json(['success' => true]);
    }

    private function serviceForSchool(string $schoolCode): ?WhatsAppService
    {
        $school = School::on('mysql')->where('code', $schoolCode)->first();
        if (!$school || empty($school->database_name)) {
            return null;
        }

        Config::set('database.connections.school.database', $school->database_name);
        DB::purge('school');
        DB::connection('school')->reconnect();
        DB::setDefaultConnection('school');

        return WhatsAppService::forSchool($school->id);
    }
}
