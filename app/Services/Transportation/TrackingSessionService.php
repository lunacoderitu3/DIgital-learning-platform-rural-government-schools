<?php

namespace App\Services\Transportation;

use App\Models\RouteVehicle;
use App\Models\RouteVehicleHistory;
use App\Models\School;
use App\Models\Students;
use App\Models\TransportationPayment;
use App\Models\User;
use App\Services\CachingService;
use Carbon\Carbon;

/**
 * Builds the single payload the student web live tracking page renders when it
 * is opened from a WebView with a tracking token.
 *
 * Everything is derived from the token claims (school + student + trip), never
 * from the request, and it deliberately carries no passenger names.
 *
 * The school database must already be selected before calling build().
 */
class TrackingSessionService
{
    /** A fix older than this is shown as stale, the pin is kept on screen */
    public const STALE_SECONDS = 90;

    private TrackingService $tracking;

    public function __construct(TrackingService $tracking)
    {
        $this->tracking = $tracking;
    }

    /**
     * @param int      $schoolId school of the token
     * @param int      $userId   student / staff user being tracked
     * @param int|null $tripId   trip pinned in the token, if any
     */
    public function build(int $schoolId, int $userId, $tripId = null): array
    {
        $payment = TransportationPayment::with([
            'pickupPoint',
            'routeVehicle',
            'routeVehicle.route',
            'routeVehicle.vehicle',
            'routeVehicle.driver',
        ])
            ->whereNotNull('route_vehicle_id')
            ->where('user_id', $userId)
            ->where('expiry_date', '>=', now())
            ->latest('id')
            ->first();

        $routeVehicle = $payment
            ? RouteVehicle::with(['route', 'route.routePickupPoints', 'route.routePickupPoints.pickupPoint', 'route.shift', 'vehicle', 'driver'])
                ->where('id', $payment->route_vehicle_id)
                ->first()
            : null;

        $trip = $routeVehicle ? $this->resolveTrip($routeVehicle, $payment, $tripId) : null;

        $data = [
            'tracking_status'   => 'no_trip',
            'poll_interval_sec' => TrackingLinkService::POLL_INTERVAL_SEC,
            'student'           => $this->studentBlock($userId),
            'school'            => $this->schoolBlock($schoolId),
            'bus'               => $this->busBlock($trip, $routeVehicle),
            'driver'            => $this->driverBlock($trip, $routeVehicle),
            'route'             => $this->routeBlock($trip, $routeVehicle),
            'trip'              => null,
            'location'          => null,
            'last_reached_stop' => null,
            'previous_location' => '-',
            'next_location'     => '-',
            'user_stop'         => null,
            'eta'               => ['user_stop_min' => null, 'school_stop_min' => null],
            'stops'             => [],
        ];

        if (!$trip) {
            // Plan is active but the bus has not started : the page shows its
            // "waiting for the bus" state, with the scheduled stop times.
            $data['stops'] = $routeVehicle
                ? $this->scheduledStops($routeVehicle, optional($payment)->pickup_point_id)
                : [];
            $data['user_stop'] = $this->pickUserStop($data['stops']);
            $data['previous_location'] = '-';
            $data['next_location'] = count($data['stops']) > 0 ? ($data['stops'][0]['name'] ?? '-') : '-';

            return $data;
        }

        $location = $this->tracking->getLocation($schoolId, $trip->id);

        $data['tracking_status'] = $location ? 'live' : 'offline';
        $data['trip'] = [
            'id'         => $trip->id,
            'type'       => $trip->type,
            'status'     => $trip->status,
            'date'       => $trip->date,
            'started_at' => $trip->actual_start_time,
            'shift_time' => $this->shiftBlock($routeVehicle, $trip),
        ];
        $data['location'] = $this->locationBlock($location);

        $timeline = $this->buildTimeline($trip, $routeVehicle, optional($payment)->pickup_point_id);

        $data['stops'] = $timeline['stops'];
        $data['user_stop'] = $timeline['user_stop'];
        $data['last_reached_stop'] = $timeline['last_reached_stop'];
        $data['previous_location'] = $timeline['previous_location'];
        $data['next_location'] = $timeline['next_location'];
        $data['eta'] = $timeline['eta'];

        return $data;
    }

