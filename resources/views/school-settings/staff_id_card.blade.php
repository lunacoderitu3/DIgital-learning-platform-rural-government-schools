<!-- Layout & Style Settings -->
<div class="setting-section">
    <div class="setting-section-title">{{ __('layout_&_style') }}</div>

    <div class="form-group">
        <label class="text-muted" style="font-size: 11px; text-transform: uppercase;">{{ __('layout_type') }}</label>
        <div class="segmented-control">
            <input type="radio" name="staff_layout_type" id="staff_layout_vertical" value="vertical"
                @if (!isset($settings['staff_layout_type']) || $settings['staff_layout_type'] == 'vertical') checked @endif>
            <label for="staff_layout_vertical"><i class="fa fa-mobile-alt mr-1"></i> {{ __('vertical') }}</label>

            <input type="radio" name="staff_layout_type" id="staff_layout_horizontal" value="horizontal"
                @if (isset($settings['staff_layout_type']) && $settings['staff_layout_type'] == 'horizontal') checked @endif>
            <label for="staff_layout_horizontal"><i class="fa fa-mobile-alt fa-rotate-270 mr-1"></i>
                {{ __('horizontal') }}</label>
        </div>
    </div>

    <div class="form-group">
        <label class="text-muted"
            style="font-size: 11px; text-transform: uppercase;">{{ __('profile_image_style') }}</label>
        <div class="segmented-control">
            <input type="radio" name="staff_profile_image_style" id="staff_profile_round" value="round"
                @if (!isset($settings['staff_profile_image_style']) || $settings['staff_profile_image_style'] == 'round') checked @endif>
            <label for="staff_profile_round"><i class="fa fa-circle mr-1"></i> {{ __('round') }}</label>

            <input type="radio" name="staff_profile_image_style" id="staff_profile_square" value="square"
                @if (isset($settings['staff_profile_image_style']) &&
                        ($settings['staff_profile_image_style'] == 'square' || $settings['staff_profile_image_style'] == 'squre')) checked @endif>
            <label for="staff_profile_square"><i class="fa fa-square mr-1"></i> {{ __('square') }}</label>
        </div>
    </div>

    {{-- <div class="form-group">
        <label class="text-muted" style="font-size: 11px; text-transform: uppercase;">{{ __('Border Radius') }} (px)</label>
        <input type="range" name="staff_border_radius" min="0" max="30" value="{{ $settings['staff_border_radius'] ?? 0 }}">
    </div> --}}
</div>

<hr>

<!-- Color Settings -->
<div class="setting-section">
    <div class="setting-section-title">{{ __('colors') }}</div>

    <div class="row">
        <div class="col-6">
            <div class="color-picker-group">
                <label for="staff_header_color">{{ __('header_color') }}</label>
                <input name="staff_header_color" id="staff_header_color"
                    value="{{ $settings['staff_header_color'] ?? '#E26565' }}" type="color" required />
            </div>
        </div>
        <div class="col-6">
            <div class="color-picker-group">
                <label for="staff_footer_color">{{ __('footer_color') }}</label>
                <input name="staff_footer_color" id="staff_footer_color"
                    value="{{ $settings['staff_footer_color'] ?? '#90B1E0' }}" type="color" required />
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-6">
            <div class="color-picker-group">
                <label for="staff_header_footer_text_color">{{ __('header_footer_text') }}</label>
                <input name="staff_header_footer_text_color" id="staff_header_footer_text_color"
                    value="{{ $settings['staff_header_footer_text_color'] ?? '#345893' }}" type="color" required />
            </div>
        </div>
        <div class="col-6">
            <div class="color-picker-group">
                <label for="staff_text_color">{{ __('primary_text') }}</label>
                <input name="staff_text_color" id="staff_text_color"
                    value="{{ $settings['staff_text_color'] ?? '#333333' }}" type="color" />
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
        <select name="staff_font_family" class="form-control form-control-sm">
            <option value="Inter" @if (($settings['staff_font_family'] ?? '') == 'Inter') selected @endif>Inter</option>
            <option value="Poppins" @if (($settings['staff_font_family'] ?? '') == 'Poppins') selected @endif>Poppins</option>
            <option value="Roboto" @if (($settings['staff_font_family'] ?? '') == 'Roboto') selected @endif>Roboto</option>
            <option value="Open Sans" @if (($settings['staff_font_family'] ?? '') == 'Open Sans') selected @endif>Open Sans</option>
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
            <input name="staff_page_width" id="staff_page_width" value="{{ $settings['staff_page_width'] ?? '110' }}"
                type="number" required class="form-control form-control-sm" />
        </div>
        <div class="form-group col-6">
            <label class="text-muted" style="font-size: 11px; text-transform: uppercase;">{{ __('height') }}</label>
            <input name="staff_page_height" id="staff_page_height"
                value="{{ $settings['staff_page_height'] ?? '205' }}" type="number" required
                class="form-control form-control-sm" />
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
        <input type="file" name="staff_background_image" accept="image/jpg,image/png,image/jpeg,image/svg"
            class="file-upload-default" />
        <div class="input-group">
            <input type="text" class="form-control form-control-sm file-upload-info" disabled
                placeholder="{{ __('Upload Image') }}" />
            <span class="input-group-append">
                <button class="file-upload-browse btn btn-theme btn-sm" type="button">{{ __('browse') }}</button>
            </span>
        </div>
        @if (!empty($settings['staff_background_image']))
            <input type="hidden" name="existing_staff_background_image"
                value="{{ $settings['staff_background_image'] }}">
            <div class="mt-2 d-flex align-items-center">
                <span class="text-success small"><i class="fa fa-check-circle mr-1"></i>
                    {{ __('image_uploaded') }}</span>
                <a href="#" data-type="staff_background" class="text-danger ml-auto small id-card-settings"><i
                        class="fa fa-trash"></i> </a>
            </div>
        @endif
    </div>

    <div class="form-group mt-3">
        <label class="text-muted d-flex justify-content-between" style="font-size: 11px; text-transform: uppercase;">
            <span>{{ __('opacity') }}</span>
            <span id="staff_bg_opacity_val">{{ $settings['staff_bg_opacity'] ?? 20 }}%</span>
        </label>
        <input type="range" name="staff_bg_opacity" min="0" max="20"
            value="{{ $settings['staff_bg_opacity'] ?? 20 }}"
            oninput="document.getElementById('staff_bg_opacity_val').innerText = this.value + '%'">
    </div>
