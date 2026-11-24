@php
    // Single reusable Package form — used by both Create and Edit.
    $isEditMode = $isEditMode ?? false;

    // Billing type + which charge block is visible on first paint.
    $typeValue = $isEditMode ? (int) $package->type : 1; // Create defaults to Postpaid (1)
    $showPostpaid = $typeValue === 1;

    // Features already attached to the package (Edit only).
    $packageFeatureIds = $isEditMode ? $package->package_feature->pluck('feature_id')->toArray() : [];

    // ----- Features grouping (UI only — the features table has no category column) -----
    $pkgFeatures = collect($features);
    $coreFeatures = $pkgFeatures->filter(fn($f) => $f->is_default == 1)->values();
    $selectableFeatures = $pkgFeatures->filter(fn($f) => $f->is_default != 1)->values();

    $pkgFeatureGroups = [
        'Academics & Learning' => [
            'Announcement Management',
            'Assignment Management',
            'Exam Management',
            'Lesson Management',
            'Student Leave Management',
            'Timetable Management',
        ],
        'Attendance & Operations' => [
            'Attendance Management',
            'Holiday Management',
            'Staff Attendance Management',
            'Transportation Module',
        ],
        'Finance & HR' => ['Finance Management', 'Fees Management', 'Staff Management', 'Staff Leave Management'],
        'Website & Communication' => [
            'Website Management',
            'Slider Management',
            'School Gallery Management',
            'Chat Module',
            'ID Card - Certificate Generation',
            'WhatsApp Messaging',
        ],
    ];

    $pkgGroupedFeatures = [];
    $pkgAssignedIds = [];
    foreach ($pkgFeatureGroups as $pkgGroupTitle => $pkgGroupNames) {
        $pkgGroupItems = $selectableFeatures->filter(fn($f) => in_array($f->name, $pkgGroupNames))->values();
        if ($pkgGroupItems->isNotEmpty()) {
            $pkgGroupedFeatures[$pkgGroupTitle] = $pkgGroupItems;
            $pkgAssignedIds = array_merge($pkgAssignedIds, $pkgGroupItems->pluck('id')->all());
        }
    }
    $pkgOtherFeatures = $selectableFeatures->reject(fn($f) => in_array($f->id, $pkgAssignedIds))->values();
    if ($pkgOtherFeatures->isNotEmpty()) {
        $pkgGroupedFeatures['Other Modules'] = $pkgOtherFeatures;
    }
@endphp

