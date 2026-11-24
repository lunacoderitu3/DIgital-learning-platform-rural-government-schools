<script src="{{ asset('/assets/js/Chart.min.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/jquery.validate.min.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/jquery-toast-plugin/jquery.toast.min.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/select2/select2.min.js') }}?v={{ config('app.version') }}"></script>

<script src="{{ asset('/assets/js/off-canvas.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/hoverable-collapse.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/misc.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/settings.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/todolist.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/g-lightbox/js/glightbox.min.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/jquery.tagsinput.min.js') }}?v={{ config('app.version') }}"></script>

<script src="{{ asset('/assets/js/apexcharts.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('assets/js/custom/custom-search-select.js') }}?v={{ config('app.version') }}"></script>



<script src="{{ asset('/assets/bootstrap-table/bootstrap-table.min.js') }}?v={{ config('app.version') }}"></script>

<script src="https://unpkg.com/bootstrap-table@1.22.1/dist/bootstrap-table.min.js?v={{ config('app.version') }}">
</script>
<script src="{{ asset('/assets/bootstrap-table/bootstrap-table-mobile.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/bootstrap-table/bootstrap-table-export.min.js') }}?v={{ config('app.version') }}">
</script>
<script src="{{ asset('/assets/bootstrap-table/fixed-columns.min.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/bootstrap-table/tableExport.min.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/bootstrap-table/jspdf.min.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/bootstrap-table/jspdf.plugin.autotable.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/bootstrap-table/reorder-rows.min.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/bootstrap-table/jquery.tablednd.min.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/bootstrap-table/loadash.min.js') }}?v={{ config('app.version') }}"></script>

<script src="{{ asset('assets/bootstrap-5.3/js/bootstrap.bundle.min.js') }}?v={{ config('app.version') }}"></script>
{{-- Bootstrap 4 -> 5 compatibility layer: makes BS5 the single engine and
     neutralizes the Bootstrap 4 bundled in vendor.bundle.base.js. Must load
     right after the Bootstrap 5 bundle above. --}}
<script src="{{ asset('/assets/js/custom/bootstrap5-compat.js') }}?v={{ config('app.version') }}"></script>

{{-- <script src="https://cdn.jsdelivr.net/npm/bootstrap-table@1.27.3/dist/bootstrap-table.min.js"></script> --}}

<script src="{{ asset('/assets/js/jquery.cookie.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/sweetalert2.all.min.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/momentjs.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/datepicker.min.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/daterangepicker.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/jquery.repeater.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/tinymce/tinymce.min.js') }}?v={{ config('app.version') }}"></script>

<script src="{{ asset('/assets/color-picker/jquery-asColor.min.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/color-picker/color.min.js') }}?v={{ config('app.version') }}"></script>

<script src="{{ asset('/assets/js/custom/validate.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/jquery-additional-methods.min.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/custom/function.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/custom/common.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/custom/custom.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/custom/bootstrap-table/actionEvents.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/custom/bootstrap-table/formatter.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/custom/bootstrap-table/queryParams.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/js/custom/session-year-switcher.js') }}?v={{ config('app.version') }}"></script>

<script src="{{ asset('/assets/ckeditor-4/ckeditor.js') }}?v={{ config('app.version') }}"></script>
<script src="{{ asset('/assets/ckeditor-4/adapters/jquery.js') }}" async></script>
<script src="{{ asset('/assets/js/dragula.min.js') }}?v={{ config('app.version') }}"></script>


<script type='text/javascript'>
    @if ($errors->any())
        @foreach ($errors->all() as $error)
            $.toast({
                text: {!! json_encode($error) !!},
                showHideTransition: 'slide',
                icon: 'error',
                loaderBg: '#f2a654',
                position: 'top-right'
            });
        @endforeach
    @endif

    @if (Session::has('success'))
        $.toast({
            text: {!! json_encode(Session::get('success')) !!},
            showHideTransition: 'slide',
            icon: 'success',
            loaderBg: '#f96868',
            position: 'top-right'
        });
    @endif

    @if (Session::has('error'))
        $.toast({
            text: {!! json_encode(Session::get('error')) !!},
            showHideTransition: 'slide',
            icon: 'error',
            loaderBg: '#f2a654',
            position: 'top-right'
        });
    @endif
