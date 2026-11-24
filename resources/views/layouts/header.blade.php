@php
    $lang = Session::get('language');
    $alignmentClass = $lang && $lang->is_rtl ? 'dropdown-menu-left' : 'dropdown-menu-right';
@endphp
<nav class="navbar default-layout-navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
    <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
        <a class="navbar-brand brand-logo" href="{{ URL::to('/dashboard') }}">
            <img src="{{ $schoolSettings['horizontal_logo'] ?? '' }}" alt="logo"
                data-custom-image="{{ $systemSettings['horizontal_logo'] ?? asset('/assets/horizontal-logo2.svg') }}"
                class="custom-default-image">
        </a>
        <a class="navbar-brand brand-logo-mini" href="{{ URL::to('/dashboard') }}">
            <img src="{{ $schoolSettings['vertical_logo'] ?? '' }}" alt="logo"
                data-custom-image="{{ $systemSettings['vertical_logo'] ?? asset('/assets/vertical-logo.svg') }}">
        </a>
    </div>
    <div class="navbar-menu-wrapper d-flex align-items-stretch">
        <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
            <span class="fa fa-bars"></span>
        </button>

        @if ($schoolSettings['school_name'] ?? '')
            <div class="align-items-stretch d-none d-md-block d-sm-block cache-clear">
                <span class="ml-3">{{ $schoolSettings['school_name'] ?? '' }}</span>
            </div>
        @endif
        @if (isset($systemSettings['email_verified']) && !$systemSettings['email_verified'])
            @can('email-setting-create')
                <div class="m-auto order-0 d-none d-md-block">
                    <div class="badge badge-danger my-2" role="alert">
                        <i class="fa fa-exclamation"></i>
                        {{ __('Email Configuration is not verified') }} <a href="{{ route('system-settings.email.index') }}"
                            class="alert-link">{{ __('Click here to redirect to email configuration') }}</a>.
                    </div>
                </div>
            @endcan
        @endif

        @if (Auth::user() &&
                !Auth::user()->school_id &&
                isset($systemSettings['web_maintenance']) &&
                $systemSettings['web_maintenance'] == 1)
            <div class="m-auto order-0 d-none d-md-block">
                <div class="badge badge-danger my-2" role="alert">
                    <i class="fa fa-exclamation"></i>
                    {{ __('the_system_is_currently_in_maintenance_mode') }}
                </div>
            </div>
        @endif

        <ul class="navbar-nav navbar-nav-right">

            @if (isset($sessionYear) && !Auth::user()->hasRole('Super Admin'))
                <li class="nav-item d-none d-md-block d-sm-block">
                    <!-- Session Year and Semester Unified Switcher -->
                    <div class="sys-dropdown-wrapper">
                        <button class="sys-trigger-btn" onclick="toggleSysDropdown(event)">
                            <div class="sys-icon-container">
                                <i class="fa fa-calendar"></i>
                            </div>
                            <div class="sys-text-container">
                                <div class="sys-label">{{ __('active_workspace') }}</div>
                                <div class="sys-value">
                                    {{ $sessionYear->name }}
                                    @if (isset($semester) && !empty($semesters) && count($semesters) > 0)
                                        <span id="sys-trigger-semester">• {{ $semester->name }}</span>
                                    @endif
                                </div>
                            </div>
                            <i class="fa fa-chevron-down sys-arrow" id="sys-arrow"></i>
                        </button>

                        <div class="sys-dropdown-menu" id="sys-dropdown-menu">
                            <div class="sys-split-container">
                                <!-- Session Year Column -->
                                <div class="sys-col-left">
                                    <span class="sys-col-title">{{ __('session_year') }}</span>
                                    <div class="sys-list-container">
                                        @foreach ($sessionYears as $year)
                                            <button type="button"
                                                class="sys-option-btn sys-session-btn {{ $year->id == $sessionYear->id ? 'active' : '' }}"
                                                data-id="{{ $year->id }}"
                                                onclick="selectSysSession({{ $year->id }}, event)">
                                                <span>{{ $year->name }}</span>
                                                @if ($year->id == $defaultSessionYear->id)
                                                    <span class="sys-option-badge">{{ __('default') }}</span>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Semester Column -->
                                <div class="sys-col-right" id="sys-semester-col">
                                    <span class="sys-col-title">{{ __('semester') }}</span>
                                    <div class="sys-list-container" id="sys-semester-list">
                                        @if (!empty($semesters) && count($semesters) > 0)
                                            @foreach ($semesters as $sem)
                                                <button type="button"
                                                    class="sys-option-btn {{ isset($semester) && $sem->id == $semester->id ? 'active' : '' }}"
                                                    onclick="event.stopPropagation(); sysPendingSemester = {{ $sem->id }}; updateSysSwitcherRender(); Array.from(this.parentElement.children).forEach(child => child.classList.remove('active')); this.classList.add('active'); checkSysApplyButton();">
                                                    <span>{{ $sem->name }}</span>
                                                    @if (isset($defaultSemester) && $sem->id == $defaultSemester->id)
                                                        <span class="sys-option-badge">{{ __('default') }}</span>
                                                    @endif
                                                </button>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="sys-footer" id="sys-footer">
                                <button type="button" class="sys-btn-cancel"
                                    onclick="closeSysDropdown(event)">{{ __('cancel') }}</button>
                                <button type="button" class="sys-btn-apply btn-theme" id="sys-btn-apply"
                                    onclick="applySysTermChanges(event)">{{ __('set_active_term') }}</button>
                            </div>
                        </div>
                    </div>
                </li>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        if (typeof initSessionYearSwitcher === 'function') {
                            initSessionYearSwitcher(
                                "{{ $sessionYear->id }}",
                                "{{ isset($semester) ? $semester->id : '' }}",
                                {{ !empty($semesters) && count($semesters) > 0 ? 'true' : 'false' }}
                            );
                        }
                    });
                </script>
            @endif

            {{-- <li class="d-none d-md-block d-sm-block nav-item ml-4">
                <div class="text-dark">
                    <span><i class="mdi mdi-weather-sunny fa-2x cursor-pointer theme"></i></span>
                </div>
            </li> --}}

            <li class="nav-item dropdown lang-switcher d-none d-md-block d-sm-block">
                @php
                    $currentLanguage = Session::get('language');
                    $currentLanguageCode = $currentLanguage->code ?? 'en';
                @endphp
                <a class="nav-link count-indicator dropdown-toggle lang-switcher-toggle" id="languageDropdown"
                    href="#" data-toggle="dropdown" aria-expanded="false" title="{{ __('language') }}">
                    @if ($currentLanguage && $currentLanguage->image)
                        <img src="{{ $currentLanguage->image }}" alt="" class="lang-switcher-current-flag"
                            onerror="handleImageError(this)">
                    @else
                        <i class="fa fa-language"></i>
                    @endif
                    <span
                        class="lang-switcher-current-code d-none d-sm-inline">{{ strtoupper($currentLanguageCode) }}</span>
                </a>
                <div class="dropdown-menu {{ $alignmentClass }} navbar-dropdown lang-switcher-menu"
                    aria-labelledby="languageDropdown">
                    <div class="lang-switcher-header">{{ __('choose_language') }}</div>
                    <div class="lang-switcher-list">
                        @foreach ($languages as $key => $language)
                            <a class="lang-switcher-item {{ $language->code == $currentLanguageCode ? 'active' : '' }}"
                                href="{{ url('set-language') . '/' . $language->code }}">
                                @if ($language->image)
                                    <img src="{{ $language->image }}" alt="{{ $language->code }}"
                                        class="lang-switcher-flag" onerror="handleImageError(this)">
                                @else
                                    <span class="lang-switcher-flag-fallback"><i class="fa fa-globe"></i></span>
                                @endif
                                <span class="lang-switcher-info">
                                    <span class="lang-switcher-name">{{ $language->name }}</span>
                                    <span class="lang-switcher-code">{{ $language->code }}</span>
                                </span>
                                <i class="fa fa-check-circle lang-switcher-check"></i>
                            </a>
                        @endforeach
                    </div>
                </div>
            </li>
            <li class="nav-item nav-profile dropdown">
                <a class="nav-link dropdown-toggle" id="profileDropdown" href="#" data-toggle="dropdown"
                    aria-expanded="true">
                    <div class="nav-profile-img">
                        <img src="{{ Auth::user()->image }}" alt="image">
                    </div>
                    <div class="nav-profile-text">
                        <p class="mb-1 text-black">{{ Auth::user()->first_name }}</p>
                    </div>
                </a>
                <div class="dropdown-menu navbar-dropdown" aria-labelledby="profileDropdown">
                    {{-- @can('update-admin-profile') --}}
                    <a class="dropdown-item" href="{{ route('auth.profile.edit') }}"><i
                            class="fa fa-user mr-2"></i>{{ __('profile') }}</a>
                    <div class="dropdown-divider"></div>
                    {{-- @endcan --}}
                    <a class="dropdown-item" href="{{ route('auth.change-password.index') }}">
                        <i class="fa fa-refresh mr-2 text-success"></i>{{ __('change_password') }}</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="{{ url('cache-flush') }}">
                        <i class="fa fa-eraser mr-2 text-theme"></i> {{ __('cache_clear') }}
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="{{ route('auth.logout') }}">
                        <i class="fa fa-sign-out mr-2 text-danger"></i> {{ __('signout') }}
                    </a>
                </div>
            </li>
        </ul>
        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button"
            data-toggle="offcanvas">
            <span class="fa fa-bars"></span>
        </button>
    </div>
</nav>
