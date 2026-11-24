@extends('layouts.master')

@section('title')
    {{ __('general_settings') }}
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
                <div class="card">
                    <div class="card-body">

                        {{-- Tab navigation --}}
                        <ul class="nav nav-tabs system-settings-tabs" id="systemSettingsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="basic-info-tab" data-bs-toggle="tab"
                                    data-bs-target="#basic-info" type="button" role="tab" aria-controls="basic-info"
                                    aria-selected="true">
                                    <i class="mdi mdi-information-outline me-1"></i>{{ __('basic_info') }}
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="branding-tab" data-bs-toggle="tab" data-bs-target="#branding"
                                    type="button" role="tab" aria-controls="branding" aria-selected="false">
                                    <i class="mdi mdi-palette-outline me-1"></i>{{ __('branding_assets') }}
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security"
                                    type="button" role="tab" aria-controls="security" aria-selected="false">
                                    <i class="mdi mdi-server-security me-1"></i>{{ __('security_server') }}
                                </button>
                            </li>
                        </ul>

                        {{-- Tab content --}}
                        <div class="tab-content pt-4">

                            {{-- TAB 1 : Basic Info --}}
                            <div class="tab-pane fade show active" id="basic-info" role="tabpanel"
                                aria-labelledby="basic-info-tab">
                                <form id="form-basic-settings" class="create-form-without-reset"
                                    data-success-function="formSuccessFunction"
                                    action="{{ route('system-settings.general.update') }}" method="POST"
                                    novalidate="novalidate" enctype="multipart/form-data">
                                    @csrf
                                    @include('settings.forms.system-settings-basic-form')
                                    <div class="mt-4 border-top pt-3 text-right">
                                        <input class="btn btn-theme" type="submit" value="{{ __('submit') }}">
                                    </div>
                                </form>
                            </div>

                            {{-- TAB 2 : Branding Assets --}}
                            <div class="tab-pane fade" id="branding" role="tabpanel" aria-labelledby="branding-tab">
                                <form id="form-branding-settings" class="create-form-without-reset"
                                    data-success-function="formSuccessFunction"
                                    action="{{ route('system-settings.branding.update') }}" method="POST"
                                    novalidate="novalidate" enctype="multipart/form-data">
                                    @csrf
                                    @include('settings.forms.system-settings-branding-form')
                                    <div class="mt-4 border-top pt-3 text-right">
                                        <input class="btn btn-theme" type="submit" value="{{ __('submit') }}">
                                    </div>
                                </form>
                            </div>

                            {{-- TAB 3 : Security & Server --}}
                            <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                                <form id="form-security-settings" class="create-form-without-reset"
                                    data-success-function="formSuccessFunction"
                                    action="{{ route('system-settings.security.update') }}" method="POST"
                                    novalidate="novalidate" enctype="multipart/form-data">
                                    @csrf
                                    @include('settings.forms.system-settings-security-form')
                                    <div class="mt-4 border-top pt-3 text-right">
                                        <input class="btn btn-theme" type="submit" value="{{ __('submit') }}">
                                    </div>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        $(document).ready(function() {
            // Restore last active tab after reload
            var activeTab = sessionStorage.getItem('systemSettingsActiveTab');
            if (activeTab && document.querySelector('[data-bs-target="' + activeTab + '"]')) {
                var triggerEl = document.querySelector('[data-bs-target="' + activeTab + '"]');
                bootstrap.Tab.getOrCreateInstance(triggerEl).show();
            }
            document.querySelectorAll('#systemSettingsTabs button[data-bs-toggle="tab"]').forEach(function(btn) {
                btn.addEventListener('shown.bs.tab', function(e) {
                    sessionStorage.setItem('systemSettingsActiveTab', e.target.getAttribute(
                        'data-bs-target'));
                });
            });

            // Policy toggle cards : sync hidden value + highlight from checkbox state
            function syncPolicyCard(checkbox) {
                var $cb = $(checkbox);
                var key = $cb.data('key');
                var isOn = $cb.is(':checked');
                $('#txt_' + key).val(isOn ? 1 : 0);
                $cb.closest('.policy-card').toggleClass('active', isOn);
            }

            // Initialize each policy card from its server-rendered checked state
            $('.policy-toggle').each(function() {
                syncPolicyCard(this);
            });

            // Whole card clickable -> toggle the switch
            $(document).on('click', '.policy-card', function() {
                var checkbox = $(this).find('.policy-toggle')[0];
                checkbox.checked = !checkbox.checked;
                syncPolicyCard(checkbox);
            });

            $(document).on('change', '#file_upload_size_limit', function() {
                $('#txt_file_upload_size_limit').val($('#file_upload_size_limit').val());
            });

            // Image preview functionality
            $('#favicon-input').on('change', function() {
                previewImage(this, '#favicon-preview');
            });

            $('#horizontal-logo-input').on('change', function() {
                previewImage(this, '#horizontal-logo-preview');
            });

            $('#vertical-logo-input').on('change', function() {
                previewImage(this, '#vertical-logo-preview');
            });

            $('#login-page-logo-input').on('change', function() {
                previewImage(this, '#login-page-logo-preview');
            });

            $('#student-web-background-image-input').on('change', function() {
                previewImage(this, '#student-web-background-image-preview');
            });

            function previewImage(input, previewSelector) {
                if (input.files && input.files[0]) {
                    var file = input.files[0];

                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $(previewSelector).attr('src', e.target.result);

                        // Update the file name in the input field
                        $(input).closest('.form-group').find('.file-upload-info').val(file.name);
                    }
                    reader.readAsDataURL(file);
                }
            }
        });

        function formSuccessFunction(response) {
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        }
    </script>
@endsection
