<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('missing_punch_out_report') }}</title>
</head>

<body style="margin:0;padding:24px;background:#f4f7f9;font-family:Arial,Helvetica,sans-serif;color:#17222b;">
    <div style="max-width:720px;margin:0 auto;background:#ffffff;border:1px solid #dee7ed;border-radius:8px;overflow:hidden;">

        <div style="padding:20px 24px;border-bottom:1px solid #ebf1f5;">
            <h2 style="margin:0 0 4px;font-size:18px;">{{ __('missing_punch_out_report') }}</h2>
            <p style="margin:0;font-size:13px;color:#64798a;">
                {{ $school_name }} &middot; {{ $date }}
            </p>
        </div>

        <div style="padding:20px 24px;">
            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
                {{ __('missing_punch_out_report_intro', ['count' => count($rows)]) }}
            </p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                style="border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#eef4f8;text-align:left;">
                        <th style="padding:8px 10px;border-bottom:1px solid #dee7ed;">{{ __('staff_name') }}</th>
                        <th style="padding:8px 10px;border-bottom:1px solid #dee7ed;">{{ __('staff_type') }}</th>
                        <th style="padding:8px 10px;border-bottom:1px solid #dee7ed;">{{ __('shift') }}</th>
                        <th style="padding:8px 10px;border-bottom:1px solid #dee7ed;">{{ __('punch_in_time') }}</th>
                        <th style="padding:8px 10px;border-bottom:1px solid #dee7ed;">{{ __('working_hours') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td style="padding:8px 10px;border-bottom:1px solid #ebf1f5;">{{ $row['staff_name'] }}</td>
                            <td style="padding:8px 10px;border-bottom:1px solid #ebf1f5;">{{ $row['staff_type'] }}</td>
                            <td style="padding:8px 10px;border-bottom:1px solid #ebf1f5;">{{ $row['shift_name'] ?? '—' }}</td>
                            <td style="padding:8px 10px;border-bottom:1px solid #ebf1f5;">
                                {{ $row['punch_in'] ? date('H:i', strtotime($row['punch_in'])) : '—' }}
                            </td>
                            <td style="padding:8px 10px;border-bottom:1px solid #ebf1f5;">
                                {{ intdiv($row['working_minutes'], 60) }}h {{ str_pad((string) ($row['working_minutes'] % 60), 2, '0', STR_PAD_LEFT) }}m
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p style="margin:18px 0 0;font-size:12.5px;line-height:1.6;color:#64798a;">
                {{ __('missing_punch_out_report_note') }}
            </p>
        </div>
    </div>
</body>

</html>
