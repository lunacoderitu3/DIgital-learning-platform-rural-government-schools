<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Student ID Card</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <style>
        @page {
            margin: 0px;
        }

        * {
            font-family: 'DejaVu Sans', sans-serif;
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0px !important;
            padding: 0px !important;
            width: 100%;
            height: 100%;
        }

        .page-break {
            page-break-after: always;
            width: 100%;
            height: 100%;
            position: relative;
            overflow: hidden;
            color: {{ $settings['text_color'] ?? '#333333' }};
            border-radius: {{ $settings['border_radius'] ?? 0 }}px;
        }

        .page-break:last-child {
            page-break-after: auto;
        }

        .id-card-bg-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            border-radius: inherit;
            background-size: cover;
            background-position: center;
            opacity: {{ ($settings['bg_opacity'] ?? 100) / 100 }};
        }

        .id-card-bg-overlay img {
            display: none;
        }

        .id-card-content {
            position: relative;
            z-index: 1;
            width: 100%;
        }

        .id-card-header {
            background-color: {{ $settings['header_color'] ?? '#E26565' }};
            color: {{ $settings['header_footer_text_color'] ?? '#ffffff' }};
            padding: 6px;
        }

        .id-card-header table {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }

        .id-card-header .school-logo {
            width: 40px;
            height: 40px;
            display: inline-block;
        }

        .id-card-header img.logo {
            /* max-width: 100%; */
            max-height: 100%;
            object-fit: contain;
        }

        .id-card-header .school-name {
            font-size: 14px;
            font-size: clamp(10px, 1.2vw, 18px);
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
            word-break: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            text-align: right;
            line-height: 1.2;
        }

        .id-card-title {
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            margin: 15px 0;
            text-transform: uppercase;
        }

        .id-card-profile {
            text-align: center;
        }

        .id-card-profile img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border: 3px solid #fff;
        }

        .id-card-profile.round img {
            border-radius: 50%;
        }

        .id-card-profile.square img {
            border-radius: 8px;
        }

        .id-card-fields {
            font-size: 11px;
        }

        .id-card-fields table {
            width: 100%;
            border-collapse: collapse;
        }

        .id-card-fields td {
            padding: 2.5px 0;
            vertical-align: top;
        }

        .id-card-field-label {
            font-weight: bold;
            width: 40%;
        }

        .id-card-field-value {
            width: 60%;
        }

        .id-card-signature {
            position: absolute;
            bottom: 40px;
            right: 20px;
            text-align: right;
            font-size: 10px;
        }

        .id-card-signature img {
            max-height: 30px;
        }

        .id-card-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: {{ $settings['footer_color'] ?? '#90B1E0' }};
            color: {{ $settings['header_footer_text_color'] ?? '#ffffff' }};
            padding: 10px;
            text-align: center;
            font-size: 10px;
        }

        /* Layout Specifics */
        .layout-vertical .id-card-profile {
            margin-bottom: 15px;
        }

        .layout-vertical .id-card-fields {
            padding: 0 20px;
        }

        .layout-horizontal .id-card-body-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .layout-horizontal .profile-td {
            width: 35%;
            text-align: center;
            vertical-align: middle;
            padding: 0 10px;
        }

        .layout-horizontal .fields-td {
            width: 65%;
            vertical-align: middle;
            padding: 0 10px;
        }
    </style>
</head>

