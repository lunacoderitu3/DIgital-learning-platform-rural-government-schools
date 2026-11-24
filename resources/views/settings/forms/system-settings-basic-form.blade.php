{{-- System Settings :: Basic Info Tab --}}

<div class="row g-3">
    <div class="form-group col-md-6 col-lg-4 col-sm-12">
        <label for="system_name">{{ __('system_name') }} <span class="text-danger">*</span></label>
        <input name="system_name" id="system_name" value="{{ $settings['system_name'] ?? '' }}" type="text" required
            placeholder="{{ __('system_name') }}" class="form-control" />
    </div>

    <div class="form-group col-md-6 col-lg-4 col-sm-12">
        <label for="mobile">{{ __('mobile') }} <span class="text-danger">*</span></label>
        <input name="mobile" id="mobile" value="{{ $settings['mobile'] ?? '' }}" type="number" required
            placeholder="{{ __('mobile') }}" class="form-control" />
    </div>

    <div class="form-group col-md-6 col-lg-4 col-sm-12">
        <label for="tag_line">{{ __('tag_line') }} <span class="text-danger">*</span></label>
        <input name="tag_line" id="tag_line" value="{{ $settings['tag_line'] ?? '' }}" type="text" required
            placeholder="{{ __('tag_line') }}" class="form-control" />
    </div>

    <div class="form-group col-md-6 col-sm-12">
        <label for="School Code Prefix">{{ __('School Code Prefix') }} <span class="text-danger">*</span></label>
        <input name="school_code_prefix" id="school_code_prefix" value="{{ $settings['school_code_prefix'] ?? 'SCH' }}"
            type="text" required placeholder="{{ __('School Code Prefix') }}" class="form-control" />
    </div>

    <div class="form-group col-md-6 col-sm-12">
        <label for="time_zone">{{ __('time_zone') }}</label>
        <select name="time_zone" id="time_zone" required class="form-control custom-search-select" style="width:100%">
            @foreach ($getTimezoneList as $timezone)
                <option value="{{ $timezone[2] }}"
                    {{ isset($settings['time_zone']) && $settings['time_zone'] == $timezone[2] ? 'selected' : '' }}>
                    {{ $timezone[2] . ' - GMT ' . $timezone[1] . ' - ' . $timezone[0] }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group col-md-6 col-sm-12">
        <label for="date_format">{{ __('date_format') }}</label>
        <select name="date_format" id="date_format" required class="form-control custom-search-select">
            @foreach ($getDateFormat as $key => $dateformat)
                <option value="{{ $key }}"
                    {{ isset($settings['date_format']) && $settings['date_format'] == $key ? 'selected' : '' }}>
                    {{ $dateformat }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group col-md-6 col-sm-12">
        <label for="time_format">{{ __('time_format') }}</label>
        <select name="time_format" id="time_format" required class="form-control custom-search-select">
            @foreach ($getTimeFormat as $key => $timeFormat)
                <option value="{{ $key }}"
                    {{ isset($settings['time_format']) && $settings['time_format'] == $key ? 'selected' : '' }}>
                    {{ $timeFormat }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group col-md-6 col-sm-12">
        <label for="hero_description">{{ __('description') }} <span class="text-danger">*</span></label>
        <textarea name="hero_description" id="hero_description" rows="3" required placeholder="{{ __('description') }}"
            class="form-control">{{ $settings['hero_description'] ?? null }}</textarea>
    </div>

    <div class="form-group col-md-6 col-sm-12">
        <label for="address">{{ __('address') }} <span class="text-danger">*</span></label>
        <textarea name="address" id="address" rows="3" required placeholder="{{ __('address') }}" class="form-control">{{ $settings['address'] ?? null }}</textarea>
    </div>
    <!-- <div class="form-group">
    <label>{{ __('default_language') }}</label>
    <select name="default_language" class="form-control">
        @foreach($languages as $language)
            <option value="{{ $language->code }}" {{ (isset($settings['default_language']) && $settings['default_language'] == $language->code) ? 'selected' : '' }}>
                {{ $language->name }}
            </option>
        @endforeach
    </select>
</div> -->
<div class="row g-3">
    <div class="form-group col-md-6 col-lg-4 col-sm-12">
        <label for="default_language">
            {{ __('default_language') }}
        </label>

        <select name="default_language" id="default_language" class="form-control  custom-search-select">
            @foreach($languages as $language)
                <option value="{{ $language->code }}"
                    {{ (isset($settings['default_language']) && $settings['default_language'] == $language->code) ? 'selected' : '' }}>
                    {{ $language->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>
</div>

{{-- End Basic Info Tab --}}
