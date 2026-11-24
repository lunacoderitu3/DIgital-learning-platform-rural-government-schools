@php
    /**
     * One horizontal 05:00–21:00 track for a staff-day.
     *
     * The shift window is a striped band, completed sessions solid bars, an
     * unmatched punch in a dashed bar with no end. Everything is positioned as
     * a percentage of the visible window, so the row scales with the column.
     *
     * @var string|null $shiftStart   H:i:s
     * @var string|null $shiftEnd     H:i:s
     * @var array       $sessions     [['in' => 'Y-m-d H:i:s', 'out' => ...], ...]
     * @var string|null $openSession  Y-m-d H:i:s
     * @var string|null $now          H:i:s, draws the "now" marker
     */
    $windowStart = 5 * 60; // 05:00
    $windowEnd = 21 * 60; // 21:00
    $windowSpan = $windowEnd - $windowStart;

    $toMinutes = function ($value) {
        if (empty($value)) {
            return null;
        }
        $timestamp = strtotime($value);
        return (int) date('G', $timestamp) * 60 + (int) date('i', $timestamp);
    };

    // Clamp to the visible window so an out-of-range punch never overflows.
    $position = function ($minutes) use ($windowStart, $windowSpan) {
        return max(0, min(100, (($minutes - $windowStart) / $windowSpan) * 100));
    };

    $shiftStartMin = $toMinutes($shiftStart ?? null);
    $shiftEndMin = $toMinutes($shiftEnd ?? null);
@endphp

<div class="qr-ribbon">
    @if ($shiftStartMin !== null && $shiftEndMin !== null && $shiftEndMin > $shiftStartMin)
        @php
            $left = $position($shiftStartMin);
            $right = $position($shiftEndMin);
        @endphp
        <span class="rb-shift" style="left:{{ $left }}%;width:{{ max(0, $right - $left) }}%"
            title="{{ $shiftName ?? '' }} {{ date('H:i', strtotime($shiftStart)) }}–{{ date('H:i', strtotime($shiftEnd)) }}"></span>
    @endif

    @foreach ($sessions ?? [] as $session)
        @php
            $inMin = $toMinutes($session['in']);
            $outMin = $toMinutes($session['out']);
            $left = $position($inMin);
            $right = $position($outMin);
            // In shift-based mode a session wholly outside the window earns nothing.
            $counted =
                $shiftStartMin === null ||
                ($outMin > $shiftStartMin && $inMin < $shiftEndMin) ||
                ($mode ?? '') === 'DAY_BASED';
        @endphp
        <span class="rb-session {{ $counted ? '' : 'is-uncounted' }}"
            style="left:{{ $left }}%;width:{{ max(0.6, $right - $left) }}%"
            title="{{ date('H:i', strtotime($session['in'])) }} → {{ date('H:i', strtotime($session['out'])) }}"></span>
    @endforeach

    @if (!empty($openSession))
        @php
            $inMin = $toMinutes($openSession);
            $left = $position($inMin);
            $endMin = $now ? $toMinutes($now) : $inMin + 20;
            $right = $position(max($endMin, $inMin + 10));
        @endphp
        <span class="rb-open" style="left:{{ $left }}%;width:{{ max(1, $right - $left) }}%"
            title="{{ __('missing_punch_out') }} · {{ date('H:i', strtotime($openSession)) }}"></span>
    @endif

    @if (!empty($now))
        <span class="rb-now" style="left:{{ $position($toMinutes($now)) }}%" title="{{ __('now') }}"></span>
    @endif
</div>