    /* ------------------------------------------------------------------
     | Trip
     |------------------------------------------------------------------*/

    /**
     * A pinned trip is honoured only while it still belongs to the student's
     * route/vehicle and is still running, otherwise the running trip of that
     * route is used.
     */
    private function resolveTrip(RouteVehicle $routeVehicle, $payment, $tripId)
    {
        $shiftId = optional($payment)->shift_id ?? optional($routeVehicle->route)->shift_id;

        $query = RouteVehicleHistory::with(['vehicle', 'driver', 'route', 'route.routePickupPoints', 'route.routePickupPoints.pickupPoint'])
            ->where('route_id', $routeVehicle->route_id)
            ->where('vehicle_id', $routeVehicle->vehicle_id)
            ->where('status', 'inprogress');

        if ($shiftId) {
            $query->where('shift_id', $shiftId);
        }

        if ($tripId) {
            return (clone $query)->where('id', $tripId)->first();
        }

        return $query->latest('id')->first();
    }

    /* ------------------------------------------------------------------
     | Blocks
     |------------------------------------------------------------------*/

    private function studentBlock(int $userId): array
    {
        $student = Students::where('user_id', $userId)->first();
        $user = User::find($userId);

        return [
            'id'      => optional($student)->id,
            'user_id' => $userId,
            'name'    => optional($user)->full_name,
        ];
    }

    private function schoolBlock(int $schoolId): array
    {
        $school = School::on('mysql')->find($schoolId);
        $settings = app(CachingService::class)->getSchoolSettings('*', $schoolId);

        return [
            'id'                   => $schoolId,
            'name'                 => optional($school)->name,
            // Same base64 encoding the school-settings API already uses.
            'google_maps_api_key'  => $settings['google_maps_api_key'] ?? '',
            'google_map_link'      => $settings['google_map_link'] ?? '',
        ];
    }

    private function busBlock($trip, $routeVehicle)
    {
        $vehicle = $trip ? $trip->vehicle : optional($routeVehicle)->vehicle;

        if (!$vehicle) {
            return null;
        }

        return [
            'id'             => $vehicle->id,
            'name'           => $vehicle->name,
            'vehicle_number' => $vehicle->vehicle_number,
        ];
    }

    private function driverBlock($trip, $routeVehicle)
    {
        $driver = $trip ? $trip->driver : optional($routeVehicle)->driver;

        if (!$driver) {
            return null;
        }

        return [
            'id'   => $driver->id,
            'name' => $driver->full_name,
        ];
    }

    private function routeBlock($trip, $routeVehicle)
    {
        $route = $trip ? $trip->route : optional($routeVehicle)->route;

        if (!$route) {
            return null;
        }

        return [
            'id'   => $route->id,
            'name' => $route->name,
        ];
    }

    private function shiftBlock($routeVehicle, $trip)
    {
        $shift = optional(optional($routeVehicle)->route)->shift;

        if (!$shift) {
            return null;
        }

        return [
            'label' => $shift->name,
            'from'  => $trip->start_time ? date('H:i', strtotime($trip->start_time)) : $this->time($shift->start_time),
            'to'    => $trip->end_time ? date('H:i', strtotime($trip->end_time)) : $this->time($shift->end_time),
        ];
    }

    /**
     * Cached location, with its age so the page can hold the last pin while the
     * bus is out of coverage.
     */
    private function locationBlock($location)
    {
        if (empty($location['updated_at'])) {
            return null;
        }

        $ageSeconds = (int)Carbon::parse($location['updated_at'])->diffInSeconds(now(), true);

        return [
            'latitude'   => $location['latitude'],
            'longitude'  => $location['longitude'],
            'updated_at' => $location['updated_at'],
            'age_sec'    => $ageSeconds,
            'is_stale'   => $ageSeconds > self::STALE_SECONDS,
        ];
    }

