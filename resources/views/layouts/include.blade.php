@php
    $lang = Session::get('language');
@endphp
<link rel="stylesheet" href="{{ asset('/assets/css/materialdesignicons.min.css') }}?v={{ config('app.version') }}">
<link rel="stylesheet" href="{{ asset('/assets/css/vendor.bundle.base.css') }}?v={{ config('app.version') }}">

<link rel="stylesheet" href="{{ asset('/assets/fonts/fontawesome-7-2/all.min.css') }}?v={{ config('app.version') }}" />
<link rel="stylesheet" href="{{ asset('/assets/select2/select2.min.css') }}?v={{ config('app.version') }}">
<link rel="stylesheet"
    href="{{ asset('/assets/jquery-toast-plugin/jquery.toast.min.css') }}?v={{ config('app.version') }}">
<link rel="stylesheet" href="{{ asset('/assets/color-picker/color.min.css') }}?v={{ config('app.version') }}">
@if ($lang)
    @if ($lang->is_rtl)
        <link rel="stylesheet" href="{{ asset('/assets/css/rtl.min.css') }}?v={{ config('app.version') }}">
        <link rel="stylesheet" href="{{ asset('/assets/css/custom-rtl.css') }}?v={{ config('app.version') }}">
    @else
        <link rel="stylesheet" href="{{ asset('/assets/css/style.min.css') }}?v={{ config('app.version') }}">
        <link rel="stylesheet" href="{{ asset('/assets/css/custom.css') }}?v={{ config('app.version') }}">
    @endif
@else
    <link rel="stylesheet" href="{{ asset('/assets/css/style.min.css') }}?v={{ config('app.version') }}">
    <link rel="stylesheet" href="{{ asset('/assets/css/custom.css') }}?v={{ config('app.version') }}">
@endif

<link rel="stylesheet" href="{{ asset('assets/bootstrap-5.3/css/bootstrap.min.css') }}?v={{ config('app.version') }}">
<link rel="stylesheet" href="{{ asset('assets/css/custom/custom-search-select.css') }}?v={{ config('app.version') }}">
<link rel="stylesheet" href="{{ asset('assets/css/custom/session-year-switcher.css') }}?v={{ config('app.version') }}">

{{-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-table@1.27.3/dist/bootstrap-table.min.css"> --}}

<link rel="stylesheet" href="{{ asset('/assets/css/comman.css') }}?v={{ config('app.version') }}">
<link rel="stylesheet" href="{{ asset('/assets/css/datepicker.min.css') }}?v={{ config('app.version') }}">
<link rel="stylesheet" href="{{ asset('/assets/css/daterangepicker.css') }}?v={{ config('app.version') }}">
<link rel="stylesheet" href="{{ asset('/assets/g-lightbox/css/glightbox.min.css') }}?v={{ config('app.version') }}">
<link rel="stylesheet" href="{{ asset('/assets/css/jquery.tagsinput.min.css') }}?v={{ config('app.version') }}">


<link rel="stylesheet"
    href="{{ asset('/assets/bootstrap-table/bootstrap-table.min.css') }}?v={{ config('app.version') }}">
<link rel="stylesheet"
    href="https://unpkg.com/bootstrap-table@1.22.1/dist/bootstrap-table.min.css?v={{ config('app.version') }}">
<link rel="stylesheet"
    href="{{ asset('/assets/bootstrap-table/fixed-columns.min.css') }}?v={{ config('app.version') }}">
<link rel="stylesheet" href="{{ asset('/assets/bootstrap-table/reorder-rows.css') }}?v={{ config('app.version') }}">

<script src="{{ asset('/assets/js/vendor.bundle.base.js') }}?v={{ config('app.version') }}"></script>
<script src='{{ asset('/assets/js/fullcalendar.js') }}?v={{ config('app.version') }}'></script>

{{--
<link rel="shortcut icon" href="{{asset(config('global.LOGO_SM')) }}" /> --}}
<link rel="shortcut icon"
    href="{{ $schoolSettings['favicon'] ?? ($systemSettings['favicon'] ?? url('assets/vertical-logo.svg')) }}" />

<script>
    window.trans = {};
</script>
<script src="{{ url('/js/lang') }}"></script>
<style>
    :root {
        --theme-color:
            <?=$systemSettings['theme_color'] ?? '#22577A' ?> !important;
        --primary: <?=$systemSettings['theme_color'] ?? '#22577A' ?> !important;
        --primary-color-sidebar: {{ $systemSettings['theme_color'] ?? '#22577a' }};
    }

    .btn-outline-primary:not(:disabled):not(.disabled).active {
        color: #fff;
        background-color: var(--theme-color);
        border-color: var(--theme-color) !important;
    }

    .btn-outline-primary:hover {
        background: var(--theme-color);
        color: #ffffff;
    }

    .btn-outline-primary {
        color: var(--theme-color);
        border-color: var(--theme-color);
    }
</style>
<script>
    const baseUrl = "{{ URL::to('/') }}";

    // Function to handle image errors
    function handleImageError(image) {
        image.classList.contains('custom-default-image')
        if (image.getAttribute('data-custom-image') != null) {
            image.src = image.getAttribute('data-custom-image');
        } else {
            image.src = "{{ asset('/assets/no_image_available.jpg') }}";
        }
    }

    // Create a MutationObserver to watch for DOM changes
    const observer = new MutationObserver((mutationsList) => {
        mutationsList.forEach((mutation) => {
            if (mutation.addedNodes) {
                mutation.addedNodes.forEach((node) => {
                    // Check if the added node is an image element
                    if (node instanceof HTMLImageElement) {
                        node.addEventListener('error', () => {
                            handleImageError(node);
                        });
                    }
                });
            }
        });
    });

    // Start observing changes in the DOM
    observer.observe(document, {
        childList: true,
        subtree: true
    });

    const onErrorImage = (e) => {
        e.target.src = "{{ asset('/assets/no_image_available.jpg') }}";
    };

    const onErrorImageSidebarHorizontalLogo = (e) => {
        e.target.src = "{{ asset('/assets/vertical-logo.svg') }}";
    };
</script>
