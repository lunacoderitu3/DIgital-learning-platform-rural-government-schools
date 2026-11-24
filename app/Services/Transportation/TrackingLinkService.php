<?php

namespace App\Services\Transportation;

use App\Models\School;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Short lived, scoped links that let the mobile app open the student web live
 * tracking page inside a WebView without a web login.
 *
 * Nothing is stored in the database. The token is a signed payload
 * (school / student / trip / expiry / purpose) and the Laravel cache only keeps
 * a small "still valid" record per token, so a link can be revoked before its
 * expiry and everything cleans itself up through the cache TTL.
 *
 *   signature invalid  -> 401, the link was tampered with
 *   record missing     -> 410, expired or revoked
 *
 * The token grants nothing except the live tracking session endpoint.
 */
class TrackingLinkService
{
    /** Purpose baked into every token, checked on redeem */
    public const PURPOSE = 'live_tracking';

    /** Maximum life of a link */
    public const TTL_MINUTES = 120;

    /** Extra time kept after the scheduled end of the trip */
    public const AFTER_TRIP_MINUTES = 30;

    /** How often the web page should poll the session endpoint */
    public const POLL_INTERVAL_SEC = 30;

    /** An existing link is handed out again while it still has this much life left */
    public const MIN_REUSE_SECONDS = 600;

    /** Rate limits */
    public const MAX_LINKS_PER_HOUR = 20;
    public const MAX_POLLS_PER_HOUR = 120;

    /* ------------------------------------------------------------------
     | Cache keys
     |------------------------------------------------------------------*/

    /** The "still valid" record of one token */
    private function linkKey(string $tokenId): string
    {
        return "transportation:tracking-link:{$tokenId}";
    }

    /** Last link handed out for a student/trip, so taps do not pile up links */
    private function reuseKey(int $schoolId, int $userId, $tripId): string
    {
        return "transportation:school:{$schoolId}:tracking-link:user:{$userId}:trip:" . ($tripId ?: 'none');
    }

    /** Token ids issued for a trip, used to revoke them when the trip ends */
    private function tripIndexKey(int $schoolId, int $tripId): string
    {
        return "transportation:school:{$schoolId}:tracking-link:trip:{$tripId}";
    }

    /** Token ids issued to a user, used to revoke them on logout */
    private function issuerIndexKey(int $schoolId, int $issuedToUserId): string
    {
        return "transportation:school:{$schoolId}:tracking-link:issuer:{$issuedToUserId}";
    }

    /* ------------------------------------------------------------------
     | Token
     |------------------------------------------------------------------*/

    private function signingKey(): string
    {
        // Separate from the login token key : this key only signs tracking links.
        return hash('sha256', self::PURPOSE . '|' . config('app.key'));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value)
    {
        return base64_decode(strtr($value, '-_', '+/'), true);
    }

