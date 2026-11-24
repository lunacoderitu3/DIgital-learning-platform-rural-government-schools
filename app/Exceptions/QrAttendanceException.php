<?php

namespace App\Exceptions;

use Exception;

/**
 * A punch that was refused for a business reason the staff member can act on.
 *
 * Carries a translation key so the caller renders a user-friendly message
 * instead of a technical exception, and a short code the app can branch on.
 */
class QrAttendanceException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $translationKey,
        public readonly array $replacements = []
    ) {
        parent::__construct(trans($translationKey, $replacements));
    }

    public static function disabled(): self
    {
        return new self('qr_disabled', 'qr_attendance_disabled');
    }

    public static function notConfigured(array $missing = []): self
    {
        return new self('not_configured', 'qr_attendance_configuration_incomplete', [
            'items' => implode(', ', $missing),
        ]);
    }

    public static function invalidQr(): self
    {
        return new self('qr_invalid', 'qr_code_invalid');
    }

    public static function expiredQr(): self
    {
        return new self('qr_expired', 'qr_code_expired');
    }

    public static function inactiveQr(): self
    {
        return new self('qr_inactive', 'qr_code_inactive');
    }

    public static function shiftMissing(): self
    {
        return new self('shift_missing', 'staff_shift_not_configured');
    }

    public static function outsideRadius(int $distance, int $radius): self
    {
        return new self('outside_radius', 'outside_allowed_attendance_location', [
            'distance' => $distance,
            'radius'   => $radius,
        ]);
    }

    public static function locationUnavailable(): self
    {
        return new self('location_unavailable', 'unable_to_determine_your_location');
    }

    public static function dayClosed(): self
    {
        return new self('day_closed', 'attendance_for_today_is_closed');
    }

    public static function cooldown(int $secondsLeft): self
    {
        return new self('cooldown', 'please_wait_before_scanning_again', ['seconds' => $secondsLeft]);
    }

    public static function staffNotEligible(): self
    {
        return new self('staff_not_eligible', 'staff_not_eligible_for_qr_attendance');
    }
}