</script>
<script>
    const please_wait = "{{ __('Please wait') }}"
    const processing_your_request = "{{ __('Processing your request') }}"
    let date_format = '{{ $schoolSettings['date_format'] ?? ($systemSettings['date_format'] ?? 'd-m-Y') }}'.replace(
        'Y',
        'YYYY').replace('m', 'MM').replace('d', 'DD');

    let date_time_format =
        '{{ $schoolSettings['date_format'] ?? ($systemSettings['date_format'] ?? 'd-m-Y') }} {{ $schoolSettings['time_format'] ?? ($systemSettings['time_format'] ?? 'h:i A') }}'
        .replace('Y', 'YYYY').replace('m', 'MM').replace('d', 'DD').replace('h', 'hh').replace('H', 'HH').replace('i',
            'mm').replace('a', 'a').replace('A', 'A');

    let time_format = '{{ $schoolSettings['time_format'] ?? ($systemSettings['time_format'] ?? 'h:i A') }}'.replace(
        'h',
        'hh').replace('H', 'HH').replace('i', 'mm').replace('a', 'a').replace('A', 'A');

    window.session_year_id = "{{ app(App\Services\CachingService::class)->getSessionYear()->id ?? '' }}";

    // text-wrap
    $(document).ready(function() {
        $('a.nav-link.active').addClass('text-wrap');
    });


    setTimeout(() => {

        $(document).ready(function() {
            var targetNode = document.querySelector('thead');
            if (targetNode) {
                // Apply initial styles
                $('th[data-field="operate"]').addClass('action-column');

                // Create an observer instance linked to the callback function
                var observer = new MutationObserver(function(mutationsList, observer) {
                    for (var mutation of mutationsList) {
                        if (mutation.type === 'childList') {
                            // Reapply the class when changes are detected
                            $('th[data-field="operate"]').addClass('action-column');
                        }
                    }
                });

                // Start observing the target node for configured mutations
                observer.observe(targetNode, {
                    childList: true,
                    subtree: true
                });
            }
        });

    }, 500);


    // razorpay-payment-button
    setTimeout(() => {
        $('.razorpay-payment-button').addClass('btn btn-info');
    }, 100);



    // document.addEventListener("DOMContentLoaded", function () {
    //     var isMobile = window.matchMedia("only screen and (max-width: 768px)").matches;
    //     var table = document.getElementsByClassName('reorder-table-row');

    //     if (table) {
    //         if (isMobile) {
    //             table.removeAttribute('data-reorderable-rows');
    //         } else {
    //             table.setAttribute('data-reorderable-rows', 'true');
    //         }
    //     }
    //     // Initialize the table
    //     $('.reorder-table-row').bootstrapTable();
    // });



    document.addEventListener("DOMContentLoaded", function() {
        // Add the event listener for the button to initiate the payment
        setTimeout(() => {

            $('#razorpay-button').click(function(e) {
                e.preventDefault();
                let baseUrl = window.location.origin;
                var order_id = '';
                var paymentTransactionId = '';

                $.ajax({
                    type: "post",
                    url: baseUrl + '/subscriptions/create/razorpay/order-id',
                    data: {
                        amount: $('.bill_amount')
                            .val(), // Amount is in currency subunits. Default currency is INR. Hence, 100 refers to 1 INR
                        currency: "{{ $systemSettings['currency_code'] ?? 'INR' }}",

                        type: $('.type').val(),
                        package_type: $('.package_type').val(),
                        package_id: $('.package_id').val(),
                        upcoming_plan_type: $('.upcoming_plan_type').val(),
                        subscription_id: $('.subscription_id').val(),
                        feature_id: $('.feature_id').val(),
                        end_date: $('.end_date').val(),

                    },
                    success: function(response) {
                        if (response.data) {
                            order_id = response.data.order.id;
                            paymentTransactionId = response.data.paymentTransaction
                                .id;

                            var options = {
                                "key": "{{ $paymentConfiguration->api_key ?? '' }}", // Enter the Key ID generated from the Dashboard
                                "amount": $('.bill_amount').val() *
                                    100, // Amount is in currency subunits. Default currency is INR. Hence, 100 refers to 1 INR
                                "currency": "{{ $systemSettings['currency_code'] ?? 'INR' }}",
                                "name": "{{ $systemSettings['system_name'] ?? 'eSchool-Saas' }}",
                                "description": "Razorpay",
                                "order_id": order_id,
                                "handler": function(response) {
                                    // Set the response data in the form
                                    $('.razorpay_payment_id').val(response
                                        .razorpay_payment_id);
                                    $('.razorpay_signature').val(response
                                        .razorpay_signature);
                                    $('.razorpay_order_id').val(response
                                        .razorpay_order_id);
                                    $('.paymentTransactionId').val(
                                        paymentTransactionId);

                                    // Submit the form
                                    document.querySelector('.razorpay-form')
                                        .submit();
                                }
                            };

                            var rzp1 = new Razorpay(options);
                            rzp1.open();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                text: response.message
                            });
                        }
                    }

                });


            });

        }, 100);

    });

    $(document).ready(function() {
        // Global AJAX setup to include session_year_id
        $.ajaxSetup({
            beforeSend: function(xhr, settings) {
                if (settings.type === 'POST' || settings.type === 'PUT' || settings.type ===
                    'PATCH' || settings.type === 'DELETE') {
                    if (settings.data instanceof FormData) {
                        if (!settings.data.has('session_year_id')) {
                            settings.data.append('session_year_id', window.session_year_id);
                        }
                    } else {
                        if (typeof settings.data === 'string') {
                            if (settings.data.indexOf('session_year_id=') === -1) {
                                settings.data += (settings.data.length > 0 ? '&' : '') +
                                    'session_year_id=' + window.session_year_id;
                            }
                        } else if (typeof settings.data === 'object' && settings.data !== null) {
                            if (!settings.data.hasOwnProperty('session_year_id')) {
                                settings.data.session_year_id = window.session_year_id;
                            }
                        } else if (settings.data === undefined) {
                            settings.data = 'session_year_id=' + window.session_year_id;
                        }
                    }
                }
            }
        });

        // Add session_year_id to all forms on submit
        $(document).on('submit', 'form', function() {
            if ($(this).find('input[name="session_year_id"]').length === 0) {
                $(this).append('<input type="hidden" name="session_year_id" value="' + window
                    .session_year_id + '">');
            }
        });
    });