    /* ------------------------------------------------------------------
     | Stops and ETA
     |------------------------------------------------------------------*/

    /**
     * Stops of the route with their scheduled times only, used while no trip
     * is running.
     */
    private function scheduledStops(RouteVehicle $routeVehicle, $userPickupPointId): array
    {
        $points = optional($routeVehicle->route)->routePickupPoints;

        if (empty($points)) {
            return [];
        }

        $activePickupPointIds = \App\Models\TransportationPayment::where('route_vehicle_id', $routeVehicle->id)
            ->where('shift_id', optional($routeVehicle->route)->shift_id)
            ->where('expiry_date', '>=', now()->startOfDay())
            ->pluck('pickup_point_id')
            ->toArray();

        $points = $points->filter(function($p) use ($activePickupPointIds) {
            return in_array($p->pickup_point_id, $activePickupPointIds);
        });

        $points = $points->sortBy(fn($point) => $point->pickup_time ?? $point->order)->values();

        $stops = [];
        foreach ($points as $index => $point) {
            $stops[] = [
                'id'             => $point->pickup_point_id,
                'name'           => optional($point->pickupPoint)->name,
                'sequence'       => $point->order ?? ($index + 1),
                'latitude'       => optional($point->pickupPoint)->latitude,
                'longitude'      => optional($point->pickupPoint)->longitude,
                'scheduled_time' => $this->time($point->pickup_time),
                'estimated_time' => null,
                'actual_time'    => 'Pending',
                'is_user_stop'   => $userPickupPointId && $point->pickup_point_id == $userPickupPointId,
            ];
        }

        return $stops;
    }