<body>
    @foreach ($students as $key => $student)
        <div class="page-break">
            @if (!empty($settings['background_image']))
                <div class="id-card-bg-overlay"
                    style="background-image: url('{{ public_path('storage/' . $settings['background_image']) }}');">
                </div>
            @endif

            <div class="id-card-content">
                <div class="id-card-header">
                    <table style="width: 100%; border: none; background: transparent;">
                        <tr>
                            <td style="width: 40px; text-align: left; vertical-align: middle; padding: 0;">
                                <div class="school-logo">
                                    <img class="logo"
                                        src="{{ !empty($settings['vertical_logo']) ? public_path('storage/' . $settings['vertical_logo']) : asset('assets/no_image_available.jpg') }}"
                                        alt="Logo">
                                </div>
                            </td>
                            <td style="text-align: right; vertical-align: middle; padding: 0; padding-left: 10px;">
                                <div class="school-name text-right">{{ $settings['school_name'] ?? '' }}</div>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="id-card-title">{{ __('student_id_card') }}</div>

                @if (isset($settings['layout_type']) && $settings['layout_type'] == 'horizontal')
                    <div class="layout-horizontal">
                        <table class="id-card-body-table">
                            <tr>
                                <td class="profile-td">

                                    <div
                                        class="id-card-profile {{ isset($settings['profile_image_style']) && $settings['profile_image_style'] == 'square' ? 'square' : 'round' }}">
                                        @if ($student->getRawOriginal('image') != null)
                                            <img src="{{ public_path('storage/' . $student->getRawOriginal('image')) }}"
                                                alt="Profile">
                                        @else
                                            <img src="{{ public_path('assets/dummy_logo.jpg') }}" alt="Profile">
                                        @endif
                                    </div>
                                </td>
                                <td class="fields-td">
                                    <div class="id-card-fields">
                                        <table>
                                            @if (in_array('student_name', $settings['student_id_card_fields'] ?? []))
                                                <tr>
                                                    <td class="id-card-field-label">{{ __('student_name') }}:</td>
                                                    <td class="id-card-field-value">{{ $student->full_name }}</td>
                                                </tr>
                                            @endif
                                            @if (in_array('class_section', $settings['student_id_card_fields'] ?? []))
                                                <tr>
                                                    <td class="id-card-field-label">{{ __('class_section') }}:</td>
                                                    <td class="id-card-field-value">
                                                        {{ $student->student->class_section->full_name ?? '' }}</td>
                                                </tr>
                                            @endif
                                            @if (in_array('roll_no', $settings['student_id_card_fields'] ?? []))
                                                <tr>
                                                    <td class="id-card-field-label">{{ __('roll_no') }}:</td>
                                                    <td class="id-card-field-value">
                                                        {{ $student->student->roll_number ?? '' }}</td>
                                                </tr>
                                            @endif
                                            @if (in_array('dob', $settings['student_id_card_fields'] ?? []))
                                                <tr>
                                                    <td class="id-card-field-label">{{ __('dob') }}:</td>
                                                    <td class="id-card-field-value">{{ $student->dob }}</td>
                                                </tr>
                                            @endif
                                            @if (in_array('gender', $settings['student_id_card_fields'] ?? []))
                                                <tr>
                                                    <td class="id-card-field-label">{{ __('gender') }}:</td>
                                                    <td class="id-card-field-value" style="text-transform: capitalize">
                                                        {{ $student->gender }}</td>
                                                </tr>
                                            @endif
                                            @if (in_array('session_year', $settings['student_id_card_fields'] ?? []))
                                                <tr>
                                                    <td class="id-card-field-label">{{ __('session_year') }}:</td>
                                                    <td class="id-card-field-value">{{ $sessionYear->name ?? '' }}</td>
                                                </tr>
                                            @endif
                                            @if (in_array('guardian_name', $settings['student_id_card_fields'] ?? []))
                                                <tr>
                                                    <td class="id-card-field-label">{{ __('guardian_name') }}:</td>
                                                    <td class="id-card-field-value">
                                                        {{ $student->student->guardian->full_name ?? '' }}</td>
                                                </tr>
                                                @if (in_array('guardian_contact', $settings['student_id_card_fields'] ?? []))
                                                    <tr>
                                                        <td class="id-card-field-label">{{ __('guardian_contact') }}:
                                                        </td>
                                                        <td class="id-card-field-value">
                                                            {{ $student->student->guardian->mobile ?? '' }}</td>
                                                    </tr>
                                                @endif
                                            @endif

                                            @php $processedFields = []; @endphp
                                            @foreach ($student->extra_student_details ?? [] as $data)
                                                @php
                                                    if (!$data->form_field) {
                                                        continue;
                                                    }
                                                    $fieldId = $data->form_field->id;
                                                    if (
                                                        !in_array(
                                                            $fieldId,
                                                            $settings['extra_student_id_card_fields'] ?? [],
                                                        )
                                                    ) {
                                                        continue;
                                                    }
                                                    $fieldName = $data->form_field->name;
                                                    if (isset($processedFields[$fieldName])) {
                                                        continue;
                                                    }
                                                    $processedFields[$fieldName] = true;
                                                @endphp
                                                <tr>
                                                    <td class="id-card-field-label">{{ $data->form_field->name }}:</td>
                                                    <td class="id-card-field-value">
                                                        @if (in_array($data->form_field->type, ['text', 'number', 'radio', 'textarea']))
                                                            {{ $data->data }}
                                                        @elseif($data->form_field->type == 'dropdown')
                                                            {!! isset($data->form_field->default_values[$data->data])
                                                                ? $data->form_field->default_values[$data->data]
                                                                : $data->data !!}
                                                        @elseif($data->form_field->type == 'checkbox')
                                                            {!! implode(',', json_decode($data->data ?? '[]')) !!}
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                @else
                    <div class="layout-vertical">
                        <div
                            class="id-card-profile {{ isset($settings['profile_image_style']) && $settings['profile_image_style'] == 'square' ? 'square' : 'round' }}">
                            @if ($student->getRawOriginal('image'))
                                <img src="{{ public_path('storage/' . $student->getRawOriginal('image')) }}"
                                    alt="Profile">
                            @else
                                <img src="{{ public_path('assets/dummy_logo.jpg') }}" alt="Profile">
                            @endif
                        </div>

                        <div class="id-card-fields">
                            <table>
                                @if (in_array('student_name', $settings['student_id_card_fields'] ?? []))
                                    <tr>
                                        <td class="id-card-field-label">{{ __('student_name') }}:</td>
                                        <td class="id-card-field-value">{{ $student->full_name }}</td>
                                    </tr>
                                @endif
                                @if (in_array('class_section', $settings['student_id_card_fields'] ?? []))
                                    <tr>
                                        <td class="id-card-field-label">{{ __('class_section') }}:</td>
                                        <td class="id-card-field-value">
                                            {{ $student->student->class_section->full_name ?? '' }}</td>
                                    </tr>
                                @endif
                                @if (in_array('roll_no', $settings['student_id_card_fields'] ?? []))
                                    <tr>
                                        <td class="id-card-field-label">{{ __('roll_no') }}:</td>
                                        <td class="id-card-field-value">{{ $student->student->roll_number ?? '' }}</td>
                                    </tr>
                                @endif
                                @if (in_array('dob', $settings['student_id_card_fields'] ?? []))
                                    <tr>
                                        <td class="id-card-field-label">{{ __('dob') }}:</td>
                                        <td class="id-card-field-value">{{ $student->dob }}</td>
                                    </tr>
                                @endif
                                @if (in_array('gender', $settings['student_id_card_fields'] ?? []))
                                    <tr>
                                        <td class="id-card-field-label">{{ __('gender') }}:</td>
                                        <td class="id-card-field-value" style="text-transform: capitalize">
                                            {{ $student->gender }}</td>
                                    </tr>
                                @endif
                                @if (in_array('session_year', $settings['student_id_card_fields'] ?? []))
                                    <tr>
                                        <td class="id-card-field-label">{{ __('session_year') }}:</td>
                                        <td class="id-card-field-value">{{ $sessionYear->name ?? '' }}</td>
                                    </tr>
                                @endif
                                @if (in_array('guardian_name', $settings['student_id_card_fields'] ?? []))
                                    <tr>
                                        <td class="id-card-field-label">{{ __('guardian_name') }}:</td>
                                        <td class="id-card-field-value">
                                            {{ $student->student->guardian->full_name ?? '' }}</td>
                                    </tr>
                                @endif
                                @if (in_array('guardian_contact', $settings['student_id_card_fields'] ?? []))
                                    <tr>
                                        <td class="id-card-field-label">{{ __('guardian_contact') }}:</td>
                                        <td class="id-card-field-value">{{ $student->student->guardian->mobile ?? '' }}
                                        </td>
                                    </tr>
                                @endif

                                @php $processedFields = []; @endphp
                                @foreach ($student->extra_student_details ?? [] as $data)
                                    @php
                                        if (!$data->form_field) {
                                            continue;
                                        }
                                        $fieldId = $data->form_field->id;
                                        if (!in_array($fieldId, $settings['extra_student_id_card_fields'] ?? [])) {
                                            continue;
                                        }
                                        $fieldName = $data->form_field->name;
                                        if (isset($processedFields[$fieldName])) {
                                            continue;
                                        }
                                        $processedFields[$fieldName] = true;
                                    @endphp
                                    <tr>
                                        <td class="id-card-field-label">{{ $data->form_field->name }}:</td>
                                        <td class="id-card-field-value">
                                            @if (in_array($data->form_field->type, ['text', 'number', 'radio', 'textarea']))
                                                {{ $data->data }}
                                            @elseif($data->form_field->type == 'dropdown')
                                                {!! isset($data->form_field->default_values[$data->data])
                                                    ? $data->form_field->default_values[$data->data]
                                                    : $data->data !!}
                                            @elseif($data->form_field->type == 'checkbox')
                                                {!! implode(',', json_decode($data->data ?? '[]')) !!}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                @endif

            </div> {{-- Close .id-card-content --}}

            @if (!empty($settings['signature']))
                <div class="id-card-signature">
                    <img src="{{ public_path('storage/' . $settings['signature']) }}" alt="Signature">
                    <div style="margin-top: 2px;">Principal Signature</div>
                </div>
            @endif

            <div class="id-card-footer">
                {{ __('valid_until') }}: {{ $valid_until ?? '' }}
            </div>
        </div> {{-- Close .page-break --}}
    @endforeach
</body>

</html>