</script>

{{-- Search sidebar menu --}}
<script>
    $(document).ready(function() {
        $("#menu-search,#menu-search-mini").on("keyup", function() {
            var value = $(this).val().toLowerCase();

            $(".nav > li").each(function() {
                var parent = $(this);
                var parentText = parent.children('a').text().toLowerCase();
                var parentMatch = parentText.indexOf(value) > -1;

                // Check if any child items match
                var childMatch = false;
                parent.find('.sub-menu > li').each(function() {
                    var child = $(this);
                    var childText = child.text().toLowerCase();
                    if (childText.indexOf(value) > -1) {
                        child.show();
                        childMatch = true;
                    } else {
                        child.hide();
                    }
                });

                // Show parent if any child matches, otherwise hide
                if (parentMatch || childMatch) {
                    parent.show();
                    if (childMatch) {
                        parent.children('.sub-menu').slideDown();
                    }
                } else {
                    parent.hide();
                }
            });
        });

    });

    $('.navbar-toggler').click(function(e) {
        e.preventDefault();

        var updatedClasses = $('body').hasClass('sidebar-icon-only');

        if (!updatedClasses) {
            $('.menu-search').addClass('d-none');
            // all menus close
            $('.collapse').removeClass('show');
        } else {
            $('.menu-search').removeClass('d-none');
        }
    });
</script>

<script src="{{ asset('/assets/js/custom/sidebar-handler.js') }}"></script>


