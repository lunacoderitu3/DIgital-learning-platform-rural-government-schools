<div id="preview-staff-canvas" class="id-card-canvas" style="display: none;">
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

        <div class="id-card-title">{{ __('staff_id_card') }}</div>

        <div class="id-card-body">
            <div class="id-card-profile round">
                <img src="{{ asset('assets/images/dummy_profile.png') }}" alt="Profile"
                    onerror="this.src='https://ui-avatars.com/api/?name=Staff&background=random'">
            </div>

            <div class="id-card-fields">
                <div class="id-card-field preview-field-name" style="display: none;">
                    <div class="id-card-field-label">{{ __('name') }}:</div>
                    <div class="id-card-field-value">Jane Smith</div>
                </div>
                <div class="id-card-field preview-field-role" style="display: none;">
                    <div class="id-card-field-label">{{ __('role') }}:</div>
                    <div class="id-card-field-value">Teacher</div>
                </div>
                <div class="id-card-field preview-field-contact" style="display: none;">
                    <div class="id-card-field-label">{{ __('contact') }}:</div>
                    <div class="id-card-field-value">+1 987 654 321</div>
                </div>
                <div class="id-card-field preview-field-email" style="display: none;">
                    <div class="id-card-field-label">{{ __('email') }}:</div>
                    <div class="id-card-field-value">jane@example.com</div>
                </div>
                <div class="id-card-field preview-field-qualification" style="display: none;">
                    <div class="id-card-field-label">{{ __('qualification') }}:</div>
                    <div class="id-card-field-value">M.Sc., B.Ed.</div>
                </div>
                <div class="id-card-field preview-field-dob" style="display: none;">
                    <div class="id-card-field-label">{{ __('dob') }}:</div>
                    <div class="id-card-field-value">15-05-1985</div>
                </div>
                <div class="id-card-field preview-field-gender" style="display: none;">
                    <div class="id-card-field-label">{{ __('gender') }}:</div>
                    <div class="id-card-field-value">Female</div>
                </div>
                <div class="id-card-field preview-field-session_year" style="display: none;">
                    <div class="id-card-field-label">{{ __('session_year') }}:</div>
                    <div class="id-card-field-value">2023-2024</div>
                </div>

                @foreach ($formFields as $field)
                    @if ($field->user_type == 2)
                        <div class="id-card-field preview-field-extra-{{ $field->id }}" style="display: none;">
                            <div class="id-card-field-label">{{ $field->name }}:</div>
                            <div class="id-card-field-value">Sample {{ $field->name }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="id-card-signature">
            <img src="{{ !empty($settings['signature']) ? url('storage/' . $settings['signature']) : '' }}"
                alt="Signature" style="{{ !empty($settings['signature']) ? '' : 'display: none;' }}">
            <div style="margin-top: 2px;">Authorized Signature</div>
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
