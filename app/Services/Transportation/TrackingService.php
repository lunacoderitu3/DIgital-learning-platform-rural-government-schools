<?php

namespace App\Services\Transportation;

use App\Models\RouteVehicleHistory;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Handles the live location of a running trip.
 *
 * Locations are never stored in the database. Only the latest location of a
 * trip is kept in the Laravel cache (currently CACHE_DRIVER=file) and it expires
 * automatically, so a driver that goes offline stops being tracked without any
 * cron/cleanup job. Every cache operation goes through this service so the
 * store can be switched to Redis later without touching the controllers.
 */
class TrackingService
{
    /** How long a location stays in the cache */
    public const TTL_SECONDS = 180;

    /** A location newer than this is considered live */
    public const LIVE_SECONDS = 90;

    /**
     * Cache key of a trip location.
     * The school id is part of the key because every school has its own database,
     * but they all share the same cache store.
     */
    public function getCacheKey(int $schoolId, int $tripId): string
    {
        return "transportation:school:{$schoolId}:trip:{$tripId}:location";
    }

    /**
     * School of the current request. Falls back to the school-code header
     * for users that do not carry a school_id (ex : Guardian without child_id).
     */
    public function resolveSchoolId()
    {
        $schoolId = Auth::user()->school_id ?? null;
        if (!empty($schoolId)) {
            return (int)$schoolId;
        }

        $schoolCode = request()->header('school-code');
        if (!empty($schoolCode)) {
            return School::on('mysql')->where('code', $schoolCode)->value('id');
        }

        return null;
    }

    /**
     * Store / overwrite the latest location of a trip.
     */
    public function updateLocation(int $schoolId, RouteVehicleHistory $trip, array $location): array
    {
        $locationData = [
            'trip_id'    => $trip->id,
            'bus_id'     => $trip->vehicle_id,
            'driver_id'  => $trip->driver_id,
            'latitude'   => (float)$location['latitude'],
            'longitude'  => (float)$location['longitude'],
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::store('transportation')->put(
            $this->getCacheKey($schoolId, $trip->id),
            $locationData,
            now()->addSeconds(self::TTL_SECONDS)
        );

        return $locationData;
    }

    /**
     * Latest cached location of a trip. Null when the driver never sent one
     * or the entry already expired.
     */
    public function getLocation(int $schoolId, int $tripId)
    {
        $location = Cache::store('transportation')->get($this->getCacheKey($schoolId, $tripId));

        return is_array($location) ? $location : null;
    }

    /**
     * Drop the location. Called as soon as a trip ends, so the bus disappears
     * from the map without waiting for the TTL.
     */
    public function removeLocation(int $schoolId, int $tripId): void
    {
        Cache::store('transportation')->forget($this->getCacheKey($schoolId, $tripId));
    }

    /**
     * live     : updated within the last 90 seconds
     * delayed  : updated between 90 and 180 seconds ago
     * offline  : no location at all / older than the TTL
     */
    public function getTrackingStatus($location): string
    {
        if (empty($location['updated_at'])) {
            return 'offline';
        }

        $seconds = Carbon::parse($location['updated_at'])->diffInSeconds(now(), true);

        if ($seconds <= self::LIVE_SECONDS) {
            return 'live';
        }

        if ($seconds <= self::TTL_SECONDS) {
            return 'delayed';
        }

        return 'offline';
    }

    /**
     * Location block of the API response. Null when nothing can be shown.
     */
    public function formatLocation($location)
    {
        $trackingStatus = $this->getTrackingStatus($location);

        if ($trackingStatus === 'offline') {
            return null;
        }

        return [
            'latitude'        => $location['latitude'],
            'longitude'       => $location['longitude'],
            'updated_at'      => $location['updated_at'],
            'tracking_status' => $trackingStatus,
        ];
    }

    /**
     * Full tracking payload of a single trip.
     * Relations vehicle / driver / route should be eager loaded by the caller.
     */
    public function getTripTracking(int $schoolId, RouteVehicleHistory $trip): array
    {
        $location = $this->getLocation($schoolId, $trip->id);
        $trackingStatus = $this->getTrackingStatus($location);

        return [
            'trip_id'         => $trip->id,
            'trip_type'       => $trip->type,
            'trip_status'     => $trip->status,
            'date'            => $trip->date,
            'started_at'      => $trip->actual_start_time,
            'bus_id'          => $trip->vehicle_id,
            'bus_name'        => optional($trip->vehicle)->name,
            'bus_number'      => optional($trip->vehicle)->vehicle_number,
            'driver_id'       => $trip->driver_id,
            'driver_name'     => optional($trip->driver)->full_name,
            'route_id'        => $trip->route_id,
            'route_name'      => optional($trip->route)->name,
            'tracking_status' => $trackingStatus,
            'location'        => $trackingStatus === 'offline' ? null : $this->formatLocation($location),
        ];
    }

    /**
     * Tracking payload of every given trip.
     *
     * @param \Illuminate\Support\Collection|array $trips
     */
    public function getActiveLocations(int $schoolId, $trips): array
    {
        $data = [];
        foreach ($trips as $trip) {
            $data[] = $this->getTripTracking($schoolId, $trip);
        }

        return $data;
    }
}