<script>
    // Function to handle image errors
    function handleImageError(image) {
        // Prevent infinite loops by checking if already processed
        if (image.hasAttribute('data-error-handled')) {
            return;
        }

        // Temporarily disconnect observer to prevent triggering mutations
        imageObserver.disconnect();

        try {
            if (image.classList.contains('custom-default-image')) {
                if (image.getAttribute('data-custom-image') != null) {
                    image.src = image.getAttribute('data-custom-image');
                } else {
                    image.src = "{{ asset('/assets/no_image_available.jpg') }}";
                }
            } else {
                image.src = "{{ asset('/assets/no_image_available.jpg') }}";
            }

            // Mark as processed to prevent duplicate handling
            image.setAttribute('data-error-handled', 'true');
        } finally {
            // Reconnect observer after changes
            imageObserver.observe(document, {
                childList: true,
                subtree: true
            });
        }
    }

    // Create a MutationObserver to watch for DOM changes
    const imageObserver = new MutationObserver((mutationsList) => {
        mutationsList.forEach((mutation) => {
            if (mutation.addedNodes) {
                mutation.addedNodes.forEach((node) => {
                    // Check if the added node is an image element
                    if (node instanceof HTMLImageElement && !node.hasAttribute(
                            'data-error-handled')) {
                        node.addEventListener('error', () => {
                            handleImageError(node);
                        });
                    }
                });
            }
        });
    });

    // Initialize: Handle existing images on page load and start observing
    document.addEventListener('DOMContentLoaded', function() {
        // Add error handlers to all existing images
        document.querySelectorAll('img').forEach(function(img) {
            if (!img.hasAttribute('data-error-handled')) {
                img.addEventListener('error', function() {
                    handleImageError(img);
                });
            }
        });

        // Start observing for new images added to the DOM
        imageObserver.observe(document, {
            childList: true,
            subtree: true
        });
    });

    $(document).on('click', '.change-session-year', function(e) {
        e.preventDefault();
        let session_year_id = $(this).data('id');
        let session_year_name = $(this).find('.preview-subject').text().replace('{{ __('default') }}', '')
            .trim();
        let baseUrl = window.location.origin;

        let changeSessionYear = function() {
            $.post(baseUrl + '/session-year/set-session-year', {
                session_year_id: session_year_id,
                _token: '{{ csrf_token() }}'
            }, function(response) {
                if (response.error == false) {
                    location.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        text: response.message
                    });
                }
            });
        };

        Swal.fire({
            title: '{{ __('are_you_sure') }}',
            text: '{{ __('data_will_be_displayed_and_created_based_on_the_selected_session_year') }} (' +
                session_year_name +
                '), {{ __('while_the_current_session_will_remain_unchanged_do_you_want_to_proceed') }}',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: '{{ __('Yes, change it!') }}'
        }).then((result) => {
            if (result.isConfirmed) {
                changeSessionYear();
            }
        });
    });

    $(document).on('click', '.change-viewing-semester', function(e) {
        e.preventDefault();
        let semester_id = $(this).data('id');
        let baseUrl = window.location.origin;

        $.post(baseUrl + '/set-semester', {
            semester_id: semester_id,
            _token: '{{ csrf_token() }}'
        }, function(response) {
            if (response.error == false) {
                location.reload();
            } else {
                Swal.fire({
                    icon: 'error',
                    text: response.message
                });
            }
        });
    });

    // Global fix for Bootstrap Table dropdown overflow/clipping issues
    $(document).on('show.bs.dropdown', '.table-action-column', function() {
        var $dropdown = $(this);
        var $menu = $dropdown.find('.dropdown-menu');
        var $btn = $dropdown.find('[data-toggle="dropdown"]');

        // Take it out of flow immediately to prevent table scrollbar/padding issues
        var offset = $btn.offset();

        $menu.css({
            'position': 'fixed',
            'top': (offset.top + $btn.outerHeight()) + 'px',
            'left': offset.left + 'px',
            'z-index': 1100,
            'display': 'block',
            'visibility': 'hidden' // Hide until shown event finalizes position
        });
    }).on('shown.bs.dropdown', '.table-action-column', function() {
        var $dropdown = $(this);
        var $menu = $dropdown.find('.dropdown-menu');
        var $btn = $dropdown.find('[data-toggle="dropdown"]');

        $dropdown.data('original-parent', $menu.parent());

        var offset = $btn.offset();
        var menuWidth = $menu.outerWidth();
        var leftPos = offset.left;

        // Handle alignment: if right-aligned, align right edges
        if ($menu.hasClass('dropdown-menu-right')) {
            leftPos = offset.left + $btn.outerWidth() - menuWidth;
        }

        // Prevent opening off-screen (left/right boundaries)
        var winWidth = $(window).width();
        if (leftPos < 10) {
            leftPos = 10;
        } else if (leftPos + menuWidth > winWidth - 10) {
            leftPos = winWidth - menuWidth - 10;
        }

        $menu.css({
            'visibility': 'visible',
            'left': leftPos + 'px',
            'width': 'auto',
            'min-width': 'max-content'
        }).appendTo('body');
    }).on('hide.bs.dropdown', '.table-action-column', function() {
        var $dropdown = $(this);
        var $parent = $dropdown.data('original-parent');
        var $menu = $('body > .action-column-dropdown-menu');

        if ($parent && $parent.length && $menu.length) {
            $menu.css({
                'display': '',
                'position': '',
                'top': '',
                'left': '',
                'width': '',
                'min-width': '',
                'z-index': '',
                'visibility': ''
            }).appendTo($parent);
        }
    });
</script>
