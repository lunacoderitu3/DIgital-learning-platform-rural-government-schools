@extends('layouts.master')

@section('title')
    {{ __('general_settings') }}
@endsection

@section('css')
    <link href="{{ asset('assets/css/custom/general-settings.css') }}" rel="stylesheet">
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('general_settings') }}
            </h3>
        </div>

        <div class="row grid-margin">
            <div class="col-lg-12">
                <form class="create-form-without-reset" action="{{ route('school-settings.store') }}" method="POST"
                    novalidate="novalidate" enctype="multipart/form-data" data-success-function="formSuccessFunction">
                    @csrf

                    <div class="">
                        <!-- Page Header -->
                        <div class="d-flex flex-column flex-md-row justify-content-end align-items-md-center mb-4 gap-3">
                            <div class="d-flex gap-2">
                                <button type="reset"
                                    class="btn btn-light border shadow-sm btn-sm px-3 d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-rotate-left"></i> {{ __('reset_changes') ?? 'Reset Changes' }}
                                </button>
                                <button type="submit"
                                    class="btn btn-theme shadow-sm btn-sm px-3 d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-floppy-disk"></i> {{ __('save_settings') ?? 'Save Settings' }}
                                </button>
                            </div>
                        </div>

                        <div class="row gx-5">
                            <!-- Top Navigation -->
                            <div class="col-md-12">
                                {{-- nav-pills --}}
                                <div class="nav nav-pills-custom flex-row flex-wrap" id="v-pills-tab" role="tablist"
                                    aria-orientation="horizontal">
                                    <button class="nav-link active" id="v-pills-basic-tab" data-toggle="pill"
                                        data-target="#v-pills-basic" type="button" role="tab"
                                        aria-controls="v-pills-basic" aria-selected="true">
                                        <i class="fa-solid fa-building"></i> {{ __('basic_info') ?? 'Basic Info' }}
                                    </button>
                                    <button class="nav-link" id="v-pills-regional-tab" data-toggle="pill"
                                        data-target="#v-pills-regional" type="button" role="tab"
                                        aria-controls="v-pills-regional" aria-selected="false">
                                        <i class="fa-solid fa-globe"></i> {{ __('regional') ?? 'Regional' }}
                                    </button>
                                    <button class="nav-link" id="v-pills-branding-tab" data-toggle="pill"
                                        data-target="#v-pills-branding" type="button" role="tab"
                                        aria-controls="v-pills-branding" aria-selected="false">
                                        <i class="fa-solid fa-image"></i> {{ __('branding') ?? 'Branding' }}
                                    </button>
                                    <button class="nav-link" id="v-pills-advanced-tab" data-toggle="pill"
                                        data-target="#v-pills-advanced" type="button" role="tab"
                                        aria-controls="v-pills-advanced" aria-selected="false">
                                        <i class="fa-solid fa-sliders"></i>
                                        {{ __('advanced_settings') ?? 'Advanced Settings' }}
                                    </button>
                                    @if (isset($extraFields) && count($extraFields) > 0)
                                        <button class="nav-link" id="v-pills-additional-tab" data-toggle="pill"
                                            data-target="#v-pills-additional" type="button" role="tab"
                                            aria-controls="v-pills-additional" aria-selected="false">
                                            <i class="fa-solid fa-list"></i>
                                            {{ __('additional_info') ?? 'Additional Info' }}
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <!-- Main Content Area -->
                            <div class="col-md-12">
                                <div class="tab-content" id="v-pills-tabContent">

                                    <div class="tab-pane fade show active" id="v-pills-basic" role="tabpanel"
                                        aria-labelledby="v-pills-basic-tab">
                                        <div class="section-card">
                                            <div class="section-header">
                                                <h2 class="section-title"><i class="fa-solid fa-building-columns"></i>
                                                    {{ __('basic_details') ?? 'Basic Details' }}
                                                </h2>
                                                <p class="section-description">
                                                    {{ __('core_identification_information') ?? 'Core identification information for the institution.' }}
                                                </p>
                                            </div>
                                            <div class="row g-3">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label" for="school_name">{{ __('school_name') }}
                                                        <span class="required-asterisk">*</span></label>
                                                    <input name="school_name" id="school_name"
                                                        value="{{ $settings['school_name'] ?? '' }}" type="text"
                                                        maxlength="73" required placeholder="{{ __('school_name') }}"
                                                        class="form-control" />
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label"
                                                        for="school_code">{{ __('school_code') }}</label>
                                                    <input name="school_code" id="school_code"
                                                        value="{{ Auth::user()->school->code ?? '' }}" type="text"
                                                        disabled placeholder="{{ __('school_code') }}"
                                                        class="form-control bg-light" />
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label"
                                                        for="school_board">{{ __('school_board') }}</label>
                                                    <select name="school_board_id" id="school_board"
                                                        class="form-control custom-search-select form-select">
                                                        <option value="">{{ __('select_school_board') }}</option>
                                                        @foreach ($schoolBoard as $board)
                                                            <option value="{{ $board->id }}"
                                                                {{ ($board->school_board->sch_board_id ?? null) == $board->id ? 'selected' : '' }}>
                                                                {{ $board->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label"
                                                        for="school_tagline">{{ __('school_tagline') }} <span
                                                            class="required-asterisk">*</span></label>
                                                    <input name="school_tagline" id="school_tagline"
                                                        value="{{ $settings['school_tagline'] ?? '' }}" type="text"
                                                        required placeholder="{{ __('school_tagline') }}"
                                                        class="form-control" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="section-card">
                                            <div class="section-header">
                                                <h2 class="section-title"><i class="fa-solid fa-map-location-dot"></i>
                                                    {{ __('contact_location') ?? 'Contact & Location' }}
                                                </h2>
                                                <p class="section-description">
                                                    {{ __('primary_contact_details') ?? 'Primary contact details and physical address.' }}
                                                </p>
                                            </div>
                                            <div class="row g-3">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label" for="school_email">{{ __('school_email') }}
                                                        <span class="required-asterisk">*</span></label>
                                                    <input name="school_email" id="school_email"
                                                        value="{{ $settings['school_email'] ?? '' }}" type="email"
                                                        required placeholder="{{ __('school_email') }}"
                                                        class="form-control" />
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label" for="school_phone">{{ __('school_phone') }}
                                                        <span class="required-asterisk">*</span></label>
                                                    <input name="school_phone" id="school_phone"
                                                        value="{{ $settings['school_phone'] ?? '' }}" type="number"
                                                        required placeholder="{{ __('school_phone') }}"
                                                        class="form-control remove-number-increment" />
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label"
                                                        for="school_address">{{ __('school_address') }} <span
                                                            class="required-asterisk">*</span></label>
                                                    <textarea name="school_address" id="school_address" required placeholder="{{ __('school_address') }}"
                                                        class="form-control" rows="3">{{ $settings['school_address'] ?? '' }}</textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="v-pills-regional" role="tabpanel"
                                        aria-labelledby="v-pills-regional-tab">
                                        <div class="section-card">
                                            <div class="section-header">
                                                <h2 class="section-title"><i class="fa-solid fa-earth-americas"></i>
                                                    {{ __('regional_settings') ?? 'Regional Settings' }}
                                                </h2>
                                                <p class="section-description">
                                                    {{ __('configure_dates_times') ?? 'Configure dates, times, and regional preferences.' }}
                                                </p>
                                            </div>
                                            <div class="row g-3">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label"
                                                        for="time_zone">{{ __('time_zone') }}</label>
                                                    <select name="time_zone" id="time_zone"
                                                        class="form-control custom-search-select form-select"
                                                        style="width:100%">
                                                        @foreach ($getTimezoneList as $timezone)
                                                            <option value="{{ $timezone[2] }}"
                                                                {{ isset($settings['time_zone']) && $settings['time_zone'] == $timezone[2] ? 'selected' : '' }}>
                                                                {{ $timezone[2] . ' - GMT ' . $timezone[1] . ' - ' . $timezone[0] }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <div class="form-text">{{ __('school_timezone_help') }}</div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label"
                                                        for="default_country_code">{{ __('default_country_code') }}</label>
                                                    <select name="default_country_code" id="default_country_code"
                                                        class="form-control custom-search-select form-select">
                                                        @foreach (\App\Helpers\CountryCodeHelper::getCountryCodes() as $code => $country)
                                                            <option value="{{ $code }}"
                                                                {{ isset($settings['default_country_code']) && $settings['default_country_code'] == $code ? 'selected' : '' }}>
                                                                {{ $country }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label"
                                                        for="date_format">{{ __('date_format') }}</label>
                                                    <select name="date_format" id="date_format" required
                                                        class="form-control custom-search-select form-select">
                                                        @foreach ($getDateFormat as $key => $dateformat)
                                                            <option value="{{ $key }}"
                                                                {{ isset($settings['date_format']) && $settings['date_format'] == $key ? 'selected' : '' }}>
                                                                {{ $dateformat }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label"
                                                        for="time_format">{{ __('time_format') }}</label>
                                                    <select name="time_format" id="time_format" required
                                                        class="form-control custom-search-select form-select">
                                                        @foreach ($getTimeFormat as $key => $timeFormat)
                                                            <option value="{{ $key }}"
                                                                {{ isset($settings['time_format']) && $settings['time_format'] == $key ? 'selected' : '' }}>
                                                                {{ $timeFormat }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="v-pills-branding" role="tabpanel"
                                        aria-labelledby="v-pills-branding-tab">
                                        <div class="section-card">
                                            <div class="section-header">
                                                <h2 class="section-title"><i class="fa-solid fa-palette"></i>
                                                    {{ __('visual_identity') ?? 'Visual Identity' }}
                                                </h2>
                                                <p class="section-description">
                                                    {{ __('logos_and_images') ?? 'Logos and images used across the application.' }}
                                                </p>
                                            </div>

                                            <div class="row g-4 mb-4">
                                                <div class="col-md-6 col-lg-3 mb-3">
                                                    <label class="form-label">{{ __('favicon') }} <span
                                                            class="required-asterisk">*</span></label>
                                                    <input type="file" name="favicon"
                                                        class="d-none image-upload-input" accept="image/*" />
                                                    <div class="image-upload-placeholder">
                                                        <img src="{{ !empty($settings['favicon']) ? url($settings['favicon']) : asset('assets/no_image_available.jpg') }}"
                                                            alt="Favicon">
                                                        <div class="image-upload-overlay">
                                                            <button type="button"
                                                                class="btn btn-light btn-sm">{{ __('change') ?? 'Change' }}</button>
                                                        </div>
                                                    </div>
                                                    <div class="form-text mt-1 text-center">(32x32 pixels)</div>
                                                </div>
                                                <div class="col-md-6 col-lg-3 mb-3">
                                                    <label class="form-label">{{ __('horizontal_logo') }} <span
                                                            class="required-asterisk">*</span></label>
                                                    <input type="file" name="horizontal_logo"
                                                        class="d-none image-upload-input" accept="image/*" />
                                                    <div class="image-upload-placeholder">
                                                        <img src="{{ !empty($settings['horizontal_logo']) ? url($settings['horizontal_logo']) : asset('assets/no_image_available.jpg') }}"
                                                            alt="Horizontal Logo">
                                                        <div class="image-upload-overlay">
                                                            <button type="button"
                                                                class="btn btn-light btn-sm">{{ __('change') ?? 'Change' }}</button>
                                                        </div>
                                                    </div>
                                                    <div class="form-text mt-1 text-center">(250x50 pixels)</div>
                                                </div>
                                                <div class="col-md-6 col-lg-3 mb-3">
                                                    <label class="form-label">{{ __('vertical_logo') }} <span
                                                            class="required-asterisk">*</span></label>
                                                    <input type="file" name="vertical_logo"
                                                        class="d-none image-upload-input" accept="image/*" />
                                                    <div class="image-upload-placeholder">
                                                        <img src="{{ !empty($settings['vertical_logo']) ? url($settings['vertical_logo']) : asset('assets/no_image_available.jpg') }}"
                                                            alt="Vertical Logo">
                                                        <div class="image-upload-overlay">
                                                            <button type="button"
                                                                class="btn btn-light btn-sm">{{ __('change') ?? 'Change' }}</button>
                                                        </div>
                                                    </div>
                                                    <div class="form-text mt-1 text-center">(100x100 pixels)</div>
                                                </div>
                                                <div class="col-md-6 col-lg-3 mb-3">
                                                    <label class="form-label">{{ __('signature') }}</label>
                                                    <input type="file" name="signature"
                                                        class="d-none image-upload-input"
                                                        accept="image/jpg,image/png,image/jpeg,image/svg" />
                                                    <div class="image-upload-placeholder">
                                                        <img src="{{ !empty($settings['signature']) ? url($settings['signature']) : asset('assets/no_image_available.jpg') }}"
                                                            alt="Signature">
                                                        <div class="image-upload-overlay">
                                                            <button type="button"
                                                                class="btn btn-light btn-sm">{{ __('change') ?? 'Change' }}</button>
                                                        </div>
                                                    </div>
                                                    <div class="form-text mt-1 text-center text-truncate"
                                                        title="{{ __('note_this_signature_image_will_be_used_on_id_cards_and_certificates') }}">
                                                        {{ __('note_this_signature_image_will_be_used_on_id_cards_and_certificates') }}
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row g-4">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">{{ __('login_page_image') }}</label>
                                                    <input type="file" name="login_page_logo"
                                                        class="d-none image-upload-input" accept="image/*" />
                                                    <div class="image-upload-placeholder" style="height: 200px;">
                                                        <img src="{{ !empty($settings['login_page_logo']) ? url($settings['login_page_logo']) : asset('assets/no_image_available.jpg') }}"
                                                            alt="Login Page Logo">
                                                        <div class="image-upload-overlay">
                                                            <button type="button"
                                                                class="btn btn-light btn-sm">{{ __('change') ?? 'Change' }}</button>
                                                        </div>
                                                    </div>
                                                    <div class="form-text mt-1 text-center">(920x1080 pixels)</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="v-pills-advanced" role="tabpanel"
                                        aria-labelledby="v-pills-advanced-tab">
                                        <div class="section-card">
                                            <div class="section-header">
                                                <h2 class="section-title"><i class="fa-solid fa-link"></i>
                                                    {{ __('links_integration') ?? 'Links & Integration' }}
                                                </h2>
                                                <p class="section-description">
                                                    {{ __('external_urls_map') ?? 'External URLs and map integrations.' }}
                                                </p>
                                            </div>
                                            <div class="row g-4">
                                                <div class="col-12">
                                                    <label class="form-label"
                                                        for="school_google_map_link">{{ __('google_map_link') }} <span
                                                            class="required-asterisk">*</span></label>
                                                    <input type="text" class="form-control w-100" required
                                                        name="google_map_link" placeholder="{{ __('google_map_link') }}"
                                                        value="{{ $settings['google_map_link'] ?? '' }}">
                                                    <div class="form-text">{{ __('html_code') }} (Provide the embed HTML
                                                        code iframe src URL).</div>
                                                </div>


                                                <div class="col-sm-12 col-md-12">

                                                    <label class="form-label">{{ __('google_maps_api_key') }}</label>

                                                    <div class="input-group">
                                                        <input type="password" class="form-control"
                                                            id="google_maps_api_key" name="google_maps_api_key"
                                                            autocomplete="off" placeholder="AIza..."
                                                            value="{{ base64_decode($settings['google_maps_api_key'] ?? null) }}">
                                                        <button class="btn btn-outline-secondary" type="button"
                                                            id="toggle-maps-key" title="Toggle Visibility">
                                                            <i class="fa fa-eye"></i>
                                                        </button>
                                                    </div>
                                                    <small class="text-muted mt-1 d-block">
                                                        {{ __('maps_javaScript_API_geocoding_API') }}
                                                    </small>


                                                </div>


                                                <div class="col-md-8 mb-3">
                                                    <label class="form-label"
                                                        for="student_web_url">{{ __('student_web_url') }}</label>
                                                    @php
                                                        if (
                                                            isset($systemSettings['student_web_url']) &&
                                                            !empty($systemSettings['student_web_url'])
                                                        ) {
                                                            $studentWebUrl =
                                                                rtrim($systemSettings['student_web_url'], '/') .
                                                                '/student/auth/' .
                                                                (Auth::user()->school->code ?? '');
                                                        } else {
                                                            $studentWebUrl = '';
                                                        }
                                                    @endphp
                                                    <div class="input-group">
                                                        <input name="student_web_url" id="student_web_url"
                                                            value="{{ $studentWebUrl }}" type="text" readonly
                                                            placeholder="{{ __('student_web_url') }}"
                                                            class="form-control bg-light" />
                                                    </div>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label"
                                                        for="fees_remainder_duration">{{ __('fee_reminder_days') }} <span
                                                            class="required-asterisk">*</span></label>
                                                    <input type="number" class="form-control w-100" required
                                                        name="fees_remainder_duration"
                                                        placeholder="{{ __('fees_remainder_duration') }}"
                                                        value="{{ $settings['fees_remainder_duration'] ?? '' }}">
                                                    <div class="form-text">({{ __('before_due_date') }})</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="section-card">
                                            <div class="section-header">
                                                <h2 class="section-title"><i class="fa-solid fa-network-wired"></i>
                                                    {{ __('Domain Settings') }}
                                                </h2>
                                                <p class="section-description">
                                                    {{ __('Manage custom domain mapping.') ?? 'Manage custom domain mapping.' }}
                                                </p>
                                            </div>
                                            <div class="mb-3 d-flex gap-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="domain_type"
                                                        id="defaultDomain" value="default"
                                                        {{ $domain_type == 'default' ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="defaultDomain">
                                                        {{ __('default') }}
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="domain_type"
                                                        id="customDomain" value="custom"
                                                        {{ $domain_type == 'custom' ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="customDomain">
                                                        {{ __('custom') }}
                                                    </label>
                                                </div>
                                            </div>

                                            <div class="mb-4 defaultDomainGroup" style="max-width: 400px; display: none;">
                                                <label class="form-label">{{ __('default_domain') }}</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control domain-pattern"
                                                        name="domain" placeholder="{{ __('domain') }}" disabled
                                                        value="{{ $domain_type == 'default' && ($settings['domain'] ?? null) ? $settings['domain'] ?? null : '' }}">
                                                    <span
                                                        class="input-group-text bg-light text-muted">.{{ $baseUrlWithoutScheme }}</span>
                                                </div>
                                            </div>

                                            <div class="mb-4 customDomainGroup" style="max-width: 400px; display: none;">
                                                <label class="form-label">{{ __('custom_domain') }}</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control domain-pattern"
                                                        name="domain" placeholder="{{ __('domain') }}" disabled
                                                        value="{{ $domain_type == 'custom' && ($settings['domain'] ?? null) ? $settings['domain'] ?? '' : '' }}">
                                                </div>
                                            </div>

                                            @if (!env('DEMO_MODE'))
                                                <div class="mb-4 serverinfo" style="max-width: 400px; display: none;">
                                                    <label class="form-label">{{ __('server_info') }}</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" name="server_ip"
                                                            placeholder="{{ __('domain') }}" disabled
                                                            value="{{ $_SERVER['SERVER_ADDR'] ?? '127.0.0.1' }}">
                                                    </div>
                                                </div>
                                            @endif

                                            <div
                                                class="info-box alert-primary border-primary-subtle bg-primary-subtle text-primary-emphasis mt-3">
                                                <i class="fa-solid fa-circle-info mt-1"></i>
                                                <div>
                                                    {{ __('Note : If You are using Custom Domain then you have add a dns entry with pointing the server ip address.') }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="section-card">
                                            <div class="section-header">
                                                <h2 class="section-title"><i class="fa-solid fa-hashtag"></i>
                                                    {{ __('Roll Number Settings') }}
                                                </h2>
                                                <p class="section-description">
                                                    {{ __('Configure how student roll numbers are assigned.') ?? 'Configure how student roll numbers are assigned.' }}
                                                </p>
                                            </div>
                                            <div class="mb-3" style="max-width: 400px;">
                                                <label class="form-label"
                                                    for="roll-number-order">{{ __('Roll Number Sorting') }}</label>
                                                <input type="hidden" id="roll-number-sort-column"
                                                    name="roll_number_sort_column"
                                                    value="{{ $settings['roll_number_sort_column'] ?? '' }}">
                                                <input type="hidden" id="roll-number-sort-order"
                                                    name="roll_number_sort_order"
                                                    value="{{ $settings['roll_number_sort_order'] ?? '' }}">
                                                <select name="" id="roll-number-order"
                                                    class="form-select form-control" required>
                                                    <option value="" hidden="">-- {{ __('Select') }} --
                                                    </option>
                                                    <option value="first_name,asc"
                                                        {{ ($settings['roll_number_sort_column'] ?? '') == 'first_name' && ($settings['roll_number_sort_order'] ?? '') == 'asc' ? 'selected' : '' }}>
                                                        {{ __('First Name - Ascending') }}
                                                    </option>
                                                    <option value="first_name,desc"
                                                        {{ ($settings['roll_number_sort_column'] ?? '') == 'first_name' && ($settings['roll_number_sort_order'] ?? '') == 'desc' ? 'selected' : '' }}>
                                                        {{ __('First Name - Descending') }}
                                                    </option>
                                                    <option value="last_name,asc"
                                                        {{ ($settings['roll_number_sort_column'] ?? '') == 'last_name' && ($settings['roll_number_sort_order'] ?? '') == 'asc' ? 'selected' : '' }}>
                                                        {{ __('Last Name - Ascending') }}
                                                    </option>
                                                    <option value="last_name,desc"
                                                        {{ ($settings['roll_number_sort_column'] ?? '') == 'last_name' && ($settings['roll_number_sort_order'] ?? '') == 'desc' ? 'selected' : '' }}>
                                                        {{ __('Last Name - Descending') }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="form-check mt-3">
                                                <label class="form-check-label text-muted small">
                                                    <input type="checkbox" class="form-check-input"
                                                        name="change_roll_number" id="change-roll-ckh-settings"
                                                        value="1">
                                                    {{ __('Change Roll Number for All Classes') }}
                                                </label>
                                            </div>
                                        </div>

                                        {{-- Teacher Student Leave Permissions Toggle --}}
                                        <div class="section-card">
                                            <div class="section-header">
                                                <h2 class="section-title"><i class="fa-solid fa-user-check"></i>
                                                    {{ __('Teacher Student Leave Permissions') }}
                                                </h2>
                                                <p class="section-description">
                                                    {{ __('Enable Teacher Role to Access Student Leave Permissions') }}
                                                </p>
                                            </div>
                                            <div class="mb-3">
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input"
                                                        id="teacher-leave-permissions-toggle"
                                                        data-url="{{ route('school-settings.toggle-teacher-leave-permissions') }}"
                                                        {{ ($teacherLeavePermissionsEnabled ?? '') ? 'checked' : '' }}>
                                                    <label class="custom-control-label"
                                                        for="teacher-leave-permissions-toggle">
                                                        {{ __('When enabled, the Class  Teacher  can approve, create, and reject student leave requests.') }}
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div> <!-- close v-pills-advanced -->

                                    @if (isset($extraFields) && count($extraFields) > 0)
                                        <div class="tab-pane fade" id="v-pills-additional" role="tabpanel"
                                            aria-labelledby="v-pills-additional-tab">
                                            <div class="section-card">
                                                <div class="section-header">
                                                    <h2 class="section-title"><i class="fa-solid fa-list"></i>
                                                        {{ __('required_additional_information') }}
                                                    </h2>
                                                    <p class="section-description">
                                                        {{ __('Additional custom fields.') ?? 'Additional custom fields.' }}
                                                    </p>
                                                </div>
                                                <div class="row g-3">
                                                    @foreach ($extraFields as $key => $data)
                                                        @php
                                                            $fieldName = str_replace(' ', '_', $data->name);
                                                            $extraData = $data->extra_school_data;
                                                            $value = $extraData ? $extraData->data : '';
                                                            $id = $extraData ? $extraData->id : '';
                                                        @endphp

                                                        <input type="hidden"
                                                            name="extra_fields[{{ $key }}][id]"
                                                            value="{{ $id }}">
                                                        <input type="hidden"
                                                            name="extra_fields[{{ $key }}][form_field_id]"
                                                            value="{{ $data->id }}">
                                                        <input type="hidden"
                                                            name="extra_fields[{{ $key }}][input_type]"
                                                            value="{{ $data->type }}">

                                                        <div class='form-group col-md-12 col-lg-6 col-xl-4 col-sm-12 mb-3'>
                                                            @if ($data->type != 'radio' && $data->type != 'checkbox')
                                                                <label class="form-label">{{ $data->name }}
                                                                    @if ($data->is_required)
                                                                        <span class="required-asterisk">*</span>
                                                                    @endif
                                                                </label>
                                                            @endif

                                                            @if ($data->type == 'text')
                                                                <input type="text"
                                                                    name="extra_fields[{{ $key }}][data]"
                                                                    value="{{ $value }}" class="form-control"
                                                                    placeholder="{{ $data->name }}"
                                                                    {{ $data->is_required == 1 ? 'required' : '' }}>
                                                            @elseif($data->type == 'number')
                                                                <input type="number"
                                                                    name="extra_fields[{{ $key }}][data]"
                                                                    value="{{ $value }}" class="form-control"
                                                                    min="0" placeholder="{{ $data->name }}"
                                                                    {{ $data->is_required == 1 ? 'required' : '' }}>
                                                            @elseif($data->type == 'dropdown')
                                                                <select name="extra_fields[{{ $key }}][data]"
                                                                    class="form-control form-select"
                                                                    {{ $data->is_required == 1 ? 'required' : '' }}>
                                                                    <option value="">
                                                                        {{ __('select') . ' ' . $data->name }}
                                                                    </option>
                                                                    @foreach ($data->default_values as $option)
                                                                        <option value="{{ $option }}"
                                                                            {{ $value == $option ? 'selected' : '' }}>
                                                                            {{ $option }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            @elseif($data->type == 'radio')
                                                                <label class="form-label d-block">{{ $data->name }}
                                                                    @if ($data->is_required)
                                                                        <span class="required-asterisk">*</span>
                                                                    @endif
                                                                </label>
                                                                <div class="row ml-1">
                                                                    @foreach ($data->default_values as $keyRadio => $option)
                                                                        <div
                                                                            class="col-md-12 col-lg-12 col-xl-6 col-sm-12 form-check">
                                                                            <label class="form-check-label">
                                                                                <input type="radio"
                                                                                    name="extra_fields[{{ $key }}][data]"
                                                                                    value="{{ $option }}"
                                                                                    {{ $value == $option ? 'checked' : '' }}
                                                                                    {{ $data->is_required == 1 ? 'required' : '' }}>
                                                                                {{ $option }}
                                                                            </label>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            @elseif($data->type == 'checkbox')
                                                                <label class="form-label d-block">{{ $data->name }}
                                                                    @if ($data->is_required)
                                                                        <span class="required-asterisk">*</span>
                                                                    @endif
                                                                </label>
                                                                @php $selectedOptions = json_decode($value) ?? []; @endphp
                                                                <div class="row form-check-inline ml-1"
                                                                    style="width: 100%">
                                                                    @foreach ($data->default_values as $chkKey => $option)
                                                                        <div
                                                                            class="col-lg-12 col-xl-6 col-md-12 col-sm-12 form-check">
                                                                            <label class="form-check-label">
                                                                                <input type="checkbox"
                                                                                    name="extra_fields[{{ $key }}][data][]"
                                                                                    value="{{ $option }}"
                                                                                    {{ in_array($option, $selectedOptions) ? 'checked' : '' }}>
                                                                                {{ $option }}
                                                                            </label>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            @elseif($data->type == 'textarea')
                                                                <textarea name="extra_fields[{{ $key }}][data]" class="form-control" rows="3"
                                                                    placeholder="{{ $data->name }}" {{ $data->is_required == 1 ? 'required' : '' }}>{{ $value }}</textarea>
                                                            @elseif($data->type == 'file')
                                                                <div class="input-group col-xs-12">
                                                                    <input type="file"
                                                                        name="extra_fields[{{ $key }}][data]"
                                                                        class="file-upload-default d-none">
                                                                    <input type="text"
                                                                        class="form-control file-upload-info" disabled
                                                                        placeholder="{{ __('upload') . ' ' . $data->name }}">
                                                                    <span class="input-group-append">
                                                                        <button class="file-upload-browse btn btn-theme"
                                                                            type="button"
                                                                            onclick="$(this).closest('.input-group').find('.file-upload-default').trigger('click')">{{ __('upload') }}</button>
                                                                    </span>
                                                                </div>
                                                                @if ($value)
                                                                    <div class="mt-2">
                                                                        <a href="{{ Storage::url($value) }}"
                                                                            target="_blank">{{ __('view_file') }}</a>
                                                                    </div>
                                                                @endif
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('assets/js/custom/general-settings.js') }}"></script>

@endsection
