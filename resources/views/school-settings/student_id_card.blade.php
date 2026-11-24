<!-- Layout & Style Settings -->
<div class="setting-section">
    <div class="setting-section-title">{{ __('layout_&_style') }}</div>

    <div class="form-group">
        <label class="text-muted" style="font-size: 11px; text-transform: uppercase;">{{ __('layout_type') }}</label>
        <div class="segmented-control">
            <input type="radio" name="layout_type" id="layout_vertical" value="vertical"
                @if (!isset($settings['layout_type']) || $settings['layout_type'] == 'vertical') checked @endif>
            <label for="layout_vertical"><i class="fa fa-mobile-alt mr-1"></i> {{ __('vertical') }}</label>

            <input type="radio" name="layout_type" id="layout_horizontal" value="horizontal"
                @if (isset($settings['layout_type']) && $settings['layout_type'] == 'horizontal') checked @endif>
            <label for="layout_horizontal"><i class="fa fa-mobile-alt fa-rotate-270 mr-1"></i>
                {{ __('horizontal') }}</label>
        </div>
    </div>

    <div class="form-group">
        <label class="text-muted"
            style="font-size: 11px; text-transform: uppercase;">{{ __('profile_image_style') }}</label>
        <div class="segmented-control">
            <input type="radio" name="profile_image_style" id="profile_round" value="round"
                @if (!isset($settings['profile_image_style']) || $settings['profile_image_style'] == 'round') checked @endif>
            <label for="profile_round"><i class="fa fa-circle mr-1"></i> {{ __('round') }}</label>

            <input type="radio" name="profile_image_style" id="profile_square" value="square"
                @if (isset($settings['profile_image_style']) &&
                        ($settings['profile_image_style'] == 'square' || $settings['profile_image_style'] == 'squre')) checked @endif>
            <label for="profile_square"><i class="fa fa-square mr-1"></i> {{ __('square') }}</label>
        </div>
    </div>

    {{-- <div class="form-group">
        <label class="text-muted" style="font-size: 11px; text-transform: uppercase;">{{ __('Border Radius') }} (px)</label>
        <input type="range" name="border_radius" min="0" max="30" value="{{ $settings['border_radius'] ?? 0 }}">
    </div> --}}
</div>

<hr>

<!-- Color Settings -->
<div class="setting-section">
    <div class="setting-section-title">{{ __('colors') }}</div>

    <div class="row">
        <div class="col-6">
            <div class="color-picker-group">
                <label for="header_color">{{ __('header_color') }}</label>
                <input name="header_color" id="header_color" value="{{ $settings['header_color'] ?? '#E26565' }}"
                    type="color" required />
            </div>
        </div>
        <div class="col-6">
            <div class="color-picker-group">
                <label for="footer_color">{{ __('footer_color') }}</label>
                <input name="footer_color" id="footer_color" value="{{ $settings['footer_color'] ?? '#90B1E0' }}"
                    type="color" required />
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-6">
            <div class="color-picker-group">
                <label for="header_footer_text_color">{{ __('header_footer_text_color') }}</label>
                <input name="header_footer_text_color" id="header_footer_text_color"
                    value="{{ $settings['header_footer_text_color'] ?? '#345893' }}" type="color" required />
            </div>
        </div>
        <div class="col-6">
            <div class="color-picker-group">
                <label for="text_color">{{ __('primary_text') }}</label>
                <input name="text_color" id="text_color" value="{{ $settings['text_color'] ?? '#333333' }}"
                    type="color" />
            </div>
        </div>
    </div>
</div>

<hr>

<!-- Typography Settings -->
{{-- <div class="setting-section">
    <div class="setting-section-title">{{ __('Typography') }}</div>
    <div class="form-group">
        <label class="text-muted" style="font-size: 11px; text-transform: uppercase;">{{ __('Font Family') }}</label>
        <select name="font_family" class="form-control form-control-sm">
            <option value="Inter" @if (($settings['font_family'] ?? '') == 'Inter') selected @endif>Inter</option>
            <option value="Poppins" @if (($settings['font_family'] ?? '') == 'Poppins') selected @endif>Poppins</option>
            <option value="Roboto" @if (($settings['font_family'] ?? '') == 'Roboto') selected @endif>Roboto</option>
            <option value="Open Sans" @if (($settings['font_family'] ?? '') == 'Open Sans') selected @endif>Open Sans</option>
        </select>
    </div>
</div> --}}

<hr>

<!-- Dimensions Settings -->
<div class="setting-section">
    <div class="setting-section-title">{{ __('dimensions_(mm)') }}</div>
    <div class="row">
        <div class="form-group col-6">
            <label class="text-muted" style="font-size: 11px; text-transform: uppercase;">{{ __('width') }}</label>
            <input name="page_width" id="page_width" value="{{ $settings['page_width'] ?? '110' }}" type="number"
                required class="form-control form-control-sm" />
        </div>
        <div class="form-group col-6">
            <label class="text-muted" style="font-size: 11px; text-transform: uppercase;">{{ __('height') }}</label>
            <input name="page_height" id="page_height" value="{{ $settings['page_height'] ?? '205' }}" type="number"
                required class="form-control form-control-sm" />
        </div>
    </div>
</div>

<hr>

