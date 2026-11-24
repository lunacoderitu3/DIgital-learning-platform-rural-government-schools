{{-- System Settings :: Branding Assets Tab --}}

<div class="row g-3">
    {{-- Theme accent color --}}
    <div class="form-group col-md-6 col-lg-4 col-sm-12">
        <label for="theme_color">{{ __('color') }}</label>
        <input name="theme_color" id="theme_color" value="{{ $settings['theme_color'] ?? '' }}" type="text" required
            placeholder="{{ __('color') }}" class="color-picker form-control" />
        <small class="form-text text-muted">{{ __('Controls highlight rings, action buttons, and active tabs') }}</small>
    </div>
</div>
<div class="row g-3">

    {{-- Favicon --}}
    <div class="form-group col-md-6 col-lg-4 col-sm-12">
        <label for="favicon">{{ __('favicon') }} <span class="text-danger">*</span>
            <small class="text-muted">(32x32 pixels)</small></label>
        <input type="file" name="favicon" id="favicon-input" class="file-upload-default" accept="image/*" />
        <div class="input-group">
            <input type="text" id="favicon" class="form-control file-upload-info" disabled=""
                placeholder="{{ __('favicon') }}" />
            <span class="input-group-append">
                <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
            </span>
        </div>
        <div class="mt-2">
            <img id="favicon-preview" height="32px" width="32px"
                src='{{ !empty($settings['favicon']) ? url($settings['favicon']) : asset('assets/no_image_available.jpg') }}'
                alt="Favicon" class="img-thumbnail">
        </div>
    </div>

    {{-- Horizontal logo --}}
    <div class="form-group col-md-6 col-lg-4 col-sm-12">
        <label for="horizontal_logo">{{ __('horizontal_logo') }} <span class="text-danger">*</span>
            <small class="text-muted">(250x50 pixels)</small></label>
        <input type="file" name="horizontal_logo" id="horizontal-logo-input" class="file-upload-default"
            accept="image/*" />
        <div class="input-group">
            <input type="text" id="horizontal_logo" class="form-control file-upload-info" disabled=""
                placeholder="{{ __('horizontal_logo') }}" />
            <span class="input-group-append">
                <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
            </span>
        </div>
        <div class="mt-2">
            <img id="horizontal-logo-preview" height="50px" width="250px"
                src='{{ !empty($settings['horizontal_logo']) ? url($settings['horizontal_logo']) : asset('assets/no_image_available.jpg') }}'
                alt="Horizontal Logo" class="img-thumbnail">
        </div>
    </div>

    {{-- Vertical logo --}}
    <div class="form-group col-md-6 col-lg-4 col-sm-12">
        <label for="vertical_logo">{{ __('vertical_logo') }} <span class="text-danger">*</span>
            <small class="text-muted">(100x100 pixels)</small></label>
        <input type="file" name="vertical_logo" id="vertical-logo-input" class="file-upload-default"
            accept="image/*" />
        <div class="input-group">
            <input type="text" class="form-control file-upload-info" id="vertical_logo" disabled=""
                placeholder="{{ __('vertical_logo') }}" />
            <span class="input-group-append">
                <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
            </span>
        </div>
        <div class="mt-2">
            <img id="vertical-logo-preview" height="100px" width="100px"
                src='{{ !empty($settings['vertical_logo']) ? url($settings['vertical_logo']) : asset('assets/no_image_available.jpg') }}'
                alt="Vertical Logo" class="img-thumbnail">
        </div>
    </div>

    {{-- Login page image --}}
    <div class="form-group col-md-6 col-lg-4 col-sm-12">
        <label for="login_page_logo">{{ __('login_page_image') }} <span class="text-danger">*</span>
            <small class="text-muted">(Recommended size: 920x1080 pixels)</small></label>
        <input type="file" name="login_page_logo" id="login-page-logo-input" class="file-upload-default"
            accept="image/*" />
        <div class="input-group">
            <input type="text" class="form-control file-upload-info" id="login_page_logo" disabled=""
                placeholder="{{ __('login_page_logo') }}" />
            <span class="input-group-append">
                <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
            </span>
        </div>
        <div class="mt-2">
            <img id="login-page-logo-preview" height="100px" width="100px"
                src='{{ !empty($settings['login_page_logo']) ? url($settings['login_page_logo']) : asset('assets/no_image_available.jpg') }}'
                alt="Login Page Logo" class="img-thumbnail">
        </div>
    </div>

    {{-- Student web background image --}}
    <div class="form-group col-md-6 col-lg-4 col-sm-12">
        <label for="student_web_background_image">{{ __('student_login_page_image') }}
            <small class="text-muted">(Recommended size: 1920x1080 pixels)</small></label>
        <input type="file" name="student_web_background_image" id="student-web-background-image-input"
            class="file-upload-default" accept="image/*" />
        <div class="input-group">
            <input type="text" class="form-control file-upload-info" id="student_web_background_image"
                disabled="" placeholder="{{ __('student_web_background_image') }}" />
            <span class="input-group-append">
                <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
            </span>
        </div>
        <div class="mt-2">
            <img id="student-web-background-image-preview" height="100px" width="100px"
                src='{{ !empty($settings['student_web_background_image']) ? url($settings['student_web_background_image']) : asset('assets/no_image_available.jpg') }}'
                alt="Student Web Background Image" class="img-thumbnail">
        </div>
    </div>
</div>

{{-- End Branding Assets Tab --}}
