<div id="preview-student-canvas" class="id-card-canvas" style="display: flex;">
    <div class="id-card-bg-overlay"></div>
    <div class="id-card-content">
        <div class="id-card-header">
            <div class="school-logo">
                <img class="logo"
                    src="{{ !empty($settings['vertical_logo']) ? url($settings['vertical_logo']) : asset('assets/no_image_available.jpg') }}"
                    alt="Logo">
            </div>
            <div class="school-name text-right">{{ $settings['school_name'] ?? ($systemSettings['school_name'] ?? '') }}
            </div>
        </div>

        <div class="id-card-title">{{ __('student_id_card') }}</div>

        <div class="id-card-body">
            <div class="id-card-profile round">
                <img src="{{ asset('assets/images/dummy_profile.png') }}" alt="Profile"
                    onerror="this.src='https://ui-avatars.com/api/?name=Student&background=random'">
            </div>

            <div class="id-card-fields">
                <div class="id-card-field preview-field-student_name" style="display: none;">
                    <div class="id-card-field-label">{{ __('student_name') }}:</div>
                    <div class="id-card-field-value">John Doe</div>
                </div>
                <div class="id-card-field preview-field-class_section" style="display: none;">
                    <div class="id-card-field-label">{{ __('class_section') }}:</div>
                    <div class="id-card-field-value">10 - A</div>
                </div>
                <div class="id-card-field preview-field-roll_no" style="display: none;">
                    <div class="id-card-field-label">{{ __('roll_no') }}:</div>
                    <div class="id-card-field-value">12345</div>
                </div>
                <div class="id-card-field preview-field-dob" style="display: none;">
                    <div class="id-card-field-label">{{ __('dob') }}:</div>
                    <div class="id-card-field-value">01-01-2010</div>
                </div>
                <div class="id-card-field preview-field-gender" style="display: none;">
                    <div class="id-card-field-label">{{ __('gender') }}:</div>
                    <div class="id-card-field-value">Male</div>
                </div>
                <div class="id-card-field preview-field-session_year" style="display: none;">
                    <div class="id-card-field-label">{{ __('session_year') }}:</div>
                    <div class="id-card-field-value">2023-2024</div>
                </div>
                <div class="id-card-field preview-field-guardian_name" style="display: none;">
                    <div class="id-card-field-label">{{ __('guardian_name') }}:</div>
                    <div class="id-card-field-value">Richard Doe</div>
                </div>
                <div class="id-card-field preview-field-guardian_contact" style="display: none;">
                    <div class="id-card-field-label">{{ __('guardian_contact') }}:</div>
                    <div class="id-card-field-value">+1 234 567 890</div>
                </div>

                @foreach ($formFields as $field)
                    @if ($field->user_type == 1)
                        <div class="id-card-field preview-field-extra-{{ $field->id }}" style="display: none;">
                            <div class="id-card-field-label">{{ $field->name }}:</div>
                            <div class="id-card-field-value">Sample {{ $field->name }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="id-card-signature">
            <img src="{{ !empty($settings['signature']) ? $settings['signature'] : '' }}" alt="Signature"
                style="{{ !empty($settings['signature']) ? '' : 'display: none;' }}">
            <div style="margin-top: 2px;">Principal Signature</div>
        </div>

        <div class="id-card-footer">
            @php
                $defaultSessionYear = app(App\Services\CachingService::class)->getDefaultSessionYear();
                $validUntil = $defaultSessionYear ? date('F d, Y', strtotime($defaultSessionYear->end_date)) : '';
            @endphp
            @if($validUntil)
                {{ __('Valid until') }}: {{ $validUntil }}
            @else
                {{ $systemSettings['school_address'] ?? 'School Address goes here' }}
            @endif
        </div>
    </div>
</div>