</div>

<hr>



<!-- Fields Selection -->
<div class="setting-section">
    <div class="setting-section-title d-flex justify-content-between">
        <span>{{ __('select_fields_(staff)') }}</span>
        <span class="text-muted"
            style="font-size: 10px; font-weight: normal;">{{ __('click_to_toggle_visibility') }}</span>
    </div>

    @php
        $staffSelectedFields = $settings['staff_id_card_fields'] ?? [];
        $staffExtraSelectedFields = $settings['extra_staff_id_card_fields'] ?? [];
    @endphp

    <div class="feature-chips">
        <div class="row">
            <div class="col-md-6 col-sm-12 mb-2">
                <label class="feature-chip w-100">
                    <input type="checkbox" name="staff_id_card_fields[]" value="name"
                        @if (in_array('name', $staffSelectedFields)) checked @endif>
                    <i class="fa fa-check mr-1"></i>{{ __('name') }}
                </label>
            </div>
            <div class="col-md-6 col-sm-12 mb-2">
                <label class="feature-chip w-100">
                    <input type="checkbox" name="staff_id_card_fields[]" value="role"
                        @if (in_array('role', $staffSelectedFields)) checked @endif>
                    <i class="fa fa-check mr-1"></i>{{ __('role') }}
                </label>
            </div>
            <div class="col-md-6 col-sm-12 mb-2">
                <label class="feature-chip w-100">
                    <input type="checkbox" name="staff_id_card_fields[]" value="contact"
                        @if (in_array('contact', $staffSelectedFields)) checked @endif>
                    <i class="fa fa-check mr-1"></i>{{ __('contact') }}
                </label>
            </div>
            <div class="col-md-6 col-sm-12 mb-2">
                <label class="feature-chip w-100">
                    <input type="checkbox" name="staff_id_card_fields[]" value="email"
                        @if (in_array('email', $staffSelectedFields)) checked @endif>
                    <i class="fa fa-check mr-1"></i>{{ __('email') }}
                </label>
            </div>
            <div class="col-md-6 col-sm-12 mb-2">
                <label class="feature-chip w-100">
                    <input type="checkbox" name="staff_id_card_fields[]" value="qualification"
                        @if (in_array('qualification', $staffSelectedFields)) checked @endif>
                    <i class="fa fa-check mr-1"></i>{{ __('qualification') }}
                </label>
            </div>
            <div class="col-md-6 col-sm-12 mb-2">
                <label class="feature-chip w-100">
                    <input type="checkbox" name="staff_id_card_fields[]" value="dob"
                        @if (in_array('dob', $staffSelectedFields)) checked @endif>
                    <i class="fa fa-check mr-1"></i>{{ __('dob') }}
                </label>
            </div>
            <div class="col-md-6 col-sm-12 mb-2">
                <label class="feature-chip w-100">
                    <input type="checkbox" name="staff_id_card_fields[]" value="gender"
                        @if (in_array('gender', $staffSelectedFields)) checked @endif>
                    <i class="fa fa-check mr-1"></i>{{ __('gender') }}
                </label>
            </div>
            <div class="col-md-6 col-sm-12 mb-2">
                <label class="feature-chip w-100">
                    <input type="checkbox" name="staff_id_card_fields[]" value="session_year"
                        @if (in_array('session_year', $staffSelectedFields)) checked @endif>
                    <i class="fa fa-check mr-1"></i>{{ __('session_year') }}
                </label>
            </div>

            {{-- Extra form fields --}}
            @foreach ($formFields as $field)
                @if ($field->user_type == 2)
                    <!-- 2 => Staff -->
                    <div class="col-md-6 col-sm-12 mb-2">
                        <label class="feature-chip w-100" data-usertype="2">
                            <input type="checkbox" name="extra_form_fields[]" value="{{ $field->id }}"
                                @if (in_array($field->id, $staffExtraSelectedFields)) checked @endif>
                            <i class="fa fa-check mr-1"></i>{{ $field->name }}
                        </label>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>
