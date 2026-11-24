@php
    $isEditMode = $isEditMode ?? false;
    $formClass = $isEditMode ? 'edit-form' : 'create-form';
@endphp

<script>
    $(document).ready(function () {
        // Prepaid / Postpaid charge fields toggle (shared by both modes).
        $('.package_type').change(function (e) {
            e.preventDefault();
            if ($('input[name="type"]:checked').val() == 1) {
                $('.postpaid').slideDown(500);
                $('.prepaid').slideUp(500);
            } else {
                $('.prepaid').slideDown(500);
                $('.postpaid').slideUp(500);
            }
        });

        @if (!$isEditMode)
            // Create mode: reset restores the default (Postpaid) state.
            $('form.create-form').on('reset', function () {
                $('#postpaid').prop('checked', true);
                $('.postpaid').slideDown(500);
                $('.prepaid').slideUp(500);
            });
        @endif
    });
</script>

@if ($isEditMode)
    <script>
        // Edit mode: tooltip, success redirect, and sync the charge fields to the saved type on load.
        $('[data-bs-toggle="tooltip"]').tooltip();

        function formSuccessFunction(response) {
            setTimeout(() => {
                window.location.href = "{{ route('package.index') }}"
            }, 2000);
        }

        setTimeout(() => {
            window.onload = $('.package_type').trigger('change');
        }, 1000);
    </script>
@endif

<script>
    // Category-level "Select all" + live selected count for the Features section.
    // Additive UI behaviour only — it toggles the existing feature checkboxes and
    // never alters validation, submission, or the prepaid/postpaid logic above.
    $(document).ready(function () {
        function pkgRefreshGroup($card) {
            var $boxes = $card.find('.feature-checkbox');
            var total = $boxes.length;
            var checked = $boxes.filter(':checked').length;
            $card.find('.pkg-sh-count').text(checked + '/' + total);
            var $all = $card.find('.pkg-select-all');
            $all.prop('checked', total > 0 && checked === total);
            $all.prop('indeterminate', checked > 0 && checked < total);
        }

        $('.pkg-selectable-card').each(function () {
            pkgRefreshGroup($(this));
        });

        $('.pkg-select-all').on('change', function () {
            var $card = $(this).closest('.pkg-selectable-card');
            $card.find('.feature-checkbox').prop('checked', $(this).prop('checked'));
            pkgRefreshGroup($card);
        });

        $('.pkg-selectable-card').on('change', '.feature-checkbox', function () {
            pkgRefreshGroup($(this).closest('.pkg-selectable-card'));
        });

        $('form.{{ $formClass }}').on('reset', function () {
            setTimeout(function () {
                $('.pkg-selectable-card').each(function () {
                    pkgRefreshGroup($(this));
                });
            }, 0);
        });
    });
</script>