    private function sign(string $payload): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $payload, $this->signingKey(), true));
    }

    /**
     * Expiry of a link : a little after the trip is scheduled to end, never
     * more than TTL_MINUTES away.
     */
    public function resolveExpiry($tripEndTime = null): Carbon
    {
        $maximum = now()->addMinutes(self::TTL_MINUTES);

        if (empty($tripEndTime)) {
            return $maximum;
        }

        try {
            $tripEnd = Carbon::parse($tripEndTime)
                ->setDateFrom(now())
                ->addMinutes(self::AFTER_TRIP_MINUTES);
        } catch (\Throwable $th) {
            return $maximum;
        }

        // A trip that already ended, or an unreadable time, still gives the
        // parent a usable window.
        if ($tripEnd->lessThanOrEqualTo(now())) {
            return $maximum;
        }

        return $tripEnd->lessThan($maximum) ? $tripEnd : $maximum;
    }

    /**
     * Hand out a link for a student, reusing the last one while it is still fresh.
     *
     * @return array{token: string, expires_at: Carbon, trip_id: int|null, reused: bool}
     */
    public function issue(int $schoolId, int $userId, int $issuedToUserId, $tripId, Carbon $expiresAt): array
    {
        $tripId = $tripId ? (int)$tripId : null;

        $reused = $this->reusableLink($schoolId, $userId, $tripId);
        if ($reused) {
            return $reused;
        }

        $tokenId = bin2hex(random_bytes(16));

        $payload = $this->base64UrlEncode(json_encode([
            'v'   => 1,
            'pur' => self::PURPOSE,
            'jti' => $tokenId,
            'sid' => $schoolId,
            'uid' => $userId,
            'iss' => $issuedToUserId,
            'tid' => $tripId,
            'exp' => $expiresAt->getTimestamp(),
        ]));

        $token = $payload . '.' . $this->sign($payload);

        // The cache record is what makes the token redeemable. Forgetting it
        // revokes the link immediately, and it disappears on its own at expiry.
        Cache::put($this->linkKey($tokenId), [
            'school_id'         => $schoolId,
            'user_id'           => $userId,
            'issued_to_user_id' => $issuedToUserId,
            'trip_id'           => $tripId,
            'expires_at'        => $expiresAt->toIso8601String(),
            'created_at'        => now()->toIso8601String(),
        ], $expiresAt);

        Cache::put($this->reuseKey($schoolId, $userId, $tripId), [
            'token'      => $token,
            'token_id'   => $tokenId,
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        if ($tripId) {
            $this->pushToIndex($this->tripIndexKey($schoolId, $tripId), $tokenId);
        }
        $this->pushToIndex($this->issuerIndexKey($schoolId, $issuedToUserId), $tokenId);

        return [
            'token'      => $token,
            'expires_at' => $expiresAt,
            'trip_id'    => $tripId,
            'reused'     => false,
        ];
    }

    /**
     * The link handed out last time, when it still has enough life left.
     */
    private function reusableLink(int $schoolId, int $userId, $tripId)
    {
        $previous = Cache::get($this->reuseKey($schoolId, $userId, $tripId));

        if (empty($previous['token']) || empty($previous['token_id'])) {
            return null;
        }

        $record = Cache::get($this->linkKey($previous['token_id']));
        if (empty($record['expires_at'])) {
            return null;
        }

        $expiresAt = Carbon::parse($record['expires_at']);
        if (now()->diffInSeconds($expiresAt, false) < self::MIN_REUSE_SECONDS) {
            return null;
        }

        return [
            'token'      => $previous['token'],
            'expires_at' => $expiresAt,
            'trip_id'    => $record['trip_id'] ?? null,
            'reused'     => true,
        ];
    }

    /**
     * Read a token.
     *
     * @return array{status: string, link: array|null}
     *         status : ok | invalid | expired
     */
    public function resolve($token): array
    {
        $invalid = ['status' => 'invalid', 'link' => null];

        if (!is_string($token) || substr_count($token, '.') !== 1) {
            return $invalid;
        }

        [$payload, $signature] = explode('.', $token);

        if ($payload === '' || $signature === '' || !hash_equals($this->sign($payload), $signature)) {
            return $invalid;
        }

        $claims = json_decode((string)$this->base64UrlDecode($payload), true);

        if (
            !is_array($claims)
            || ($claims['pur'] ?? null) !== self::PURPOSE
            || empty($claims['jti'])
            || empty($claims['sid'])
            || empty($claims['uid'])
            || empty($claims['exp'])
        ) {
            return $invalid;
        }

        if (Carbon::createFromTimestamp($claims['exp'])->isPast()) {
            return ['status' => 'expired', 'link' => null];
        }

        // Missing record : revoked, or the cache entry is gone. Either way the
        // app has to hand out a new link.
        $record = Cache::get($this->linkKey($claims['jti']));
        if (empty($record)) {
            return ['status' => 'expired', 'link' => null];
        }

        return [
            'status' => 'ok',
            'link'   => array_merge($record, [
                'token_id' => $claims['jti'],
                'trip_id'  => $claims['tid'] ?? null,
            ]),
        ];
    }

    /**
     * Note that a link is being used. Kept for debugging a reported link.
     */
    public function touch(array $link): void
    {
        if (empty($link['token_id']) || empty($link['expires_at'])) {
            return;
        }

        $expiresAt = Carbon::parse($link['expires_at']);
        if ($expiresAt->isPast()) {
            return;
        }

        $record = Cache::get($this->linkKey($link['token_id']));
        if (empty($record)) {
            return;
        }

        $record['last_used_at'] = now()->toIso8601String();
        Cache::put($this->linkKey($link['token_id']), $record, $expiresAt);
    }

    /* ------------------------------------------------------------------
     | Revoking
     |------------------------------------------------------------------*/

    public function revokeToken(string $tokenId): void
    {
        Cache::forget($this->linkKey($tokenId));
    }

    /** Called when a trip ends : its links stop working straight away. */
    public function revokeTripLinks(int $schoolId, int $tripId): void
    {
        $this->revokeIndex($this->tripIndexKey($schoolId, $tripId));
    }

    /** Called on logout : links the user handed out stop working. */
    public function revokeUserLinks(int $schoolId, int $issuedToUserId): void
    {
        $this->revokeIndex($this->issuerIndexKey($schoolId, $issuedToUserId));
    }

    private function revokeIndex(string $indexKey): void
    {
        $tokenIds = Cache::get($indexKey);

        if (is_array($tokenIds)) {
            foreach ($tokenIds as $tokenId) {
                $this->revokeToken($tokenId);
            }
        }

        Cache::forget($indexKey);
    }

    private function pushToIndex(string $indexKey, string $tokenId): void
    {
        $tokenIds = Cache::get($indexKey);
        $tokenIds = is_array($tokenIds) ? $tokenIds : [];

        $tokenIds[] = $tokenId;
        $tokenIds = array_slice(array_unique($tokenIds), -50);

        // The index outlives a single link so it can still revoke the newest ones.
        Cache::put($indexKey, $tokenIds, now()->addMinutes(self::TTL_MINUTES + self::AFTER_TRIP_MINUTES));
    }

    /* ------------------------------------------------------------------
     | Helpers
     |------------------------------------------------------------------*/

    /**
     * Full WebView url for a token. The token travels in the fragment so it is
     * never sent to the web server and stays out of access logs.
     */
    public function buildUrl(string $token): string
    {
        $path = config('constants.TRANSPORT.LIVE_TRACKING_PATH') ?: '/live-tracking';

        // An explicit url wins, for a web app that is not served where the
        // student_web_url setting points.
        $base = config('constants.TRANSPORT.LIVE_TRACKING_URL');

        if (empty($base)) {
            // student_web_url is the student login page, so drop the auth part
            // of the path and keep the rest of the deployment.
            $base = app(\App\Services\CachingService::class)->getSystemSettings('student_web_url');
            $base = is_string($base) ? rtrim(trim($base), '/') : '';
            $base = preg_replace('#/(student/auth/login|student/auth|auth/login|auth|login)$#i', '', $base);
        }

        $base = rtrim(trim((string)$base), '/');

        if ($base === '') {
            return '';
        }

        return $base . '/' . ltrim($path, '/') . '#tk=' . $token;
    }

    /**
     * Point the default connection at a school database.
     * Used by the redeem endpoint, which runs outside APISwitchDatabase because
     * the WebView sends no school-code header.
     */
    public function connectSchoolDatabase(School $school): void
    {
        DB::setDefaultConnection('school');
        Config::set('database.connections.school.database', $school->database_name);
        DB::purge('school');
        DB::connection('school')->reconnect();
        DB::setDefaultConnection('school');
    }
}