<div class="content-wrapper pkg-create">

    {{-- Page heading + back --}}
    <div class="d-flex justify-content-between align-items-start flex-wrap mb-4">
        <div>
            <h3 class="font-weight-bold mb-1">{{ $isEditMode ? __('edit_package') : __('create_package') }}</h3>
            <p class="text-muted mb-0">{{ __('Define pricing, limits, and included features for a new tier.') }}</p>
        </div>
        <a href="{{ route('package.index') }}" class="btn btn-light border d-inline-flex align-items-center">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                <path d="m12 19-7-7 7-7" />
                <path d="M19 12H5" />
            </svg>
            {{ __('back') }}
        </a>
    </div>

    <div class="card shadow-sm border">
        @if ($isEditMode)
            {!! Form::model($package, [
                'route' => ['package.update', $package->id],
                'method' => 'post',
                'class' => 'edit-form',
                'novalidate' => 'novalidate',
                'data-success-function' => 'formSuccessFunction',
            ]) !!}
        @else
            {!! Form::open([
                'route' => 'package.store',
                'method' => 'post',
                'class' => 'create-form',
                'novalidate' => 'novalidate',
            ]) !!}
        @endif

        {{-- ===== Section: Basic Details ===== --}}
        <div class="p-4 border-bottom">
            <div class="d-flex align-items-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="text-primary mr-2">
                    <path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z" />
                    <path d="M7 7h.01" />
                </svg>
                <h5 class="mb-0 font-weight-bold">{{ __('package_information') }}</h5>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label class="font-weight-bold text-secondary small">{{ __('name') }} <span
                            class="text-danger">*</span></label>
                    {!! Form::text('name', null, ['required', 'class' => 'form-control', 'placeholder' => __('package_name')]) !!}
                </div>
                <div class="form-group col-md-6">
                    <label class="font-weight-bold text-secondary small">{{ __('tagline') }}</label>
                    {!! Form::text('tagline', null, ['class' => 'form-control', 'placeholder' => __('tagline')]) !!}
                </div>
                <div class="form-group col-12 mb-0">
                    <label class="font-weight-bold text-secondary small">{{ __('description') }}</label>
                    {!! Form::textarea('description', null, [
                        'class' => 'form-control',
                        'placeholder' => __('description'),
                        'rows' => '3',
                    ]) !!}
                </div>
            </div>
        </div>

        {{-- ===== Section: Pricing & Billing ===== --}}
        <div class="p-4 border-bottom">
            <div class="d-flex align-items-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="text-success mr-2">
                    <line x1="12" x2="12" y1="2" y2="22" />
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                </svg>
                <h5 class="mb-0 font-weight-bold">{{ __('pricing') }}</h5>
            </div>

            <div class="row">
                {{-- Left: billing type --}}
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <label class="font-weight-bold text-secondary small d-block">{{ __('type') }} <span
                            class="text-danger">*</span></label>
                    <div class="form-row pkg-billing-row">
                        <div class="col-6">
                            <label class="billing-option mb-0">
                                <input type="radio" class="form-check-input package_type" name="type"
                                    id="prepaid" value="0" @if ($typeValue === 0) checked @endif>
                                <span class="billing-card d-block text-center border rounded p-3 bg-white">
                                    <span class="billing-title font-weight-bold d-block">{{ __('prepaid') }}</span>
                                    <small class="text-muted">{{ __('Pay before use') }}</small>
                                </span>
                            </label>
                        </div>
                        <div class="col-6">
                            <label class="billing-option mb-0">
                                <input type="radio" class="form-check-input package_type" name="type"
                                    id="postpaid" value="1" @if ($typeValue === 1) checked @endif>
                                <span class="billing-card d-block text-center border rounded p-3 bg-white">
                                    <span class="billing-title font-weight-bold d-block">{{ __('postpaid') }}</span>
                                    <small class="text-muted">{{ __('Pay after use') }}</small>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Right: validity + charges --}}
                <div class="col-lg-6">
                    <div class="form-group">
                        <label class="font-weight-bold text-secondary small day-label">{{ __('days') }} <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" class="text-muted">
                                    <rect width="18" height="18" x="3" y="4" rx="2"
                                        ry="2" />
                                    <line x1="16" x2="16" y1="2" y2="6" />
                                    <line x1="8" x2="8" y1="2" y2="6" />
                                    <line x1="3" x2="21" y1="10" y2="10" />
                                </svg>
                            </span>
                            {!! Form::number('days', null, [
                                'required',
                                'class' => 'form-control days',
                                'min' => 1,
                                'placeholder' => __('days'),
                            ]) !!}
                        </div>
                    </div>

                    {{-- Postpaid --}}
                    <div class="postpaid" @if (!$showPostpaid) style="display: none" @endif>
                        <div class="form-row">
                            <div class="form-group col-md-6 mb-0">
                                <label class="font-weight-bold text-secondary small student-label">
                                    {{ __('per_active_student_charges') }} <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    {!! Form::number('student_charge', null, [
                                        'required',
                                        'class' => 'form-control student-input',
                                        'min' => 0,
                                        'placeholder' => __('per_active_student_charges'),
                                    ]) !!}
                                </div>
                            </div>
                            <div class="form-group col-md-6 mb-0">
                                <label class="font-weight-bold text-secondary small staff-label">
                                    {{ __('per_active_staff_charges') }} <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    {!! Form::number('staff_charge', null, [
                                        'required',
                                        'class' => 'form-control staff-input',
                                        'min' => 0,
                                        'placeholder' => __('per_active_staff_charges'),
                                    ]) !!}
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Prepaid --}}
                    <div class="prepaid" @if ($showPostpaid) style="display: none" @endif>
                        <div class="form-row">
                            <div class="form-group col-md-6 mb-0">
                                <label class="font-weight-bold text-secondary small staff-label">
                                    {{ __('no_of_students') }} <span
                                        class="text-info small">({{ __('active') }})</span> <span
                                        class="text-danger">*</span></label>
                                {!! Form::number('no_of_students', null, [
                                    'required',
                                    'class' => 'form-control',
                                    'min' => 1,
                                    'placeholder' => __('no_of_students'),
                                ]) !!}
                            </div>
                            <div class="form-group col-md-6 mb-0">
                                <label class="font-weight-bold text-secondary small staff-label">
                                    {{ __('no_of_staffs') }} <span
                                        class="text-info small">({{ __('active') }})</span> <span
                                        class="text-danger">*</span></label>
                                {!! Form::number('no_of_staffs', null, [
                                    'required',
                                    'class' => 'form-control',
                                    'min' => 1,
                                    'placeholder' => __('no_of_staffs'),
                                ]) !!}
                            </div>
                        </div>
                    </div>
                    <div class="prepaid" @if ($showPostpaid) style="display: none" @endif>
                        <div class="form-group mt-3 mb-0">
                            <label class="font-weight-bold text-secondary small staff-label">{{ __('charges') }} <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                {!! Form::number('charges', null, [
                                    'required',
                                    'class' => 'form-control',
                                    'min' => 1,
                                    'placeholder' => __('charges'),
                                ]) !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Highlight + (Edit only) Instant effects.
                 Plain native checkboxes (no .custom-control pseudo-elements) so labels can never overlap. --}}
            <div class="d-flex flex-wrap align-items-start mt-4">
                <label class="pkg-check mb-0 mr-4">
                    {!! Form::checkbox('highlight', 1, null, ['class' => 'pkg-check-input', 'id' => 'package_highlight']) !!}
                    <span class="pkg-check-box"></span>
                    <span>{{ __('highlight_package') }}</span>
                </label>

                @if ($isEditMode)
                    <div>
                        <span class="popover-container">
                            <label class="pkg-check mb-0">
                                {!! Form::checkbox('instant_effects', 1, null, [
                                    'class' => 'pkg-check-input popover-trigger',
                                    'id' => 'instant_effects',
                                ]) !!}
                                <span class="pkg-check-box"></span>
                                <span>{{ __('instant_effects') }}</span>
                            </label>
                            <i class="popover-trigger fa fa-info-circle text-muted ml-1"></i>
                            <div class="popover-content">
                                <h4>{{ __('Immediate Features Access') }}</h4>
                                <hr>
                                <ul>
                                    <li><strong>{{ __('Checked') }}</strong>
                                        {{ __('Features changes will be available to existing subscribers in their current billing cycle') }}
                                    </li>
                                    <li><strong>{{ __('Unchecked') }}</strong>
                                        {{ __('Features changes will only be available to subscribers starting in their next billing cycle') }}
                                    </li>
                                </ul>
                            </div>
                        </span>
                        <div class="text-danger small mt-1">{{ __('note: Only features are effected') }}</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ===== Section: Features ===== --}}
        <div class="p-4">
            <div class="d-flex align-items-center mb-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" class="text-secondary mr-2">
                    <rect width="7" height="7" x="3" y="3" rx="1" />
                    <rect width="7" height="7" x="14" y="3" rx="1" />
                    <rect width="7" height="7" x="14" y="14" rx="1" />
                    <rect width="7" height="7" x="3" y="14" rx="1" />
                </svg>
                <h5 class="mb-0 font-weight-bold">{{ __('features') }}</h5>
            </div>
            <p class="text-muted small mb-4">{{ __('Select which modules are enabled in this package.') }}</p>

            {{-- Core / default features – always included --}}
            @if ($coreFeatures->isNotEmpty())
                <p class="text-uppercase text-muted font-weight-bold small mb-2">
                    {{ __('Core Architecture (Always Included)') }}</p>
                <div class="row mb-4">
                    @foreach ($coreFeatures as $feature)
                        <div class="col-sm-12 col-md-3 mb-3">
                            <input id="{{ __($feature->name) }}" class="feature-checkbox"
                                @if ($feature->is_default == 1) checked disabled @endif type="checkbox"
                                name="feature_id[]" value="{{ $feature->id }}" />
                            <label class="feature-list-default text-center" for="{{ __($feature->name) }}"
                                title="{{ __('default_feature') }}">{{ __($feature->name) }}</label>
                            <input type="hidden" name="feature_id[]" value="{{ $feature->id }}">
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Selectable features – grouped into module categories --}}
            @if (!empty($pkgGroupedFeatures))
                <p class="text-uppercase text-muted font-weight-bold small mb-2">{{ __('Selectable Modules') }}</p>
                @foreach ($pkgGroupedFeatures as $pkgGroupTitle => $pkgGroupItems)
                    <div class="card border mb-3 pkg-selectable-card">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input pkg-select-all"
                                    id="pkg-select-all-{{ $loop->index }}">
                                <label class="custom-control-label font-weight-bold text-dark mb-0"
                                    for="pkg-select-all-{{ $loop->index }}">{{ __($pkgGroupTitle) }}</label>
                            </div>
                            <span
                                class="text-muted small font-weight-bold pkg-sh-count">0/{{ $pkgGroupItems->count() }}</span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @foreach ($pkgGroupItems as $feature)
                                    <div class="col-12 col-md-3 mb-3">
                                        <input id="{{ __($feature->name) }}" class="feature-checkbox"
                                            type="checkbox" name="feature_id[]" value="{{ $feature->id }}"
                                            @if (in_array($feature->id, $packageFeatureIds)) checked @endif />
                                        <label class="feature-list text-center"
                                            for="{{ __($feature->name) }}">{{ __($feature->name) }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- ===== Footer actions ===== --}}
        <div class="d-flex justify-content-end align-items-center flex-wrap p-3 bg-light border-top">
            <input class="btn btn-light border mr-2" type="reset" value={{ __('reset') }}>
            <input class="btn btn-primary" id="create-btn" type="submit" value={{ __('submit') }}>
        </div>

        {!! Form::close() !!}
    </div>
</div>
