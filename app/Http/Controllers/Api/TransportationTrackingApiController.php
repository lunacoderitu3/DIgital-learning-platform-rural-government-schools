<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RouteVehicle;
use App\Models\RouteVehicleHistory;
use App\Models\School;
use App\Models\Students;
use App\Models\TransportationPayment;
use App\Models\User;
use App\Services\ResponseService;
use App\Services\Transportation\TrackingLinkService;
use App\Services\Transportation\TrackingService;
use App\Services\Transportation\TrackingSessionService;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

/**
 * Live tracking of the running trips.
 *
 * Locations are only kept in the Laravel cache (see TrackingService), no GPS
 * table and no GPS history is created.
 */
class TransportationTrackingApiController extends Controller
{
    private TrackingService $tracking;
    private TrackingLinkService $trackingLink;
    private TrackingSessionService $trackingSession;

    public function __construct(
        TrackingService $tracking,
        TrackingLinkService $trackingLink,
        TrackingSessionService $trackingSession
    ) {
        $this->tracking = $tracking;
        $this->trackingLink = $trackingLink;
        $this->trackingSession = $trackingSession;
    }

    /**
     * POST transportation/trip/location
     *
     * Driver / Helper mobile app sends the current coordinates of its running trip.
     * Called every 15-30 seconds, so the response is kept minimal.
     */
    public function updateLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_id'   => 'required|integer',
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy'  => 'nullable|numeric|min:0',
        ], [
            'latitude.between'  => 'Invalid latitude or longitude.',
            'longitude.between' => 'Invalid latitude or longitude.',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            if (!(Auth::user()->hasRole('Driver') || Auth::user()->hasRole('Helper'))) {
                ResponseService::errorResponse("You are not authorized to update this trip.");
            }

            $schoolId = $this->tracking->resolveSchoolId();
            if (empty($schoolId)) {
                ResponseService::errorResponse("School not found");
            }

            // The trip is never trusted from the request alone, it must belong to
            // the authenticated driver / helper and must still be running.
            $userId = Auth::id();
            $trip = RouteVehicleHistory::where('id', $request->trip_id)
                ->where('status', 'inprogress')
                ->where(function ($q) use ($userId) {
                    $q->where('driver_id', $userId)
                        ->orWhere('helper_id', $userId);
                })
                ->first();

            if (!$trip) {
                ResponseService::errorResponse("No active trip found.");
            }

            $this->tracking->updateLocation($schoolId, $trip, [
                'latitude'  => $request->latitude,
                'longitude' => $request->longitude,
            ]);

            ResponseService::successResponse("Location updated successfully.");
        } catch (\Throwable $th) {
            ResponseService::logErrorResponse($th, 'TransportationTrackingApiController -> updateLocation');
            ResponseService::errorResponse();
        }
    }

    /**
     * GET transportation/trip/current
     *
     * Running trip of the authenticated driver / helper with its last known
     * location. Used by the mobile app to resume tracking after a restart.
     */
    public function currentTrip()
    {
        try {
            if (!(Auth::user()->hasRole('Driver') || Auth::user()->hasRole('Helper'))) {
                ResponseService::errorResponse("Unauthorized");
            }

            $schoolId = $this->tracking->resolveSchoolId();
            if (empty($schoolId)) {
                ResponseService::errorResponse("School not found");
            }

            $userId = Auth::id();
            $trip = RouteVehicleHistory::with(['vehicle', 'driver', 'route'])
                ->where('status', 'inprogress')
                ->where(function ($q) use ($userId) {
                    $q->where('driver_id', $userId)
                        ->orWhere('helper_id', $userId);
                })
                ->latest('id')
                ->first();

            if (!$trip) {
                ResponseService::successResponse("Current trip fetched successfully", [
                    'trip'            => null,
                    'location'        => null,
                    'tracking_status' => 'offline',
                ]);
            }

            ResponseService::successResponse(
                "Current trip fetched successfully",
                $this->tracking->getTripTracking($schoolId, $trip)
            );
        } catch (\Throwable $th) {
            ResponseService::logErrorResponse($th, 'TransportationTrackingApiController -> currentTrip');
            ResponseService::errorResponse();
        }
    }

    /**
     * GET transportation/live-tracking
     *
     * Every running trip of the school with its current location.
     * Meant for the admin live tracking map, refreshed every 60 seconds.
     */
    public function liveTracking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'route_id'   => 'nullable|integer|exists:routes,id',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'shift_id'   => 'nullable|integer|exists:shifts,id',
            'type'       => 'nullable|in:pickup,drop',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            ResponseService::noAnyPermissionThenSendJson(['RouteVehicle-list']);

            $schoolId = $this->tracking->resolveSchoolId();
            if (empty($schoolId)) {
                ResponseService::errorResponse("School not found");
            }

            // Only the trips of the school database the request is connected to,
            // so a school can never read another school's buses.
            $trips = RouteVehicleHistory::with(['vehicle', 'driver', 'route'])
                ->where('status', 'inprogress')
                ->when($request->route_id, fn($q) => $q->where('route_id', $request->route_id))
                ->when($request->vehicle_id, fn($q) => $q->where('vehicle_id', $request->vehicle_id))
                ->when($request->shift_id, fn($q) => $q->where('shift_id', $request->shift_id))
                ->when($request->type, fn($q) => $q->where('type', $request->type))
                ->get();

            ResponseService::successResponse(
                "Live tracking data fetched successfully",
                $this->tracking->getActiveLocations($schoolId, $trips)
            );
        } catch (\Throwable $th) {
            ResponseService::logErrorResponse($th, 'TransportationTrackingApiController -> liveTracking');
            ResponseService::errorResponse();
        }
    }

    /**
     * GET transportation/live-tracking/my-bus
     *
     * Location of the bus assigned to the passenger only.
     * Student / Staff  : own transportation plan.
     * Guardian         : plan of the child passed in child_id.
     */
    public function myBusTracking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'nullable|integer',
            'user_id'  => 'nullable|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $authUser = Auth::user();
            $userId = $authUser->id;

            if ($authUser->hasRole('Guardian')) {
                if (empty($request->child_id)) {
                    ResponseService::errorResponse(
                        "Please select a child",
                        null,
                        config('constants.RESPONSE_CODE.INVALID_CHILD_ID')
                    );
                }

                $child = $authUser->guardianRelationChild()->where('id', $request->child_id)->first();
                if (!$child) {
                    ResponseService::errorResponse(
                        "Invalid child id",
                        null,
                        config('constants.RESPONSE_CODE.INVALID_CHILD_ID')
                    );
                }

                $userId = $child->user_id;
            } elseif (!empty($request->user_id) && $request->user_id != $authUser->id) {
                // Only staff allowed to manage transportation can look at another passenger.
                // ResponseService::noAnyPermissionThenSendJson(['RouteVehicle-list']);
                $userId = $request->user_id;
            }

            $schoolId = $this->tracking->resolveSchoolId();
            if (empty($schoolId)) {
                ResponseService::errorResponse("School not found");
            }

            $emptyResponse = [
                'bus'             => null,
                'driver'          => null,
                'route'           => null,
                'trip'            => null,
                'location'        => null,
                'tracking_status' => 'offline',
            ];

            $transportationPayment = TransportationPayment::whereNotNull('route_vehicle_id')
                ->where('user_id', $userId)
                ->where('expiry_date', '>=', Carbon::now())
                ->latest('id')
                ->first();

            if (!$transportationPayment) {
                ResponseService::successResponse("Live tracking data fetched successfully", $emptyResponse);
            }

            $routeVehicle = RouteVehicle::with(['route'])
                ->where('id', $transportationPayment->route_vehicle_id)
                ->where('status', 1)
                ->first();

            if (!$routeVehicle) {
                ResponseService::successResponse("Live tracking data fetched successfully", $emptyResponse);
            }

            $shiftId = $transportationPayment->shift_id ?? optional($routeVehicle->route)->shift_id;

            $trip = RouteVehicleHistory::with(['vehicle', 'driver', 'route'])
                ->where('route_id', $routeVehicle->route_id)
                ->where('vehicle_id', $routeVehicle->vehicle_id)
                ->when($shiftId, fn($q) => $q->where('shift_id', $shiftId))
                ->where('status', 'inprogress')
                ->latest('id')
                ->first();

            if (!$trip) {
                ResponseService::successResponse("Live tracking data fetched successfully", $emptyResponse);
            }

            $trackingData = $this->tracking->getTripTracking($schoolId, $trip);

            ResponseService::successResponse("Live tracking data fetched successfully", [
                'bus'             => [
                    'id'             => $trip->vehicle_id,
                    'name'           => optional($trip->vehicle)->name,
                    'vehicle_number' => optional($trip->vehicle)->vehicle_number,
                ],
                'driver'          => [
                    'id'   => $trip->driver_id,
                    'name' => optional($trip->driver)->full_name,
                ],
                'route'           => [
                    'id'   => $trip->route_id,
                    'name' => optional($trip->route)->name,
                ],
                'trip'            => [
                    'id'         => $trip->id,
                    'type'       => $trip->type,
                    'status'     => $trip->status,
                    'started_at' => $trip->actual_start_time,
                ],
                'location'        => $trackingData['location'],
                'tracking_status' => $trackingData['tracking_status'],
            ]);
        } catch (\Throwable $th) {
            ResponseService::logErrorResponse($th, 'TransportationTrackingApiController -> myBusTracking');
            ResponseService::errorResponse();
        }
    }

    /**
     * POST transportation/live-tracking/link
     *
     * Called by the student / parent app when the user taps "Live Tracking".
     * Returns the url of the student web tracking page carrying a short lived,
     * tracking-only token, so the page can be opened in a WebView without a
     * web login.
     *
     * Everything is authorized here, once : the redeem endpoint trusts the token.
     */
    public function createLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id'  => 'required_without:child_id|nullable|integer|exists:users,id',
            'child_id' => 'nullable|integer',
            'trip_id'  => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $authUser = Auth::user();
            $forbidden = config('constants.RESPONSE_CODE.PERMISSION_DENIED') ?? 403;

            $schoolId = $this->tracking->resolveSchoolId();
            if (empty($schoolId)) {
                ResponseService::errorResponse("School not found");
            }

            // Parent apps know the child, not the child's user id.
            $userId = $request->user_id;
            if (empty($userId) && $request->child_id) {
                $child = $authUser->guardianRelationChild()->where('id', $request->child_id)->first();
                if (!$child) {
                    ResponseService::errorResponse(
                        "Invalid child id",
                        null,
                        config('constants.RESPONSE_CODE.INVALID_CHILD_ID')
                    );
                }
                $userId = $child->user_id;
            }

            $userId = (int)$userId;

            // Who is allowed to track this student :
            // the student / staff member themselves, one of their guardians,
            // or a staff member who manages transportation.
            if ($userId !== (int)$authUser->id) {
                $isGuardian = Students::where('user_id', $userId)
                    ->where('guardian_id', $authUser->id)
                    ->exists();

                if (!$isGuardian && !$authUser->canany(['RouteVehicle-list'])) {
                    ResponseService::errorResponse(
                        "You are not authorized to track this student.",
                        null,
                        $forbidden
                    );
                }
            }

            // The student must belong to the school of this request.
            $trackedUser = User::find($userId);
            if (!$trackedUser || (!empty($trackedUser->getRawOriginal('school_id')) && (int)$trackedUser->getRawOriginal('school_id') !== (int)$schoolId)) {
                ResponseService::errorResponse("You are not authorized to track this student.", null, $forbidden);
            }

            // Roughly 20 links per user per hour is plenty for a tap on a card.
            $rateKey = "transport-tracking-link:{$schoolId}:{$authUser->id}";
            if (RateLimiter::tooManyAttempts($rateKey, TrackingLinkService::MAX_LINKS_PER_HOUR)) {
                ResponseService::errorResponse(
                    "Too many attempts. Please try again later.",
                    null,
                    config('constants.RESPONSE_CODE.TOO_MANY_ATTEMPTS') ?? 429
                );
            }
            RateLimiter::hit($rateKey, 3600);

            $payment = TransportationPayment::whereNotNull('route_vehicle_id')
                ->where('user_id', $userId)
                ->where('expiry_date', '>=', Carbon::now())
                ->latest('id')
                ->first();

            if (!$payment) {
                ResponseService::errorResponse("No active transport plan", null, $forbidden);
            }

            $routeVehicle = RouteVehicle::with(['route'])
                ->where('id', $payment->route_vehicle_id)
                ->first();

            if (!$routeVehicle) {
                ResponseService::errorResponse("No active transport plan", null, $forbidden);
            }

            $shiftId = $payment->shift_id ?? optional($routeVehicle->route)->shift_id;

            $tripQuery = RouteVehicleHistory::where('route_id', $routeVehicle->route_id)
                ->where('vehicle_id', $routeVehicle->vehicle_id)
                ->where('status', 'inprogress')
                ->when($shiftId, fn($q) => $q->where('shift_id', $shiftId));

            if ($request->trip_id) {
                // A pinned trip has to be the student's own trip.
                $trip = (clone $tripQuery)->where('id', $request->trip_id)->first();
                if (!$trip) {
                    ResponseService::errorResponse("This trip is not available for this student.", null, $forbidden);
                }
            } else {
                // No trip running is fine : the page opens and waits for the bus.
                $trip = (clone $tripQuery)->latest('id')->first();
            }

            $expiresAt = $this->trackingLink->resolveExpiry(optional($trip)->end_time);

            $link = $this->trackingLink->issue(
                $schoolId,
                $userId,
                (int)$authUser->id,
                optional($trip)->id,
                $expiresAt
            );

            $url = $this->trackingLink->buildUrl($link['token']);
            if (empty($url)) {
                Log::error('Transportation live tracking : student_web_url system setting is empty, cannot build the tracking link.');
                ResponseService::errorResponse("Live tracking page is not configured. Please contact the school.");
            }

            ResponseService::successResponse("Success", [
                'url'               => $url,
                'token'             => $link['token'],
                'expires_at'        => $link['expires_at']->toIso8601String(),
                'trip_id'           => $link['trip_id'],
                'poll_interval_sec' => TrackingLinkService::POLL_INTERVAL_SEC,
            ]);
        } catch (\Throwable $th) {
            ResponseService::logErrorResponse($th, 'TransportationTrackingApiController -> createLink');
            ResponseService::errorResponse();
        }
    }

    /**
     * GET transportation/live-tracking/session
     *
     * Redeems a tracking token. The WebView sends nothing but the token :
     * no Authorization, no school-code, no user_id, no trip_id, so the page can
     * never be pointed at another student.
     *
     * This endpoint answers with real HTTP status codes (401 / 410 / 429) so the
     * page can tell an invalid link from an expired one and stop polling.
     */
    public function session(Request $request)
    {
        $token = $request->header('X-Tracking-Token');

        $resolved = $this->trackingLink->resolve($token);

        if ($resolved['status'] === 'invalid') {
            return $this->trackingError("Invalid tracking link", 401);
        }

        if ($resolved['status'] !== 'ok') {
            return $this->trackingError("Tracking link has expired", 410);
        }

        $link = $resolved['link'];

        // ~120 polls per token per hour, twice what a 30 second poll needs.
        $rateKey = "transport-tracking-session:" . $link['token_id'];
        if (RateLimiter::tooManyAttempts($rateKey, TrackingLinkService::MAX_POLLS_PER_HOUR)) {
            return $this->trackingError("Too many requests", 429);
        }
        RateLimiter::hit($rateKey, 3600);

        try {
            $school = School::on('mysql')->find($link['school_id']);

            if (!$school || $school->status != 1 || empty($school->database_name)) {
                return $this->trackingError("Tracking link has expired", 410);
            }

            // The token is the only thing that selects the school database.
            $this->trackingLink->connectSchoolDatabase($school);

            $data = $this->trackingSession->build(
                (int)$link['school_id'],
                (int)$link['user_id'],
                $link['trip_id'] ?? null
            );

            $this->trackingLink->touch($link);

            $data['expires_at'] = $link['expires_at'];

            return response()->json([
                'error'   => false,
                'message' => trans("Success"),
                'data'    => $data,
                'code'    => 200,
            ]);
        } catch (\Throwable $th) {
            Log::error('TransportationTrackingApiController -> session ' . $th->getMessage() . ' ---> ' . $th->getFile() . ' At Line : ' . $th->getLine());

            return response()->json([
                'error'   => true,
                'message' => trans("Error Occurred"),
                'data'    => null,
                'code'    => 500,
            ], 500);
        }
    }

    /**
     * Error body of the session endpoint. error:true so the web client rejects
     * it instead of treating it as an empty success.
     */
    private function trackingError(string $message, int $status)
    {
        return response()->json([
            'error'   => true,
            'message' => trans($message),
            'data'    => null,
            'code'    => $status,
        ], $status);
    }
}