    /**
     * Stops, ETA per stop and the two ETA numbers the page shows.
     * Same delay maths the live-route API already uses : the trip's own delay
     * plus the drift since the last stop the driver marked as reached.
     */
    private function buildTimeline(RouteVehicleHistory $trip, $routeVehicle, $userPickupPointId): array
    {
        $empty = [
            'stops'             => [],
            'user_stop'         => null,
            'last_reached_stop' => null,
            'previous_location' => '-',
            'next_location'     => '-',
            'eta'               => ['user_stop_min' => null, 'school_stop_min' => null],
        ];

        $points = optional($trip->route)->routePickupPoints ?? optional(optional($routeVehicle)->route)->routePickupPoints;

        if (empty($points)) {
            return $empty;
        }

        // Filter out stops that have no active students assigned
        $activePickupPointIds = \App\Models\TransportationPayment::where('route_vehicle_id', $routeVehicle->id)
            ->where('shift_id', $trip->shift_id ?? optional($routeVehicle->route)->shift_id)
            ->where('expiry_date', '>=', now()->startOfDay())
            ->pluck('pickup_point_id')
            ->toArray();

        $points = $points->filter(function($p) use ($activePickupPointIds) {
            return in_array($p->pickup_point_id, $activePickupPointIds);
        });

        $isPickup = $trip->type === 'pickup';

        $points = $isPickup
            ? $points->sortBy(fn($point) => $point->pickup_time ?? $point->order)->values()
            : $points->sortBy(fn($point) => $point->drop_time ?? $point->order)->values();

        // Delay of the trip itself, signed : negative means it left early.
        $delayMinutes = 0;
        if ($trip->start_time && $trip->actual_start_time) {
            $delayMinutes = Carbon::parse($trip->start_time)
                ->diffInMinutes(Carbon::parse($trip->actual_start_time), false);
        }

        // Extra drift accumulated since the driver marked the last stop.
        $driftMinutes = 0;
        if ($trip->last_pickup_point_id && $trip->updated_at) {
            $driftMinutes = Carbon::parse($trip->updated_at)->diffInMinutes(now(), false);
            $driftMinutes = max(0, $driftMinutes);
        }

        $lastReachedIndex = null;
        if ($trip->last_pickup_point_id) {
            foreach ($points as $index => $point) {
                if ($point->pickup_point_id == $trip->last_pickup_point_id) {
                    $lastReachedIndex = $index;
                    break;
                }
            }
        }

        $stops = [];
        $userStop = null;
        $userEta = null;

        foreach ($points as $index => $point) {
            $scheduled = $isPickup ? $point->pickup_time : $point->drop_time;
            $reached = $lastReachedIndex !== null && $index <= $lastReachedIndex;

            $estimated = null;
            if (!$reached && $scheduled) {
                $estimated = Carbon::parse($scheduled)
                    ->addMinutes($delayMinutes)
                    ->addMinutes($driftMinutes);
            }

            $isUserStop = $userPickupPointId && $point->pickup_point_id == $userPickupPointId;

            $stop = [
                'id'             => $point->pickup_point_id,
                'name'           => optional($point->pickupPoint)->name,
                'sequence'       => $point->order ?? ($index + 1),
                'latitude'       => optional($point->pickupPoint)->latitude,
                'longitude'      => optional($point->pickupPoint)->longitude,
                'scheduled_time' => $this->time($scheduled),
                'estimated_time' => $reached ? 'Reached' : ($estimated ? $estimated->format('H:i') : null),
                'actual_time'    => $reached ? 'Reached' : 'Pending',
                'is_user_stop'   => (bool)$isUserStop,
            ];

            $stops[] = $stop;

            if ($isUserStop) {
                $userStop = $stop;
                $userEta = $reached ? null : $estimated;
            }
        }

        // School stop, the end of the trip.
        $schoolEtaMinutes = null;
        if ($trip->actual_end_time) {
            $schoolEtaMinutes = 'Reached';
        } elseif ($trip->end_time) {
            $schoolEta = Carbon::parse($trip->end_time)
                ->addMinutes($delayMinutes)
                ->addMinutes($driftMinutes);

            $schoolEtaMinutes = (int)now()->diffInMinutes($schoolEta, false);
            if ($schoolEtaMinutes < 0) {
                $schoolEtaMinutes = 'Delayed';
            }
        }

        $userEtaMinutes = null;
        if ($userStop) {
            if ($userEta) {
                $userEtaMinutes = (int)now()->diffInMinutes($userEta, false);
                if ($userEtaMinutes < 0) {
                    $userEtaMinutes = 'Delayed';
                }
            } else {
                $userEtaMinutes = 'Reached';
            }
        }

        $lastReached = null;
        if ($lastReachedIndex !== null && isset($stops[$lastReachedIndex])) {
            $lastReached = [
                'id'   => $stops[$lastReachedIndex]['id'],
                'name' => $stops[$lastReachedIndex]['name'],
            ];
        }

        $previousLocation = '-';
        $nextLocation = '-';
        $totalStops = count($stops);

        if ($totalStops > 0) {
            if ($lastReachedIndex === null) {
                // Not reached any stop yet
                $previousLocation = '-';
                $nextLocation = $stops[0]['name'] ?? '-';
            } else {
                if ($lastReachedIndex === 0) {
                    $previousLocation = '-';
                } else {
                    $previousLocation = $stops[$lastReachedIndex - 1]['name'] ?? '-';
                }

                if ($lastReachedIndex + 1 < $totalStops) {
                    $nextLocation = $stops[$lastReachedIndex + 1]['name'] ?? '-';
                } else {
                    $nextLocation = $trip->type === 'pickup' ? 'School' : '-';
                }
            }
        }

        return [
            'stops'             => $stops,
            'user_stop'         => $userStop,
            'last_reached_stop' => $lastReached,
            'previous_location' => $previousLocation,
            'next_location'     => $nextLocation,
            'eta'               => [
                'user_stop_min'   => $userEtaMinutes,
                'school_stop_min' => $schoolEtaMinutes,
            ],
        ];
    }

    private function pickUserStop(array $stops)
    {
        foreach ($stops as $stop) {
            if (!empty($stop['is_user_stop'])) {
                return $stop;
            }
        }

        return null;
    }

    private function time($value)
    {
        return $value ? date('H:i', strtotime($value)) : null;
    }
}
