/* ==========================================================================
   QR Attendance Settings
   Location picker, QR lifecycle and the readiness panel.
   The live map only loads once an API key is supplied; until then the picker
   falls back to a preview and coordinates are typed by hand.
   ========================================================================== */
(function () {
    'use strict';

    var settings = window.qrAttendance || {};

    var $lat = $('#qr_attendance_latitude');
    var $lng = $('#qr_attendance_longitude');
    var $radius = $('#qr_attendance_radius');
    var $wrap = $('#qr-map-wrap');
    var $note = $('#qr-map-note');
    var $previewCircle = $('#qr-pv-circle');
    var $schoolEnd = $('#qr_attendance_school_end_time');

    var map, marker, circle, geocoder;

    /* ---------------------------------------------------------- helpers */

    function centre() {
        return {
            lat: parseFloat($lat.val()) || 0,
            lng: parseFloat($lng.val()) || 0
        };
    }

    function radiusValue() {
        return parseInt($radius.val(), 10) || 0;
    }

    function updateNote() {
        var c = centre();
        $note.text(radiusValue() + ' m · ' + c.lat.toFixed(6) + ', ' + c.lng.toFixed(6));
    }

    /* The preview circle tracks the radius field, so the control still reads
       correctly before a key is entered. */
    function sizePreview() {
        var px = Math.max(46, Math.min(280, radiusValue() * 0.86));
        $previewCircle.css({ width: px + 'px', height: px + 'px' });
    }

    /* Anything that changes the coordinates goes through here. */
    function syncFromFields(pan) {
        updateNote();
        if (!marker) {
            return;
        }
        var c = centre();
        marker.setPosition(c);
        circle.setCenter(c);
        if (pan) {
            map.panTo(c);
        }
    }

    /* -------------------------------------------------- finalization echo */

    function updateFinalizationTime() {
        var end = $schoolEnd.val();
        if (!end) {
            $('#finalization_time').val('--:--');
            return;
        }
        var parts = end.split(':');
        var date = new Date();
        date.setHours(parseInt(parts[0], 10) + 1, parseInt(parts[1], 10), 0, 0);
        $('#finalization_time').val(
            ('0' + date.getHours()).slice(-2) + ':' + ('0' + date.getMinutes()).slice(-2)
        );
    }

    /* ----------------------------------------------------------- the map */

    window.initQrAttendanceMap = function () {
        var pos = centre();

        map = new google.maps.Map(document.getElementById('qr-map-canvas'), {
            center: pos,
            zoom: 17,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false,
            clickableIcons: false
        });

        marker = new google.maps.Marker({
            position: pos,
            map: map,
            draggable: true
        });

        circle = new google.maps.Circle({
            map: map,
            center: pos,
            radius: radiusValue(),
            strokeColor: '#22577A',
            strokeOpacity: 0.9,
            strokeWeight: 2,
            fillColor: '#22577A',
            fillOpacity: 0.10
        });

        geocoder = new google.maps.Geocoder();

        // Picking on the map fills the coordinate fields.
        function commit(latLng) {
            $lat.val(latLng.lat().toFixed(6));
            $lng.val(latLng.lng().toFixed(6));
            circle.setCenter(latLng);
            updateNote();
        }

        marker.addListener('dragend', function (e) {
            commit(e.latLng);
        });

        map.addListener('click', function (e) {
            marker.setPosition(e.latLng);
            commit(e.latLng);
        });

        $wrap.addClass('is-live');
    };

    // Google calls this when the key is rejected.
    window.gm_authFailure = function () {
        $wrap.removeClass('is-live');
        showErrorToast(window.trans['Error Occurred'] || 'Google Maps key rejected');
    };

    function loadMap() {
        var key = $.trim(settings.mapsApiKey || "");

        if (!key) {
            return;
        }

        if (window.google && window.google.maps) {
            window.initQrAttendanceMap();
            return;
        }

        var script = document.createElement('script');
        script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) +
            '&callback=initQrAttendanceMap&loading=async';
        script.async = true;
        script.onerror = function () {
            showErrorToast(window.trans['Error Occurred'] || 'Could not reach Google Maps');
        };
        document.head.appendChild(script);
    }

    /* ------------------------------------------------------- QR lifecycle */

    function generateQr() {
        var $btn = $('#generate-qr');

        $.ajax({
            url: $btn.data('url'),
            type: 'POST',
            data: {
                end_date: $('#new_qr_end_date').val() || null
            },
            beforeSend: function () {
                $btn.prop('disabled', true);
            },
            success: function (response) {
                if (response.error) {
                    showErrorToast(response.message);
                    $btn.prop('disabled', false);
                    return;
                }
                showSuccessToast(response.message);
                location.reload();
            },
            error: function (xhr) {
                showErrorToast(xhr.responseJSON ? xhr.responseJSON.message : '');
                $btn.prop('disabled', false);
            }
        });
    }

    function saveQrEndDate() {
        var $btn = $('#save-qr-end-date');
        var endDate = $('#qr_end_date').val();

        if (!endDate) {
            return;
        }

        $.ajax({
            url: $btn.data('url'),
            type: 'PUT',
            data: { end_date: endDate },
            beforeSend: function () {
                $btn.prop('disabled', true);
            },
            success: function (response) {
                $btn.prop('disabled', false);
                if (response.error) {
                    showErrorToast(response.message);
                    return;
                }
                showSuccessToast(response.message);
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                showErrorToast(xhr.responseJSON ? xhr.responseJSON.message : '');
            }
        });
    }

    /* ----------------------------------------------------------- bindings */

    $(function () {
        updateNote();
        sizePreview();

        $('#load-map').on('click', loadMap);

        $radius.on('input', function () {
            updateNote();
            sizePreview();
            if (circle) {
                circle.setRadius(radiusValue());
            }
        });

        // Typing coordinates by hand moves the map straight away.
        $lat.add($lng).on('input', function () {
            syncFromFields(false);
        }).on('change', function () {
            syncFromFields(true);
        });

        $('#use-current-location').on('click', function () {
            if (!navigator.geolocation) {
                showErrorToast(window.trans['Error Occurred'] || 'Location unavailable');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                function (position) {
                    $lat.val(position.coords.latitude.toFixed(6));
                    $lng.val(position.coords.longitude.toFixed(6));
                    syncFromFields(true);
                },
                function () {
                    showErrorToast(window.trans['Error Occurred'] || 'Location permission denied');
                }
            );
        });

        $schoolEnd.on('change', updateFinalizationTime);

        $('#generate-qr').on('click', generateQr);
        $('#save-qr-end-date').on('click', saveQrEndDate);
        $('#print-qr').on('click', function() {
            var svgElement = document.querySelector('.qr-frame svg');
            if (!svgElement) return;

            var statusHtml = document.getElementById('qr-status-badges') ? document.getElementById('qr-status-badges').innerHTML : '';

            var printWindow = window.open('', '', 'width=600,height=600');
            printWindow.document.write('<html><head><title>Print QR Code</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background-color: #ffffff; font-family: sans-serif; }');
            printWindow.document.write('.print-container { text-align: center; padding: 20px; }');
            printWindow.document.write('svg { width: 400px; height: 400px; margin-bottom: 20px; }');
            printWindow.document.write('.badge { display: inline-block; padding: 0.5em 0.75em; font-size: 1em; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.25rem; margin: 0 5px; }');
            printWindow.document.write('.badge-success { color: #fff; background-color: #28a745; border: 1px solid #28a745; }');
            printWindow.document.write('.badge-primary { color: #fff; background-color: #007bff; border: 1px solid #007bff; }');
            printWindow.document.write('.badge-danger { color: #fff; background-color: #dc3545; border: 1px solid #dc3545; }');
            printWindow.document.write('@media print { @page { size: auto; margin: 0; } body { min-height: auto; height: auto; display: block; padding-top: 50px; -webkit-print-color-adjust: exact; print-color-adjust: exact; margin: 20px; } .print-container { page-break-inside: avoid; } }');
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write('<div class="print-container">');
            printWindow.document.write('<h2>QR Code</h2>');
            printWindow.document.write(svgElement.outerHTML);
            printWindow.document.write('<div>' + statusHtml + '</div>');
            printWindow.document.write('</div>');
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            setTimeout(function() {
                printWindow.print();
                printWindow.close();
            }, 250);
        });

        // Calculation mode card selection
        $('input[name="qr_attendance_calculation_mode"]').on('change', function () {
            $('.qr-calc-card').removeClass('is-active');
            $(this).closest('.qr-calc-card').addClass('is-active');
        });

        // A saved key loads the picker without another click.
        if (settings.mapsApiKey) {
            loadMap();
        }
    });
})();