<!-- Background Settings -->
<div class="setting-section">
    <div class="setting-section-title">{{ __('background') }}</div>

    <div class="form-group">
        <label class="text-muted"
            style="font-size: 11px; text-transform: uppercase;">{{ __('background_image') }}</label>
        <input type="file" name="background_image" accept="image/jpg,image/png,image/jpeg,image/svg"
            class="file-upload-default" />
        <div class="input-group">
            <input type="text" class="form-control form-control-sm file-upload-info" disabled
                placeholder="{{ __('Upload Image') }}" />
            <span class="input-group-append">
                <button class="file-upload-browse btn btn-theme btn-sm" type="button">{{ __('browse') }}</button>
            </span>
        </div>
        @if (!empty($settings['background_image']))
            <input type="hidden" name="existing_background_image" value="{{ $settings['background_image'] }}">
            <div class="mt-2 d-flex align-items-center">
                <span class="text-success small"><i class="fa fa-check-circle mr-1"></i>
                    {{ __('image_uploaded') }}</span>
                <a href="#" data-type="background" class="text-danger ml-auto small id-card-settings"><i
                        class="fa fa-trash"></i> </a>
            </div>
        @endif
    </div>

    <div class="form-group mt-3">
        <label class="text-muted d-flex justify-content-between" style="font-size: 11px; text-transform: uppercase;">
            <span>{{ __('opacity') }}</span>
            <span id="bg_opacity_val">{{ $settings['bg_opacity'] ?? 20 }}%</span>
        </label>
        <input type="range" name="bg_opacity" min="0" max="20"
            value="{{ $settings['bg_opacity'] ?? 20 }}"
            oninput="document.getElementById('bg_opacity_val').innerText = this.value + '%'">
    </div>
</div>

<hr>



<!-- Fields Selection -->
<div class="setting-section">
    <div class="setting-section-title d-flex justify-content-between">
        <span>{{ __('select_fields_student') }}</span>
        <span class="text-muted"
            style="font-size: 10px; font-weight: normal;">{{ __('click_to_toggle_visibility') }}</span>
    </div>

    @php
        $selectedFields = $settings['student_id_card_fields'] ?? [];
        $extraSelectedFields = $settings['extra_student_id_card_fields'] ?? [];
    @endphp

    <div class="feature-chips">
        <div class="row">
            <div class="col-md-6 col-sm-12 mb-2">
                <label class="feature-chip w-100">
                    <input type="checkbox" name="student_id_card_fields[]" value="student_name"
                        @if (in_array('student_name', $selectedFields)) checked @endif>
                    <i class="fa fa-check mr-1"></i>{{ __('student_name') }}
                </label>
            </div>
            <div class="col-md-6 col-sm-12 mb-2">
                <label class="feature-chip w-100">
                    <input type="checkbox" name="student_id_card_fields[]" value="class_section"
                        @if (in_array('class_section', $selectedFields)) checked @endif>
                    <i class="fa fa-check mr-1"></i>{{ __('class_section') }}
                </label>
            </div>
            <div class="col-md-6 col-sm-12 mb-2">
                <label class="feature-chip w-100">
                    <input type="checkbox" name="student_id_card_fields[]" value="roll_no"
                        @if (in_array('roll_no', $selectedFields)) checked @endif>
                    <i class="fa fa-check mr-1"></i>{{ __('roll_no') }}
                </label>
            </div>
            <div class="col-md-6 col-sm-12 mb-2">
                <label class="feature-chip w-100">
                    <input type="checkbox" name="student_id_card_fields[]" value="dob"
                        @if (in_array('dob', $selectedFields)) checked @endif>
                    <i class="fa fa-check mr-1"></i>{{ __('dob') }}
                </label>
            </div>
            <div class="col-md-6 col-sm-12 mb-2">
                <label class="feature-chip w-100">
                    <input type="checkbox" name="student_id_card_fields[]" value="gender"
                        @if (in_array('gender', $selectedFields)) checked @endif>
                    <i class="fa fa-check mr-1"></i>{{ __('gender') }}
                </label>
            </div>
            <div class="col-md-6 col-sm-12 mb-2">
                <label class="feature-chip w-100">
                    <input type="checkbox" name="student_id_card_fields[]" value="session_year"
                        @if (in_array('session_year', $selectedFields)) checked @endif>
                    <i class="fa fa-check mr-1"></i>{{ __('session_year') }}
                </label>
            </div>
            <div class="col-md-6 col-sm-12 mb-2">
                <label class="feature-chip w-100">
                    <input type="checkbox" name="student_id_card_fields[]" value="guardian_name"
                        @if (in_array('guardian_name', $selectedFields)) checked @endif>
                    <i class="fa fa-check mr-1"></i>{{ __('guardian_name') }}
                </label>
            </div>
            <div class="col-md-6 col-sm-12 mb-2">
                <label class="feature-chip w-100">
                    <input type="checkbox" name="student_id_card_fields[]" value="guardian_contact"
                        @if (in_array('guardian_contact', $selectedFields)) checked @endif>
                    <i class="fa fa-check mr-1"></i>{{ __('guardian_contact') }}
                </label>
            </div>

            {{-- Extra form fields --}}
            @foreach ($formFields as $field)
                @if ($field->user_type == 1)
                    <div class="col-md-6 col-sm-12 mb-2">
                        <label class="feature-chip w-100" data-usertype="1">
                            <input type="checkbox" name="extra_form_fields[]" value="{{ $field->id }}"
                                @if (in_array($field->id, $extraSelectedFields)) checked @endif>
                            <i class="fa fa-check mr-1"></i>{{ $field->name }}
                        </label>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>
