@extends('layouts.master')

@section('title')
    {{ __('whatsapp') }}
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/custom/whatsapp.css') }}">
@endsection

@section('content')
    <div class="content-wrapper wa-module">

        {{-- ===== 1. Header card ===== --}}
        <div class="wa-header-card">
            <div class="wa-header-left w-100">
                <div class="wa-logo-tile">
                    <i class="fa-brands fa-whatsapp"></i>
                </div>
                <div class="flex-grow-1" style="min-width: 0;">
                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                        <div>
                            <h1 class="wa-header-title">{{ __('whatsapp_integration') }}</h1>
                            <p class="wa-header-subtitle">{{ __('whatsapp_integration_note') }}</p>
                        </div>
                        <button type="button" class="wa-btn wa-btn-outline flex-shrink-0 ml-3 mb-2 mb-sm-0"
                            onclick="window.location.reload()">
                            <i class="fa-solid fa-arrows-rotate"></i> {{ __('refresh') }}
                        </button>
                    </div>

                    <div class="wa-header-chips w-100">
                        @if ($settings->status == 'connected')
                            <span class="wa-chip-status wa-connected">
                                <span class="wa-pulse-dot"></span> {{ __('connected') }}
                            </span>
                        @else
                            <span class="wa-chip-status wa-disconnected">
                                <span class="wa-pulse-dot"></span> {{ __('disconnected') }}
                            </span>
                        @endif
                        @if ($settings->business_account_id)
                            <span class="wa-chip-meta">
                                <i class="fa-solid fa-building"></i> {{ __('business_account_id') }}:
                                {{ $settings->business_account_id }}
                            </span>
                            <span class="wa-chip-sep d-none d-sm-inline-block"></span>
                        @endif
                        <span class="wa-chip-meta">
                            <i class="fa-solid fa-clock-rotate-left"></i> {{ __('last_updated') }}:
                            {{ $settings->updated_at }}
                        </span>
                        <a class="theme-color text-decoration-none ml-auto" style="font-size: 0.85rem; font-weight: 500;"
                            href="https://wrteam-in.github.io/eSchool-SaaS-Doc/schooladmin/system-settings/whatsapp/"
                            target="_blank" rel="noopener noreferrer">
                            <i class="fa fa-book mr-1"></i> {{ __('Check documentation') }} &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== 2. Statistics cards ===== --}}
        <div class="row">
            <div class="col-lg-3 col-sm-6 grid-margin">
                <div class="wa-stat-card">
                    <div class="wa-stat-top">
                        <div>
                            <p class="wa-stat-label">{{ __('messages_sent_today') }}</p>
                            <h3 class="wa-stat-value">{{ $stats['sent_today'] }}</h3>
                        </div>
                        <div class="wa-stat-icon wa-icon-blue"><i class="fa-solid fa-paper-plane"></i></div>
                    </div>
                    <div class="wa-stat-meta">{{ __('messages_sent_today') }}</div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6 grid-margin">
                <div class="wa-stat-card">
                    <div class="wa-stat-top">
                        <div>
                            <p class="wa-stat-label">{{ __('messages_sent_this_month') }}</p>
                            <h3 class="wa-stat-value">{{ $stats['sent_month'] }}</h3>
                        </div>
                        <div class="wa-stat-icon wa-icon-indigo"><i class="fa-solid fa-calendar"></i></div>
                    </div>
                    <div class="wa-stat-meta">{{ __('total_volume') }}</div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6 grid-margin">
                <div class="wa-stat-card wa-stat-danger">
                    <div class="wa-stat-top">
                        <div>
                            <p class="wa-stat-label">{{ __('failed_messages') }}</p>
                            <h3 class="wa-stat-value">{{ $stats['failed'] }}</h3>
                        </div>
                        <div class="wa-stat-icon wa-icon-red"><i class="fa-solid fa-circle-exclamation"></i></div>
                    </div>
                    <div class="wa-stat-meta">
                        <a href="javascript:void(0)" id="wa-view-logs-link">{{ __('view_logs') }} &rarr;</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6 grid-margin">
                <div class="wa-stat-card wa-stat-success">
                    <div class="wa-stat-top">
                        <div>
                            <p class="wa-stat-label">{{ __('meta_messaging_tier') }}</p>
                            <h3 class="wa-stat-value">{{ $metaInsights['tier_label'] ?? '-' }}</h3>
                        </div>
                        <div class="wa-stat-icon wa-icon-emerald"><i class="fa-brands fa-meta"></i></div>
                    </div>
                    <div class="wa-stat-meta">
                        <span class="wa-meta-positive"><i class="fa-solid fa-circle-check"></i>
                            {{ __('set_by_meta') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Meta account status (live from the Meta API) ===== --}}
        <div class="row grid-margin">
            <div class="col-12">
                <div class="wa-main-card">
                    <div class="wa-quota">
                        <div class="wa-quota-head">
                            <h3 class="wa-section-title mb-0"><i class="fa-brands fa-meta mr-1 text-muted"></i>
                                {{ __('meta_account_status') }}</h3>
                            <span class="wa-chip-meta">{{ __('usage_month') }}:
                                {{ $settings->usage_month ?? now()->format('Y-m') }}</span>
                        </div>

                        <div class="wa-quota-grid">
                            <div class="wa-quota-item">
                                <span class="wa-quota-label">{{ __('meta_messaging_tier') }}</span>
                                <span class="wa-quota-value">{{ $metaInsights['tier_label'] ?? '-' }}</span>
                                <span class="wa-quota-sub">{{ __('meta_messaging_tier_hint') }}</span>
                            </div>
                            <div class="wa-quota-item">
                                <span class="wa-quota-label">{{ __('quality_rating') }}</span>
                                @php
                                    $qualityColors = [
                                        'GREEN' => 'wa-pill-green',
                                        'YELLOW' => 'wa-pill-amber',
                                        'RED' => 'wa-pill-red',
                                    ];
                                    $quality = strtoupper((string) ($metaInsights['quality_rating'] ?? ''));
                                @endphp
                                @if ($quality && $quality !== 'UNKNOWN')
                                    <span class="wa-quota-value"><span
                                            class="wa-pill {{ $qualityColors[$quality] ?? 'wa-pill-gray' }}"><span
                                                class="wa-dot"></span> {{ $quality }}</span></span>
                                @else
                                    <span class="wa-quota-value">-</span>
                                @endif
                                <span class="wa-quota-sub">{{ $metaInsights['phone_number'] ?? '' }}</span>
                            </div>
                            <div class="wa-quota-item">
                                <span class="wa-quota-label">{{ __('messages_sent_this_month') }}</span>
                                <span class="wa-quota-value">{{ number_format($metaInsights['used']) }}</span>
                                <span class="wa-quota-sub">{{ __('tracked_by_panel') }}</span>
                            </div>
                            <div class="wa-quota-item">
                                <span class="wa-quota-label">{{ __('meta_cost_this_month') }}</span>
                                @if (!is_null($metaInsights['cost']))
                                    <span class="wa-quota-value">{{ number_format($metaInsights['cost'], 2) }}</span>
                                    <span class="wa-quota-sub">{{ $metaInsights['conversations'] }}
                                        {{ __('conversations') }} — {{ __('billed_by_meta') }}</span>
                                @else
                                    <span class="wa-quota-value">-</span>
                                    <span class="wa-quota-sub">{{ __('meta_cost_unavailable_hint') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== 3. Tabs card ===== --}}
        <div class="row grid-margin">
            <div class="col-12">
                <div class="wa-main-card">

                    <ul class="nav wa-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#wa-setup" role="tab">
                                <i class="fa-solid fa-plug"></i> {{ __('setup') }}
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#wa-bulk" role="tab">
                                <i class="fa-solid fa-bullhorn"></i> {{ __('send_message') }}
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#wa-contacts" role="tab">
                                <i class="fa-solid fa-address-book"></i> {{ __('external_users') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#wa-scheduled" role="tab"
                                id="wa-scheduled-tab">
                                <i class="fa-solid fa-clock"></i> {{ __('scheduled') }}
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#wa-manual-templates" role="tab">
                                <i class="fa-solid fa-pen-to-square"></i> {{ __('manual_templates') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#wa-templates" role="tab">
                                <i class="fa-solid fa-file-code"></i> {{ __('templates') }}
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#wa-logs" role="tab" id="wa-logs-tab">
                                <i class="fa-solid fa-list-ul"></i> {{ __('message_logs') }}
                            </a>
                        </li>

                    </ul>

                    <div class="tab-content">

                        {{-- =================== SETUP =================== --}}
                        <div class="tab-pane fade show active wa-tab-body" id="wa-setup" role="tabpanel">
                            <div class="row">

                                {{-- Connection details --}}
                                <div class="col-sm-12 mb-4 col-md-12 mb-xl-0">
                                    <div class="mb-3">
                                        <h3 class="wa-section-title">{{ __('connection_details') }}</h3>
                                        <p class="wa-section-subtitle">{{ __('whatsapp_setup_note') }}</p>
                                    </div>

                                    <form class="create-form-without-reset wa-panel"
                                        action="{{ route('whatsapp.settings') }}"
                                        data-success-function="whatsappSettingsSaved" novalidate="novalidate">
                                        @csrf

                                        {{-- Enable switch --}}
                                        <div class="wa-enable-row">
                                            <div>
                                                <p class="wa-enable-title">{{ __('enable_whatsapp') }}</p>
                                                <p class="wa-enable-desc">{{ __('enable_whatsapp_note') }}</p>
                                            </div>
                                            <label class="wa-switch" for="wa-enabled">
                                                <input type="checkbox" id="wa-enabled" name="enabled" value="1"
                                                    {{ $settings->enabled ? 'checked' : '' }}>
                                                <span class="wa-slider"></span>
                                            </label>
                                        </div>

                                        <div class="row">
                                            {{-- Phone Number ID --}}
                                            <div class="wa-field col-sm-12 col-md-6">
                                                <label class="wa-label">{{ __('phone_number_id') }} <span
                                                        class="text-danger">*</span></label>
                                                <div class="wa-input-wrap wa-has-actions">
                                                    <i class="fa-solid fa-phone wa-input-icon"></i>
                                                    <input type="text" name="phone_number_id" id="wa-phone-number-id"
                                                        class="wa-input" required
                                                        value="{{ $settings->phone_number_id }}"
                                                        placeholder="e.g. 104567890123456">
                                                    <div class="wa-input-actions">
                                                        <button type="button" class="wa-icon-btn wa-copy"
                                                            data-target="#wa-phone-number-id"
                                                            title="{{ __('copy') }}">
                                                            <i class="fa-regular fa-copy"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Business Account ID --}}
                                            <div class="wa-field col-sm-12 col-md-6">
                                                <label class="wa-label">{{ __('business_account_id') }} <span
                                                        class="text-danger">*</span></label>
                                                <div class="wa-input-wrap wa-has-actions">
                                                    <i class="fa-solid fa-briefcase wa-input-icon"></i>
                                                    <input type="text" name="business_account_id"
                                                        id="wa-business-account-id" class="wa-input" required
                                                        value="{{ $settings->business_account_id }}"
                                                        placeholder="e.g. 104567890123456">
                                                    <div class="wa-input-actions">
                                                        <button type="button" class="wa-icon-btn wa-copy"
                                                            data-target="#wa-business-account-id"
                                                            title="{{ __('copy') }}">
                                                            <i class="fa-regular fa-copy"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>



                                        {{-- Access Token --}}
                                        <div class="wa-field">
                                            <label class="wa-label">{{ __('permanent_access_token') }} @if (empty($settings->getRawOriginal('access_token')))
                                                    <span class="text-danger">*</span>
                                                @endif
                                            </label>
                                            <div class="wa-input-wrap wa-has-actions">
                                                <i class="fa-solid fa-key wa-input-icon"></i>
                                                <input type="password" name="access_token" id="wa-access-token"
                                                    class="wa-input wa-input-mono" autocomplete="new-password"
                                                    @if (empty($settings->getRawOriginal('access_token'))) required @endif
                                                    placeholder="{{ empty($settings->getRawOriginal('access_token')) ? 'EAAL...' : __('leave_blank_to_keep_existing') }}">
                                                <div class="wa-input-actions">
                                                    <button type="button" class="wa-icon-btn" id="wa-toggle-token"
                                                        title="{{ __('show') }}">
                                                        <i class="fa-regular fa-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <p class="wa-hint"><i class="fa-solid fa-circle-info"></i>
                                                {{ __('access_token_warning') }}</p>
                                        </div>

                                        {{-- Default Meta relay template --}}
                                        <div class="wa-field">
                                            <label class="wa-label">{{ __('default_meta_relay_template') }} (Text)</label>
                                            <div class="row">
                                                <div class="col-4">
                                                    <div class="wa-input-wrap">
                                                        <i class="fa-brands fa-meta wa-input-icon"></i>
                                                        <input type="text" name="default_meta_template"
                                                            class="wa-input wa-input-mono"
                                                            value="{{ $settings->default_meta_template }}"
                                                            placeholder="e.g. school_message">
                                                    </div>

                                                </div>
                                                <div class="col-4">
                                                    <input type="text" name="default_meta_language"
                                                        class="wa-input wa-input-mono"
                                                        value="{{ $settings->default_meta_language }}"
                                                        placeholder="en_US">
                                                </div>
                                            </div>
                                            <p class="wa-hint"><i class="fa-solid fa-circle-info"></i>
                                                {{ __('default_meta_relay_template_hint') }}</p>
                                        </div>

                                        {{-- Default Meta Document relay template --}}
                                        <div class="wa-field">
                                            <label class="wa-label">{{ __('default_meta_document_template') }}</label>
                                            <div class="row">
                                                <div class="col-4">
                                                    <div class="wa-input-wrap">
                                                        <i class="fa-brands fa-meta wa-input-icon"></i>
                                                        <input type="text" name="default_meta_media_template"
                                                            class="wa-input wa-input-mono"
                                                            value="{{ $settings->default_meta_media_template }}"
                                                            placeholder="e.g. school_message_document">
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="wa-hint"><i class="fa-solid fa-circle-info"></i>
                                                {{ __('default_meta_document_template_hint') }}</p>
                                        </div>

                                        {{-- Default Meta Image relay template --}}
                                        <div class="wa-field">
                                            <label class="wa-label">{{ __('default_meta_image_template') }}</label>
                                            <div class="row">
                                                <div class="col-4">
                                                    <div class="wa-input-wrap">
                                                        <i class="fa-brands fa-meta wa-input-icon"></i>
                                                        <input type="text" name="default_meta_image_template"
                                                            class="wa-input wa-input-mono"
                                                            value="{{ $settings->default_meta_image_template }}"
                                                            placeholder="e.g. school_message_image">
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="wa-hint"><i class="fa-solid fa-circle-info"></i>
                                                {{ __('default_meta_image_template_hint') }}</p>
                                        </div>

                                        <div class="wa-hint mb-3">
                                            <i class="fa-solid fa-circle-info"></i>
                                            {{ __('meta_media_template_fallback_hint') }}
                                        </div>

                                        {{-- Actions --}}
                                        <div class="wa-form-actions">
                                            <input type="submit" class="wa-btn wa-btn-primary"
                                                value="{{ __('save') }} {{ __('settings') }}">
                                            <button type="button" class="wa-btn wa-btn-outline" id="wa-test-connection">
                                                <i class="fa-solid fa-plug"></i> {{ __('test_connection') }}
                                            </button>

                                            <button type="button" class="wa-btn wa-btn-outline" data-bs-toggle="modal"
                                                data-bs-target="#waTestMessage">
                                                <i class="fa-brands fa-solid fa-whatsapp"></i>
                                                {{ __('send_test_message') }}
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                {{-- Template Test Modal --}}
                                <div class="modal fade" id="waTestMessage" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-md">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">{{ __('send_test_message') }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                    aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="wa-panel">
                                                    <label
                                                        class="wa-label">{{ __('enter_mobile_with_country_code') }}</label>
                                                    <div class="wa-join">
                                                        <span class="wa-join-prepend"><i
                                                                class="fa-solid fa-mobile-screen-button"></i></span>
                                                        <input type="text" class="wa-input" id="wa-test-mobile"
                                                            placeholder="91 98765 43210">
                                                        <button type="button" class="wa-join-append wa-join-dark"
                                                            id="wa-send-test">
                                                            <i class="fa-solid fa-paper-plane"></i> {{ __('send') }}
                                                        </button>
                                                    </div>
                                                    <p class="wa-hint">{{ __('whatsapp_test_note') }}</p>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>


                                {{-- Side column: webhook + test message --}}
                                <div class="col-xl-4 d-none">
                                    <div class="mb-3">
                                        <h3 class="wa-section-title">{{ __('webhook_configuration') }}</h3>
                                        <p class="wa-section-subtitle">{{ __('whatsapp_webhook_note') }}</p>
                                    </div>

                                    <div class="wa-panel-inset mb-4">
                                        <div class="wa-field">
                                            <label class="wa-label-xs">{{ __('webhook_url') }}</label>
                                            <div class="wa-join">
                                                <input type="text" class="wa-input wa-input-mono" id="wa-webhook-url"
                                                    readonly value="{{ $webhookUrl }}">
                                                <button type="button" class="wa-join-append wa-copy"
                                                    data-target="#wa-webhook-url" title="{{ __('copy') }}">
                                                    <i class="fa-regular fa-copy"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mb-0">
                                            <label class="wa-label-xs">{{ __('webhook_verify_token') }}</label>
                                            <div class="wa-join">
                                                <input type="text" class="wa-input wa-input-mono" id="wa-verify-token"
                                                    readonly value="{{ $settings->verify_token }}">
                                                <button type="button" class="wa-join-append wa-copy"
                                                    data-target="#wa-verify-token" title="{{ __('copy') }}">
                                                    <i class="fa-regular fa-copy"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        {{-- =================== BULK SEND =================== --}}

                        <div class="tab-pane fade wa-tab-body" id="wa-bulk" role="tabpanel">
                            <div class="row">
                                {{-- Compose --}}
                                <div class="col-xl-5 mb-4 mb-xl-0">
                                    <div class="mb-3">
                                        <h3 class="wa-section-title">{{ __('send_message') }}</h3>
                                        <p class="wa-section-subtitle">{{ __('bulk_send_note') }}</p>
                                    </div>
                                    <div class="wa-panel">
                                        <div class="wa-field">
                                            <label class="wa-label">{{ __('select_template') }} <span
                                                    class="text-danger">*</span></label>
                                            <select class="wa-input" id="wa-bulk-template">
                                                <option value="">{{ __('select_template') }}</option>
                                                @foreach ($templates as $template)
                                                    <option value="{{ $template['id'] }}">
                                                        {{ $template['name'] }}{{ $template['category'] ? ' (' . $template['category'] . ')' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div id="wa-bulk-variables"></div>

                                        <div class="wa-field d-none" id="wa-bulk-auto-wrap">
                                            <label class="wa-label-xs">{{ __('auto_filled_per_recipient') }}</label>
                                            <div id="wa-bulk-auto-vars"></div>
                                        </div>

                                        <label class="wa-label-xs">{{ __('message_preview') }}</label>
                                        <div class="wa-preview-wrap mb-3">
                                            <div class="wa-bubble"><span
                                                    id="wa-bulk-preview">{{ __('select_template') }}</span>
                                                <span class="wa-bubble-time">10:42 AM <i
                                                        class="fa-solid fa-check-double"></i></span>
                                            </div>
                                        </div>


                                        <div class="wa-field">
                                            <label class="wa-label">{{ __('attachment') }} <span
                                                    class="text-muted small">({{ __('optional') }})</span></label>
                                            <input type="file" class="wa-input" id="wa-bulk-attachment"
                                                accept="image/jpeg,image/png,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv">
                                            <p class="wa-hint"><i class="fa-solid fa-circle-info"></i>
                                                {{ __('attachment_hint') }}</p>
                                        </div>


                                        <div class="wa-field">
                                            <label class="wa-label">{{ __('delivery') }}</label>
                                            <div class="wa-schedule-options">
                                                <label class="wa-radio"><input type="radio" name="wa_schedule_type"
                                                        value="now" checked> {{ __('immediately') }}</label>
                                                <label class="wa-radio"><input type="radio" name="wa_schedule_type"
                                                        value="once"> {{ __('date_and_time') }}</label>
                                                <label class="wa-radio"><input type="radio" name="wa_schedule_type"
                                                        value="weekly"> {{ __('repeat_weekly') }}</label>
                                                <label class="wa-radio"><input type="radio" name="wa_schedule_type"
                                                        value="monthly"> {{ __('repeat_monthly') }}</label>
                                            </div>
                                            <div class="mt-2 d-none" id="wa-schedule-datetime-wrap">
                                                <input type="datetime-local" class="wa-input" id="wa-schedule-datetime">
                                                <p class="wa-hint d-none" id="wa-schedule-hint-weekly"><i
                                                        class="fa-solid fa-circle-info"></i>
                                                    {{ __('repeat_weekly_hint') }}</p>
                                                <p class="wa-hint d-none" id="wa-schedule-hint-monthly"><i
                                                        class="fa-solid fa-circle-info"></i>
                                                    {{ __('repeat_monthly_hint') }}</p>
                                            </div>
                                        </div>

                                        <div class="wa-form-actions">
                                            <button type="button" class="wa-btn wa-btn-primary" id="wa-bulk-send">
                                                <i class="fa-solid fa-paper-plane"></i> <span
                                                    id="wa-bulk-send-label">{{ __('send') }}</span>
                                                <span class="badge badge-academic-year ml-1" id="wa-bulk-count">0</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Recipients --}}
                                <div class="col-xl-7">
                                    <div class="mb-3">
                                        <h3 class="wa-section-title">{{ __('select_recipients') }}</h3>
                                        <p class="wa-section-subtitle">{{ __('select_recipients_note') }}</p>
                                    </div>

                                    {{-- Audience source --}}
                                    <div class="wa-field">
                                        <div class="wa-schedule-options">
                                            <label class="wa-radio"><input type="radio" name="wa_recipient_source"
                                                    value="users" checked> {{ __('system_users') }}</label>
                                            <label class="wa-radio"><input type="radio" name="wa_recipient_source"
                                                    value="csv"> {{ __('external_users') }}</label>
                                        </div>
                                    </div>

                                    {{-- External users panel --}}
                                    <div class="d-none" id="wa-csv-panel">
                                        <p class="wa-hint mb-2"><i class="fa-solid fa-circle-info"></i>
                                            {{ __('external_users_send_note') }}</p>
                                        <table aria-describedby="mydesc" class='table' id='wa_ext_table'
                                            data-toggle="table" data-url="{{ route('whatsapp.contacts.list') }}"
                                            data-side-pagination="server" data-pagination="true"
                                            data-page-list="[10, 20, 50, 100]" data-search="true"
                                            data-show-refresh="true" data-trim-on-search="false"
                                            data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc"
                                            data-query-params="queryParams" data-click-to-select="true"
                                            data-maintain-selected="true" data-response-handler="waExtResponseHandler"
                                            data-escape="true">
                                            <thead>
                                                <tr>
                                                    <th data-field="state" data-checkbox="true"></th>
                                                    <th scope="col" data-field="id" data-sortable="true"
                                                        data-visible="false">{{ __('id') }}</th>
                                                    <th scope="col" data-field="no">{{ __('no.') }}</th>
                                                    <th scope="col" data-field="name">{{ __('name') }}</th>
                                                    <th scope="col" data-field="mobile">{{ __('mobile') }}</th>
                                                    <th scope="col" data-field="tag">{{ __('tag') }}</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>

                                    <div id="wa-users-panel">
                                        <div class="wa-field">
                                            <select multiple class="form-control select2-dropdown" id="wa-bulk-roles"
                                                data-placeholder="{{ __('roles') }}">
                                                @foreach ($bulkRoles as $roleId => $roleName)
                                                    <option value="{{ $roleId }}">{{ $roleName }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <table aria-describedby="mydesc" class='table' id='wa_users_table'
                                            data-toggle="table" data-url="{{ route('whatsapp.recipients.list') }}"
                                            data-side-pagination="server" data-pagination="true"
                                            data-page-list="[10, 20, 50, 100]" data-search="true"
                                            data-show-refresh="true" data-trim-on-search="false"
                                            data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc"
                                            data-maintain-selected="true" data-click-to-select="true"
                                            data-query-params="waUsersQueryParams"
                                            data-response-handler="waUsersResponseHandler" data-escape="true">
                                            <thead>
                                                <tr>
                                                    <th data-field="state" data-checkbox="true"></th>
                                                    <th scope="col" data-field="id" data-sortable="true"
                                                        data-visible="false">{{ __('id') }}</th>
                                                    <th scope="col" data-field="no">{{ __('no.') }}</th>
                                                    <th scope="col" data-field="full_name">{{ __('name') }}
                                                    </th>
                                                    <th scope="col" data-field="mobile">{{ __('mobile') }}</th>
                                                    <th scope="col" data-field="role">{{ __('roles') }}</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>


                        {{-- =================== EXTERNAL USERS =================== --}}

                        <div class="tab-pane fade" id="wa-contacts" role="tabpanel">
                            <div class="d-flex justify-content-end align-items-center flex-wrap px-4 pt-4">
                                <div class="mb-2">
                                    <a href="{{ route('whatsapp.contacts-sample') }}"
                                        class="wa-btn wa-btn-outline wa-btn-sm mr-1">
                                        <i class="fa-solid fa-download"></i> {{ __('sample_csv') }}
                                    </a>
                                    <button type="button" class="wa-btn wa-btn-outline wa-btn-sm mr-1"
                                        id="wa-contacts-import-btn">
                                        <i class="fa-solid fa-file-import"></i> {{ __('import_csv') }}
                                    </button>
                                    <button type="button" class="wa-btn wa-btn-primary wa-btn-sm"
                                        id="wa-contact-create">
                                        <i class="fa-solid fa-plus"></i> {{ __('add_contact') }}
                                    </button>
                                    <input type="file" id="wa-contacts-import-file" class="d-none"
                                        accept=".csv,text/csv">
                                </div>
                            </div>
                            <table aria-describedby="mydesc" class='table' id='wa_contacts_table' data-toggle="table"
                                data-url="{{ route('whatsapp.contacts.list') }}" data-side-pagination="server"
                                data-pagination="true" data-page-list="[10, 20, 50, 100]" data-search="true"
                                data-show-refresh="true" data-trim-on-search="false" data-mobile-responsive="true"
                                data-sort-name="id" data-sort-order="desc" data-query-params="queryParams"
                                data-escape="true">
                                <thead>
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="false" data-visible="false">
                                            {{ __('id') }}</th>
                                        <th scope="col" data-field="no">{{ __('no.') }}</th>
                                        <th scope="col" data-field="name">{{ __('name') }}</th>
                                        <th scope="col" data-field="mobile">{{ __('mobile') }}</th>
                                        <th scope="col" data-field="tag">{{ __('tag') }}</th>
                                        <th scope="col" data-field="source">{{ __('source') }}</th>
                                        <th data-events="whatsappContactEvents" scope="col" data-field="operate"
                                            data-escape="false">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>


                        {{-- =================== SCHEDULED =================== --}}

                        <div class="tab-pane fade" id="wa-scheduled" role="tabpanel">
                            <table aria-describedby="mydesc" class='table' id='wa_scheduled_table' data-toggle="table"
                                data-url="{{ route('whatsapp.scheduled.list') }}" data-side-pagination="server"
                                data-pagination="true" data-page-list="[10, 20, 50]" data-search="true"
                                data-show-refresh="true" data-trim-on-search="false" data-mobile-responsive="true"
                                data-sort-name="id" data-sort-order="desc" data-query-params="queryParams"
                                data-escape="true">
                                <thead>
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="false" data-visible="false">
                                            {{ __('id') }}</th>
                                        <th scope="col" data-field="no">{{ __('no.') }}</th>
                                        <th scope="col" data-field="template_name">{{ __('templates') }}</th>
                                        <th scope="col" data-field="recipients">{{ __('recipient') }}</th>
                                        <th scope="col" data-field="schedule_type" data-escape="false"
                                            data-formatter="waScheduleTypeFormatter">{{ __('type') }}</th>
                                        <th scope="col" data-field="next_run_at">{{ __('next_run') }}</th>
                                        <th scope="col" data-field="last_run_at">{{ __('last_run') }}</th>
                                        <th scope="col" data-field="status" data-escape="false"
                                            data-formatter="waScheduleStatusFormatter">{{ __('status') }}</th>
                                        <th data-events="whatsappScheduledEvents" scope="col" data-field="operate"
                                            data-escape="false">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>


                        {{-- =================== MANUAL TEMPLATES =================== --}}
                        <div class="tab-pane fade" id="wa-manual-templates" role="tabpanel">
                            <div class="d-flex justify-content-end align-items-center flex-wrap px-4 pt-4">
                                <button type="button" class="wa-btn wa-btn-primary mb-2" id="wa-manual-create">
                                    <i class="fa-solid fa-plus"></i> {{ __('create_template') }}
                                </button>
                            </div>
                            <table aria-describedby="mydesc" class='table' id='wa_manual_table' data-toggle="table"
                                data-url="{{ route('whatsapp.manual-templates.list') }}" data-side-pagination="server"
                                data-pagination="true" data-page-list="[5, 10, 20, 50]" data-search="true"
                                data-show-refresh="true" data-trim-on-search="false" data-mobile-responsive="true"
                                data-sort-name="id" data-sort-order="desc" data-query-params="queryParams"
                                data-escape="true">
                                <thead>
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="false" data-visible="false">
                                            {{ __('id') }}</th>
                                        <th scope="col" data-field="no">{{ __('no.') }}</th>
                                        <th scope="col" data-field="name" data-escape="false"
                                            data-formatter="waManualNameFormatter">{{ __('name') }}</th>
                                        <th scope="col" data-field="body" data-escape="false"
                                            data-formatter="waBodyFormatter">{{ __('message') }}</th>
                                        <th scope="col" data-field="variables" data-escape="false"
                                            data-formatter="waVariablesFormatter">{{ __('variables') }}</th>
                                        <th scope="col" data-field="status" data-escape="false"
                                            data-formatter="waActiveFormatter">{{ __('status') }}</th>
                                        <th data-events="whatsappManualEvents" scope="col" data-field="operate"
                                            data-escape="false">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>

                        {{-- =================== TEMPLATES =================== --}}
                        <div class="tab-pane fade" id="wa-templates" role="tabpanel">

                            {{-- <div class="wa-alert-warning">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                {{ __('your_package_does_not_allow_this_action') }}
                            </div> --}}

                            <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                                data-url="{{ route('whatsapp.templates.list') }}" data-side-pagination="server"
                                data-pagination="true" data-page-list="[5, 10, 20, 50]" data-search="true"
                                data-show-refresh="true" data-trim-on-search="false" data-mobile-responsive="true"
                                data-sort-name="id" data-sort-order="asc" data-query-params="queryParams"
                                data-escape="true">
                                <thead>
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="false" data-visible="false">
                                            {{ __('id') }}</th>
                                        <th scope="col" data-field="no">{{ __('no.') }}</th>
                                        <th scope="col" data-field="event" data-escape="false"
                                            data-formatter="waEventFormatter">{{ __('event') }}</th>
                                        <th scope="col" data-field="name" data-escape="false"
                                            data-formatter="waTemplateNameFormatter">{{ __('name') }}</th>
                                        <th scope="col" data-field="variables" data-escape="false"
                                            data-formatter="waVariablesFormatter">{{ __('variables') }}</th>
                                        <th scope="col" data-field="status" data-escape="false"
                                            data-formatter="waActiveFormatter">{{ __('status') }}</th>
                                        <th data-events="whatsappTemplateEvents" scope="col" data-field="operate"
                                            data-escape="false">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>

                        {{-- =================== LOGS =================== --}}

                        <div class="tab-pane fade" id="wa-logs" role="tabpanel">
                            <div class="wa-logs-toolbar">
                                <button type="button" class="wa-btn wa-btn-outline" id="wa-retry-selected">
                                    <i class="fa-solid fa-rotate-right"></i> {{ __('retry_selected') }}
                                </button>
                                <button type="button" class="wa-btn wa-btn-outline" id="wa-retry-all">
                                    <i class="fa-solid fa-rotate"></i> {{ __('retry_all_failed') }}
                                </button>
                                <select class="wa-input" id="wa-log-status-filter">
                                    <option value="">{{ __('all') }} {{ __('status') }}</option>
                                    <option value="pending">{{ __('pending') }}</option>
                                    <option value="sent">{{ __('sent') }}</option>
                                    <option value="delivered">{{ __('delivered') }}</option>
                                    <option value="read">{{ __('read') }}</option>
                                    <option value="failed">{{ __('failed') }}</option>
                                </select>
                                <div class="wa-input-wrap">
                                    <i class="fa-solid fa-calendar wa-input-icon"></i>
                                    <input type="date" class="wa-input" id="wa-log-date-filter">
                                </div>
                            </div>

                            <table aria-describedby="mydesc" class='table' id='wa_logs_table' data-toggle="table"
                                data-url="{{ route('whatsapp.logs.list') }}" data-side-pagination="server"
                                data-pagination="true" data-page-list="[10, 20, 50, 100]" data-search="true"
                                data-show-refresh="true" data-trim-on-search="false" data-show-columns="true"
                                data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc"
                                data-query-params="waLogsQueryParams" data-click-to-select="true"
                                data-maintain-selected="true" data-response-handler="waLogsResponseHandler"
                                data-escape="true">
                                <thead>
                                    <tr>
                                        <th data-field="state" data-checkbox="true"></th>
                                        <th scope="col" data-field="id" data-sortable="false" data-visible="false">
                                            {{ __('id') }}</th>
                                        <th scope="col" data-field="no">{{ __('no.') }}</th>
                                        <th scope="col" data-field="recipient">{{ __('recipient') }}</th>
                                        <th scope="col" data-field="mobile">{{ __('mobile') }}</th>
                                        <th scope="col" data-field="event">{{ __('event') }}</th>
                                        <th scope="col" data-field="message" data-escape="false"
                                            data-formatter="waBodyFormatter">{{ __('message') }}</th>
                                        <th scope="col" data-field="status" data-escape="false"
                                            data-formatter="waStatusFormatter">{{ __('status') }}</th>
                                        <th scope="col" data-field="response" data-escape="false"
                                            data-formatter="waBodyFormatter">{{ __('response') }}</th>
                                        <th scope="col" data-field="created_at">{{ __('date') }}</th>
                                    </tr>
                                </thead>
                            </table>

                            {{-- Empty state (shown by JS when there are no logs) --}}
                            <div class="wa-empty-state d-none" id="wa-logs-empty">
                                <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg"
                                    aria-hidden="true">
                                    <circle cx="100" cy="100" r="80" fill="#F8FAFC" />
                                    <path
                                        d="M140 85C140 98.8071 128.807 110 115 110H75C61.1929 110 50 98.8071 50 85C50 71.1929 61.1929 60 75 60H115C128.807 60 140 71.1929 140 85Z"
                                        fill="#E2E8F0" />
                                    <path
                                        d="M150 115C150 128.807 138.807 140 125 140H85C71.1929 140 60 128.807 60 115C60 101.193 71.1929 90 85 90H125C138.807 90 150 101.193 150 115Z"
                                        fill="#CBD5E1" />
                                    <path d="M125 100H85" stroke="#94A3B8" stroke-width="4" stroke-linecap="round" />
                                    <path d="M110 115H85" stroke="#94A3B8" stroke-width="4" stroke-linecap="round" />
                                    <circle cx="130" cy="130" r="25" fill="#14B8A6" opacity="0.2" />
                                    <circle cx="130" cy="130" r="15" fill="#14B8A6" />
                                    <path d="M125 130L129 134L135 126" stroke="white" stroke-width="3"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <h3 class="wa-empty-title">{{ __('no_messages_yet') }}</h3>
                                <p class="wa-empty-text">{{ __('no_messages_yet_note') }}</p>
                                <button type="button" class="wa-btn wa-btn-primary" id="wa-refresh-logs">
                                    <i class="fa-solid fa-arrows-rotate"></i> {{ __('refresh') }}
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Edit Template Modal ===== --}}
    <div class="modal fade wa-modal" id="editModal" data-backdrop="static" tabindex="-1" role="dialog"
        aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">
                        <span class="wa-modal-title-icon"><i class="fa-solid fa-mobile-screen-button"></i></span>
                        <span id="wa-edit-title">{{ __('edit_template') }}</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true"><i class="fa fa-close"></i></span>
                    </button>
                </div>
                <form class="edit-form" action="{{ url('whatsapp/templates') }}"
                    data-success-function="formSuccessFunction" novalidate="novalidate">
                    @csrf
                    <div class="modal-body">

                        <div class="form-group mb-0 d-flex align-items-center justify-content-between">
                            <div>
                                <p class="wa-enable-title mb-0">{{ __('active') }}</p>
                            </div>
                            <label class="wa-switch" for="wa-edit-status">
                                <input type="checkbox" id="wa-edit-status" name="status" value="1">
                                <span class="wa-slider"></span>
                            </label>
                        </div>

                        {{-- Live WhatsApp bubble preview --}}
                        <label class="wa-label-xs">{{ __('message_preview') }}</label>
                        <div class="wa-preview-wrap">
                            <div class="wa-bubble"><span id="wa-edit-preview"></span>
                                <span class="wa-bubble-time">{{ \Carbon\Carbon::now()->format('h:i A') }} <i
                                        class="fa-solid fa-check-double"></i></span>
                            </div>
                        </div>

                        <div class="row">
                            <div class="form-group col-sm-12 col-md-6 ps-0">
                                <label class="wa-label">{{ __('event') }}</label>
                                <input type="text" class="wa-input" id="wa-edit-event" readonly>
                            </div>
                            <div class="form-group col-sm-12 col-md-6 pe-0">
                                <label class="wa-label">{{ __('name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="wa-edit-name" class="wa-input" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="wa-label">{{ __('header') }}</label>
                            <input type="text" name="header" id="wa-edit-header" class="wa-input">
                        </div>
                        <div class="form-group">
                            <label class="wa-label">{{ __('message') }} <span class="text-danger">*</span></label>
                            <textarea name="body" id="wa-edit-body" class="wa-input" rows="6" required></textarea>

                            {{-- Detected variables --}}
                            <div class="wa-detected-vars">
                                <label class="wa-label-xs">{{ __('whatsapp_template_variables_note') }}</label>
                                <div id="wa-edit-variables"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="wa-label">{{ __('footer') }}</label>
                            <input type="text" name="footer" id="wa-edit-footer" class="wa-input">
                        </div>
                        {{-- <div class="form-group">
                            <label class="wa-label">{{ __('meta_template_name') }}</label>
                            <div class="wa-input-wrap">
                                <i class="fa-brands fa-meta wa-input-icon"></i>
                                <input type="text" name="meta_template_name" id="wa-edit-meta-name"
                                    class="wa-input wa-input-mono" placeholder="e.g. hello_world">
                            </div>
                            <p class="wa-hint"><i class="fa-solid fa-circle-info"></i>
                                {{ __('meta_template_name_hint') }}</p>
                        </div> --}}
                        {{-- <div class="form-group" id="wa-edit-meta-param-wrap">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="wa-enable-title mb-0">{{ __('meta_body_param') }}</p>
                                    <p class="wa-enable-desc mb-0">{{ __('meta_body_param_hint') }}</p>
                                </div>
                                <label class="wa-switch" for="wa-edit-meta-param">
                                    <input type="checkbox" id="wa-edit-meta-param" name="meta_body_param"
                                        value="1">
                                    <span class="wa-slider"></span>
                                </label>
                            </div>
                        </div> --}}
                        {{-- <div class="wa-alert-warning mx-0 mt-0 mb-3 d-none" id="wa-edit-delivery-hint"></div> --}}



                    </div>
                    <div class="modal-footer">
                        <button type="button" class="wa-btn wa-btn-outline"
                            data-dismiss="modal">{{ __('cancel') }}</button>
                        <input class="wa-btn wa-btn-primary" type="submit" value="{{ __('submit') }}">
                    </div>
                </form>
            </div>
        </div>
    </div>
    {{-- ===== External Contact Create / Edit Modal ===== --}}
    <div class="modal fade wa-modal" id="waContactModal" data-backdrop="static" tabindex="-1" role="dialog"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="wa-modal-title-icon"><i class="fa-solid fa-address-book"></i></span>
                        <span id="wa-contact-modal-title">{{ __('add_contact') }}</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true"><i class="fa fa-close"></i></span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="wa-label">{{ __('name') }}</label>
                        <input type="text" id="wa-contact-name" class="wa-input">
                    </div>
                    <div class="form-group">
                        <label class="wa-label">{{ __('mobile') }} <span class="text-danger">*</span></label>
                        <div class="wa-input-wrap">
                            <i class="fa-solid fa-mobile-screen-button wa-input-icon"></i>
                            <input type="text" id="wa-contact-mobile" class="wa-input" required
                                placeholder="91XXXXXXXXXX">
                        </div>
                        <p class="wa-hint"><i class="fa-solid fa-circle-info"></i>
                            {{ __('enter_mobile_with_country_code') }}</p>
                    </div>
                    <div class="form-group mb-0">
                        <label class="wa-label">{{ __('tag') }}</label>
                        <input type="text" id="wa-contact-tag" class="wa-input" placeholder="e.g. Alumni 2020">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="wa-btn wa-btn-outline"
                        data-dismiss="modal">{{ __('cancel') }}</button>
                    <button type="button" class="wa-btn wa-btn-primary"
                        id="wa-contact-save">{{ __('submit') }}</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Manual Template Create / Edit Modal ===== --}}
    <div class="modal fade wa-modal" id="waManualModal" data-backdrop="static" tabindex="-1" role="dialog"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="wa-modal-title-icon"><i class="fa-solid fa-pen-to-square"></i></span>
                        <span id="wa-manual-modal-title">{{ __('create_template') }}</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true"><i class="fa fa-close"></i></span>
                    </button>
                </div>
                <div class="modal-body">

                    <div class="form-group d-flex align-items-center justify-content-between">
                        <p class="wa-enable-title mb-0">{{ __('active') }}</p>
                        <label class="wa-switch" for="wa-manual-status">
                            <input type="checkbox" id="wa-manual-status" value="1" checked>
                            <span class="wa-slider"></span>
                        </label>
                    </div>

                    <label class="wa-label-xs">{{ __('message_preview') }}</label>
                    <div class="wa-preview-wrap mb-3">
                        <div class="wa-bubble"><span id="wa-manual-preview"></span>
                            <span class="wa-bubble-time">10:42 AM <i class="fa-solid fa-check-double"></i></span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-sm-12 col-md-6 ps-0">
                            <label class="wa-label">{{ __('name') }} <span class="text-danger">*</span></label>
                            <input type="text" id="wa-manual-name" class="wa-input" required>
                        </div>
                        <div class="form-group col-sm-12 col-md-6 pe-0">
                            <label class="wa-label">{{ __('category') }}</label>
                            <input type="text" id="wa-manual-category" class="wa-input"
                                placeholder="{{ __('category') }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="wa-label">{{ __('header') }}</label>
                        <input type="text" id="wa-manual-header" class="wa-input">
                    </div>
                    <div class="form-group">
                        <label class="wa-label">{{ __('message') }} <span class="text-danger">*</span></label>
                        <textarea id="wa-manual-body" class="wa-input" rows="6" required
                            placeholder="Dear @{{ recipient_name }}, ..."></textarea>
                        <p class="wa-hint"><i class="fa-solid fa-circle-info"></i>
                            {{ __('manual_template_variables_note') }}</p>

                        <div class="wa-detected-vars">
                            <label class="wa-label-xs">{{ __('variables') }}</label>
                            <div id="wa-manual-variables">-</div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="wa-label">{{ __('footer') }}</label>
                        <input type="text" id="wa-manual-footer" class="wa-input">
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="wa-btn wa-btn-outline"
                        data-dismiss="modal">{{ __('cancel') }}</button>
                    <button type="button" class="wa-btn wa-btn-primary"
                        id="wa-manual-save">{{ __('submit') }}</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Template Test Modal ===== --}}
    <div class="modal fade wa-modal" id="waTemplateTestModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="wa-modal-title-icon"><i class="fa-solid fa-paper-plane"></i></span>
                        <span class="text-white">{{ __('send_test') }}: <span class="text-white"
                                id="wa-test-template-name"></span></span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true"><i class="fa fa-close"></i></span>
                    </button>
                </div>
                <div class="modal-body">
                    <label class="wa-label-xs">{{ __('message_preview') }}</label>
                    <div class="wa-preview-wrap">
                        <div class="wa-bubble"><span id="wa-test-template-preview"></span>
                            <span class="wa-bubble-time">10:42 AM <i class="fa-solid fa-check-double"></i></span>
                        </div>
                    </div>
                    <p class="wa-hint mb-3"><i class="fa-solid fa-circle-info"></i> {{ __('template_test_note') }}</p>
                    <label class="wa-label">{{ __('enter_mobile_with_country_code') }} <span
                            class="text-danger">*</span></label>
                    <div class="wa-join">
                        <span class="wa-join-prepend"><i class="fa-solid fa-mobile-screen-button"></i></span>
                        <input type="text" class="wa-input" id="wa-test-template-mobile"
                            placeholder="91XXXXXXXXXX">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="wa-btn wa-btn-outline"
                        data-dismiss="modal">{{ __('cancel') }}</button>
                    <button type="button" class="wa-btn wa-btn-primary" id="wa-test-template-send">
                        <i class="fa-solid fa-paper-plane"></i> {{ __('send') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        var whatsappTestConnectionUrl = "{{ route('whatsapp.test-connection') }}";
        var whatsappTestMessageUrl = "{{ route('whatsapp.test-message') }}";
        var whatsappDefaultMetaTemplate = "{{ $settings->default_meta_template }}";
        var whatsappBulkSendUrl = "{{ route('whatsapp.bulk-send') }}";
        var whatsappManualStoreUrl = "{{ route('whatsapp.manual-templates.store') }}";
        var whatsappLogsRetryUrl = "{{ route('whatsapp.logs.retry') }}";
        var whatsappParseContactsUrl = "{{ route('whatsapp.parse-contacts') }}";
        var whatsappContactStoreUrl = "{{ route('whatsapp.contacts.store') }}";
        var whatsappContactsImportUrl = "{{ route('whatsapp.contacts.import') }}";
        var whatsappBulkTemplates = {!! json_encode($templates) !!};
    </script>
    <script src="{{ asset('assets/js/custom/whatsapp.js') }}"></script>
@endsection
