@php
    /**
     * Attendance status as a pill.
     *
     * @var int|null $type  staff_attendances.type
     */
    $map = [
        0 => ['qr-pill-absent', __('absent')],
        1 => ['qr-pill-present', __('present')],
        2 => ['qr-pill-halfday', __('half_day')],
        3 => ['qr-pill-holiday', __('holiday')],
        4 => ['qr-pill-halfday', __('first_half_present')],
        5 => ['qr-pill-halfday', __('second_half_present')],
    ];
    [$class, $label] = $map[$type] ?? ['qr-pill-holiday', '—'];
@endphp

<span class="qr-pill {{ $class }}">{{ $label }}</span>
