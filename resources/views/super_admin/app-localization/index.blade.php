@extends('layouts.master')

@section('title')
    {{ __('app_localization') }}
@endsection

@php
    $platforms = [
        'student' => [
            'column' => 'student_app_file',
            'input' => 'student_app_file',
            'label' => __('student_parent_app'),
            'tint' => 'al-tint-indigo',
            'icon' => 'fa-solid fa-mobile-screen-button',
        ],
        'staff' => [
            'column' => 'staff_app_file',
            'input' => 'staff_app_file',
            'label' => __('staff_teacher_app'),
            'tint' => 'al-tint-emerald',
            'icon' => 'fa-solid fa-chalkboard-user',
        ],
        'web' => [
            'column' => 'web_file',
            'input' => 'web_file',
            'label' => __('student_web_portal'),
            'tint' => 'al-tint-sky',
            'icon' => 'fa-solid fa-laptop',
        ],
        'panel' => [
            'column' => 'file',
            'input' => 'panel_file',
            'label' => __('admin_panel'),
            'tint' => 'al-tint-rose',
            'icon' => 'fa-solid fa-sliders',
        ],
    ];
@endphp

@section('content')
    <div class="content-wrapper">

        {{-- Page Header --}}
        <div class="al-page-header">
            <div>
                <h3 class="page-title">{{ __('app_localization') }}</h3>
            </div>
        </div>

        <div class="row align-items-start">

            {{-- ============ LEFT COLUMN : Add Language Form ============ --}}
            <div class="col-lg-4 grid-margin">
                <div class="al-card al-form-card">

                    <div class="al-card-header">
                        <h4 class="al-card-title">
                            <i class="fa-solid fa-circle-plus"></i> {{ __('add_language') }}
                        </h4>
                    </div>

                    <form id="al-language-form" class="create-form" action="{{ route('app-localization.store') }}"
                        data-success-function="formSuccessFunction" enctype="multipart/form-data" novalidate="novalidate">
                        @csrf
                        <div class="al-card-body">

                            {{-- Core Info --}}
                            <div class="al-field">
                                <label class="al-label" for="al-native-name">{{ __('name') }} <span
                                        class="text-danger">*</span></label>
                                <input type="text" id="al-native-name" name="name" class="al-input" required
                                    placeholder="{{ __('native_name_placeholder') }}">
                            </div>

                            <div class="al-field-grid">
                                <div class="al-field">
                                    <label class="al-label" for="al-code">{{ __('code') }} <span
                                            class="text-danger">*</span></label>
                                    <input type="text" id="al-code" name="code" class="al-input" required
                                        placeholder="{{ __('language_code_placeholder') }}">
                                </div>
                                <div class="al-field">
                                    <label class="al-label" for="al-lang-icon">{{ __('icon_image') }}</label>
                                    <div class="al-icon-upload" id="al-icon-upload-ui">
                                        <input type="file" id="al-lang-icon" name="image"
                                            accept="image/png,image/jpeg,image/jpg,image/svg+xml">
                                        <span class="al-icon-upload-text"
                                            id="al-icon-file-name">{{ __('upload_flag_icon') }}</span>
                                        <i class="fa-solid fa-upload"></i>
                                    </div>
                                </div>
                            </div>

                            {{-- RTL Toggle --}}
                            <div class="al-toggle-box">
                                <div>
                                    <p class="al-toggle-title">{{ __('right_to_left_rtl') }}</p>
                                    <p class="al-toggle-desc">{{ __('rtl_language_hint') }}</p>
                                </div>
                                <label class="al-switch" for="al-rtl-toggle">
                                    <input type="checkbox" id="al-rtl-toggle" name="rtl" value="1">
                                    <span class="al-slider"></span>
                                </label>
                            </div>

                            <div class="al-divider"></div>

                            {{-- Translation Files --}}
                            <div class="al-section-head">
                                <h5 class="al-section-title">{{ __('translation_files') }}</h5>
                                <span class="al-chip">{{ __('json_only') }}</span>
                            </div>

                            {{-- Sample JSON info + dropdown --}}
                            <div class="al-info-box">
                                <p class="al-info-text">
                                    <i class="fa-solid fa-circle-info"></i>
                                    {{ __('sample_json_note') }}
                                </p>
                                <div class="al-dropdown" id="al-sample-dropdown">
                                    <button type="button" class="al-btn al-btn-outline al-btn-sm"
                                        id="al-sample-dropdown-btn">
                                        <i class="fa-solid fa-download"></i> {{ __('sample_json') }} <i
                                            class="fa-solid fa-caret-down"></i>
                                    </button>
                                    <div class="al-dropdown-menu" id="al-sample-dropdown-menu">
                                        <div class="al-dropdown-header">{{ __('select_platform_sample') }}</div>
                                        <a href="{{ route('app-localization.sample', 'panel') }}" class="al-dropdown-item">
                                            <i class="fa-solid fa-sliders al-text-rose"></i> {{ __('admin_panel') }}
                                        </a>
                                        <a href="{{ route('app-localization.sample', 'student') }}"
                                            class="al-dropdown-item">
                                            <i class="fa-solid fa-mobile-screen-button al-text-indigo"></i>
                                            {{ __('student_parent_app') }}
                                        </a>
                                        <a href="{{ route('app-localization.sample', 'staff') }}" class="al-dropdown-item">
                                            <i class="fa-solid fa-chalkboard-user al-text-emerald"></i>
                                            {{ __('staff_teacher_app') }}
                                        </a>
                                        <a href="{{ route('app-localization.sample', 'web') }}" class="al-dropdown-item">
                                            <i class="fa-solid fa-laptop al-text-sky"></i> {{ __('student_web_portal') }}
                                        </a>
                                    </div>
                                </div>
                            </div>

                            {{-- Platform JSON upload cards --}}
                            <div class="al-upload-list">
                                @foreach ($platforms as $type => $platform)
                                    <div class="al-upload-card" data-target="al-file-{{ $type }}">
                                        <div class="al-upload-info">
                                            <div class="al-upload-icon {{ $platform['tint'] }}">
                                                <i class="{{ $platform['icon'] }}"></i>
                                            </div>
                                            <div class="al-upload-meta">
                                                <p class="al-upload-title">{{ $platform['label'] }}</p>
                                                <p class="al-upload-status">{{ __('upload_file') }}...</p>
                                            </div>
                                        </div>
                                        <span class="al-upload-action"><i class="fa-solid fa-upload"></i></span>
                                        <input type="file" id="al-file-{{ $type }}"
                                            name="{{ $platform['input'] }}" class="al-hidden-input"
                                            accept=".json,application/json">
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Footer Actions --}}
                        <div class="al-card-footer">
                            <button type="reset" class="al-btn al-btn-secondary"
                                id="al-reset-btn">{{ __('reset') }}</button>
                            <input type="submit" class="al-btn al-btn-primary" value="{{ __('save_language') }}">
                        </div>
                    </form>
                </div>
            </div>

            {{-- ============ RIGHT COLUMN : Languages List ============ --}}
            <div class="col-lg-8 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{ __('list_language') }}
                        </h4>

                        <div class="row">
                            <div class="col-12">
                                <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                                    data-url="{{ route('app-localization.list') }}" data-click-to-select="true"
                                    data-side-pagination="server" data-pagination="true"
                                    data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                                    data-show-columns="true" data-show-refresh="true" data-fixed-columns="false"
                                    data-fixed-number="1" data-fixed-right-number="1" data-trim-on-search="false"
                                    data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc"
                                    data-maintain-selected="true" data-export-data-type='all' data-show-export="true"
                                    data-export-options='{ "fileName": "language-settings-list-{{ date('d-m-y') }}","ignoreColumn": ["operate"]}'
                                    data-query-params="queryParams" data-escape="true">
                                    <thead>
                                        <tr>
                                            <th scope="col" data-field="id" data-sortable="false"
                                                data-visible="false">{{ __('id') }}</th>
                                            <th scope="col" data-field="no">{{ __('no.') }}</th>
                                            <th scope="col" data-field="name" data-sortable="true"
                                                data-escape="false" data-formatter="languageNameFormatter">
                                                {{ __('name') }}</th>
                                            <th scope="col" data-field="image" data-sortable="false"
                                                data-escape="false" data-formatter="platformCoverageFormatter">
                                                {{ __('platform_coverage') }}
                                            </th>
                                            <th scope="col" data-field="is_rtl" data-sortable="false"
                                                data-formatter="yesAndNoStatusFormatter">{{ __('rtl') }}</th>
                                            <th scope="col" data-field="operate" data-events="appLocalizationEvents"
                                                data-escape="false">{{ __('action') }}</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ Edit Language Modal ============ --}}
    <div class="modal fade al-modal" id="editModal" data-backdrop="static" tabindex="-1" role="dialog"
        aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg al-modal-dialog" role="document">
            <div class="modal-content al-modal-content">

                <form id="al-edit-form" class="edit-form" action="{{ url('app-localization') }}"
                    data-success-function="formSuccessFunction" enctype="multipart/form-data" novalidate="novalidate">
                    @csrf
                    @method('PUT')

                    <div class="modal-header al-modal-header">
                        <h5 class="modal-title al-modal-title" id="editModalLabel">
                            <i class="fa-solid fa-pencil"></i> {{ __('edit_language_files') }}
                        </h5>
                        <button type="button" class="al-modal-close" data-dismiss="modal" aria-label="Close">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="modal-body al-modal-body">

                        {{-- Language summary --}}
                        <div class="al-modal-lang">
                            <div class="al-modal-flag" id="al-modal-flag">🌐</div>
                            <div>
                                <h6 class="al-modal-lang-name" id="al-modal-lang-name"></h6>
                                <p class="al-modal-lang-code" id="al-modal-lang-code"></p>
                            </div>
                        </div>

                        {{-- Core fields --}}
                        <div class="al-field-grid">
                            <div class="al-field">
                                <label class="al-label" for="al-edit-name">{{ __('name') }} <span
                                        class="text-danger">*</span></label>
                                <input type="text" id="al-edit-name" name="name" class="al-input" required
                                    placeholder="{{ __('native_name_placeholder') }}">
                            </div>
                            <div class="al-field">
                                <label class="al-label" for="al-edit-code">{{ __('code') }} <span
                                        class="text-danger">*</span></label>
                                <input type="text" id="al-edit-code" name="code" class="al-input" required
                                    placeholder="{{ __('language_code_placeholder') }}">
                            </div>
                        </div>

                        <div class="al-field-grid">
                            <div class="al-field">
                                <label class="al-label" for="al-edit-icon">{{ __('icon_image') }}</label>
                                <div class="al-icon-upload" id="al-edit-icon-upload-ui">
                                    <input type="file" id="al-edit-icon" name="image"
                                        accept="image/png,image/jpeg,image/jpg,image/svg+xml">
                                    <span class="al-icon-upload-text"
                                        id="al-edit-icon-file-name">{{ __('upload_flag_icon') }}</span>
                                    <i class="fa-solid fa-upload"></i>
                                </div>
                            </div>
                            <div class="al-field">
                                <label class="al-label">{{ __('right_to_left_rtl') }}</label>
                                <div class="al-toggle-box al-toggle-box-compact">
                                    <p class="al-toggle-desc">{{ __('rtl_language_hint') }}</p>
                                    <label class="al-switch" for="al-edit-rtl">
                                        <input type="checkbox" id="al-edit-rtl" name="rtl" value="1">
                                        <span class="al-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="al-divider"></div>

                        <h6 class="al-section-title">{{ __('manage_json_files') }}</h6>

                        <div class="al-upload-list">
                            @foreach ($platforms as $type => $platform)
                                <div class="al-file-row" id="al-edit-row-{{ $type }}">
                                    <div class="al-upload-info">
                                        <div class="al-upload-icon {{ $platform['tint'] }}">
                                            <i class="{{ $platform['icon'] }}"></i>
                                        </div>
                                        <div class="al-upload-meta">
                                            <p class="al-upload-title">{{ $platform['label'] }}</p>
                                            <p class="al-file-status" id="al-edit-status-{{ $type }}"></p>
                                        </div>
                                    </div>
                                    <div class="al-file-actions">
                                        <a href="#" target="_blank" class="al-btn al-btn-light al-btn-xs"
                                            id="al-edit-download-{{ $type }}">
                                            <i class="fa-solid fa-download"></i> {{ __('download') }}
                                        </a>
                                        <button type="button" class="al-btn al-btn-tint al-btn-xs al-edit-replace-btn"
                                            data-target="al-edit-file-{{ $type }}"
                                            id="al-edit-replace-{{ $type }}">
                                            <i class="fa-solid fa-right-left"></i> {{ __('replace') }}
                                        </button>
                                        <input type="file" id="al-edit-file-{{ $type }}"
                                            name="{{ $platform['input'] }}" class="al-hidden-input al-edit-file-input"
                                            data-type="{{ $type }}" accept=".json,application/json">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="modal-footer al-modal-footer">
                        <button type="button" class="al-btn al-btn-secondary"
                            data-dismiss="modal">{{ __('cancel') }}</button>
                        <input type="submit" class="al-btn al-btn-primary al-btn-modal-submit"
                            value="{{ __('update') }}">
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        function alTrans(key, fallback) {
            return (window.trans && window.trans[key]) ? window.trans[key] : fallback;
        }

        function alSetEditFileRow(type, fileData, code) {
            var status = $('#al-edit-status-' + type);
            var download = $('#al-edit-download-' + type);
            var replace = $('#al-edit-replace-' + type);
            var row = $('#al-edit-row-' + type);

            row.removeClass('al-file-row-missing');
            status.removeClass('al-file-status-done al-file-status-missing al-file-status-pending');

            if (fileData && fileData.name) {
                status.addClass('al-file-status-done')
                    .html('<i class="fa-solid fa-circle-check"></i> ' + $('<span>').text(code + '_' + type + '.json ' +
                        alTrans('uploaded', 'uploaded')).html());
                download.attr('href', fileData.download).removeClass('d-none');
                replace.html('<i class="fa-solid fa-right-left"></i> ' + alTrans('replace', 'Replace'))
                    .removeClass('al-btn-warning').addClass('al-btn-tint');
            } else {
                row.addClass('al-file-row-missing');
                status.addClass('al-file-status-missing')
                    .html('<i class="fa-solid fa-circle-exclamation"></i> ' + alTrans('missing_json_file',
                        'Missing JSON file'));
                download.addClass('d-none');
                replace.html('<i class="fa-solid fa-upload"></i> ' + alTrans('upload_now', 'Upload Now'))
                    .removeClass('al-btn-tint').addClass('al-btn-warning');
            }
        }



        $(document).ready(function() {

            // =====================================================
            // Add Language form
            // =====================================================

            // Language icon / flag upload preview
            $('#al-lang-icon, #al-edit-icon').on('change', function() {
                var file = this.files[0];
                if (!file) return;

                var ui = $(this).closest('.al-icon-upload');
                ui.addClass('al-selected').find('> i').attr('class', 'fa-solid fa-check');
                ui.find('.al-icon-upload-text').text(file.name);
            });

            // Platform JSON upload cards
            $('.al-upload-card').on('click', function(e) {
                if ($(e.target).is('input')) return;
                $('#' + $(this).data('target')).trigger('click');
            });

            $('.al-upload-card input[type="file"]').on('click', function(e) {
                e.stopPropagation();
            });

            $('.al-upload-card input[type="file"]').on('change', function() {
                var file = this.files[0];
                var card = $(this).closest('.al-upload-card');
                if (!file) return;

                card.addClass('al-uploaded');
                card.find('.al-upload-status')
                    .addClass('al-uploaded-text')
                    .html('<i class="fa-solid fa-circle-check"></i> ' + $('<span>').text(file.name).html());
                card.find('.al-upload-action').html('<i class="fa-solid fa-check"></i>');
            });

            // Sample JSON dropdown
            $('#al-sample-dropdown-btn').on('click', function(e) {
                e.stopPropagation();
                $('#al-sample-dropdown-menu').toggleClass('al-open');
            });

            $('#al-sample-dropdown-menu .al-dropdown-item').on('click', function() {
                $('#al-sample-dropdown-menu').removeClass('al-open');
            });

            $(document).on('click', function() {
                $('#al-sample-dropdown-menu').removeClass('al-open');
            });

            // Reset the Add Language form UI states
            function resetAddFormUI() {
                $('#al-icon-file-name').text($('#al-icon-file-name').data('default'));
                $('#al-icon-upload-ui').removeClass('al-selected').find('> i').attr('class', 'fa-solid fa-upload');

                $('#al-language-form .al-upload-card').each(function() {
                    $(this).removeClass('al-uploaded');
                    $(this).find('.al-upload-status')
                        .removeClass('al-uploaded-text')
                        .text($(this).find('.al-upload-status').data('default'));
                    $(this).find('.al-upload-action').html('<i class="fa-solid fa-upload"></i>');
                });
            }

            $('#al-reset-btn').on('click', resetAddFormUI);

            // Store default texts so Reset can restore them
            $('#al-icon-file-name').data('default', $('#al-icon-file-name').text());
            $('#al-edit-icon-file-name').data('default', $('#al-edit-icon-file-name').text());
            $('#al-language-form .al-upload-status').each(function() {
                $(this).data('default', $(this).text());
            });

            // Called by common.js after a successful create / update request.
            // The bootstrap table refreshes automatically; only the custom upload UI needs a reset.
            window.formSuccessFunction = function() {
                resetAddFormUI();
            };

            // =====================================================
            // Edit modal file rows
            // =====================================================

            // Replace / Upload Now buttons trigger the hidden file inputs
            $(document).on('click', '.al-edit-replace-btn', function() {
                $('#' + $(this).data('target')).trigger('click');
            });

            $('.al-edit-file-input').on('change', function() {
                var file = this.files[0];
                if (!file) return;

                var type = $(this).data('type');
                $('#al-edit-status-' + type)
                    .removeClass('al-file-status-done al-file-status-missing')
                    .addClass('al-file-status-pending')
                    .html('<i class="fa-solid fa-circle-check"></i> ' + $('<span>').text(file.name).html());
                $('#al-edit-row-' + type).removeClass('al-file-row-missing');
            });
        });
    </script>
@endsection
