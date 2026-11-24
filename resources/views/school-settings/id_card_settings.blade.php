@extends('layouts.master')

@section('title')
    {{ __('id_card_setting') }}
@endsection

@section('content')
    <link rel="stylesheet" href="{{ asset('assets/css/custom/id_card_preview.css') }}">

    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('id_card_setting') }}
            </h3>
        </div>

        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body overflow-hidden">
                        <form id="formdata" class="create-form-without-reset" action="{{ url('id-card-settings') }}"
                            method="POST" novalidate="novalidate" enctype="multipart/form-data">
                            @csrf
                            <div class="id-card-builder-container row no-gutters mx-0">
                                <!-- Left Configuration Panel -->
                                <div class="id-card-settings-panel col-lg-4 col-md-5 p-0">
                                    <div class="id-card-settings-header">
                                        <h4 class="mb-1">{{ __('ID Card Settings') }}</h4>
                                        <p class="text-muted mb-0" style="font-size: 12px;">
                                            {{ __('configure_layout_colours_fields') }}
                                        </p>
                                    </div>

                                    <div class="id-card-settings-body">
                                        <!-- Target Audience -->
                                        <div class="setting-section">
                                            <div class="setting-section-title">{{ __('target_audience') }}</div>
                                            <div class="segmented-control">
                                                <input type="radio" name="type" id="type_student" value="Student"
                                                    checked required="required">
                                                <label for="type_student"><i
                                                        class="fa fa-user-graduate mr-2"></i>{{ __('student') }}</label>

                                                <input type="radio" name="type" id="type_staff" value="Staff"
                                                    required="required">
                                                <label for="type_staff"><i
                                                        class="fa fa-user-tie mr-2"></i>{{ __('staff') }}</label>
                                            </div>
                                        </div>

                                        <!-- Dynamic settings container based on type -->
                                        <div id="student-id-card">
                                            @include('school-settings.student_id_card')
                                        </div>

                                        <div id="staff-id-card" style="display: none;">
                                            @include('school-settings.staff_id_card')
                                        </div>
                                    </div>

                                    <div class="id-card-settings-footer">
                                        <button type="submit" class="btn btn-theme btn-sm" id="create-btn"><i
                                                class="fa fa-save mr-1"></i>{{ __('save') }}</button>
                                    </div>
                                </div>

                                <!-- Right Live Preview Area -->
                                <div class="id-card-preview-area col-lg-8 col-md-7 p-0">
                                    <div class="id-card-preview-toolbar">
                                        <div class="live-preview-badge">{{ __('live_preview') }}</div>
                                        <div style="width: 1px; height: 20px; background: #e1e5eb;"></div>
                                        <div id="preview-dimensions" style="color: #6c757d;"></div>
                                        <div style="width: 1px; height: 20px; background: #e1e5eb;"></div>
                                        <button id="zoom-in" title="Zoom In"><i class="fa fa-search-plus"></i></button>
                                        <button id="zoom-reset" title="Reset Zoom"><i class="fa fa-expand"></i></button>
                                        <button id="zoom-out" title="Zoom Out"><i class="fa fa-search-minus"></i></button>
                                    </div>

                                    <div class="id-card-canvas-wrapper" id="id-card-canvas-wrapper">
                                        <!-- Student Canvas -->
                                        @include('school-settings.preview_student_canvas')

                                        <!-- Staff Canvas -->
                                        @include('school-settings.preview_staff_canvas')
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="{{ asset('assets/js/custom/id_card_preview.js') }}"></script>
    <script>
        // No custom color picker needed, using native HTML5 color input
    </script>
@endsection
