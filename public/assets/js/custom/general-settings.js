function formSuccessFunction(response) {
    setTimeout(() => {
        window.location.reload();
    }, 4000);
}

$(document).ready(function () {
    // Domain settings toggle functionality
    function toggleFields() {
        if ($('#defaultDomain').is(':checked')) {
            $('.defaultDomainGroup').show().find('input').prop('disabled', false);
            $('.customDomainGroup').hide().find('input').prop('disabled', true);
            $('.serverinfo').hide().find('input');
        } else if ($('#customDomain').is(':checked')) {
            $('.customDomainGroup').show().find('input').prop('disabled', false);
            $('.serverinfo').show().find('input');
            $('.defaultDomainGroup').hide().find('input').prop('disabled', true);
        }
    }
    $("input[name='domain_type']").on('change', toggleFields);
    toggleFields();

    // Image Upload Click Handlers
    $('.image-upload-placeholder').on('click', function () {
        $(this).siblings('input[type="file"]').trigger('click');
    });

    // Image preview functionality
    $('input[type="file"].image-upload-input').on('change', function () {
        var input = this;
        var placeholder = $(this).siblings('.image-upload-placeholder');
        var previewImg = placeholder.find('img');

        if (input.files && input.files[0]) {
            var file = input.files[0];
            var reader = new FileReader();
            reader.onload = function (e) {
                previewImg.attr('src', e.target.result);
            }
            reader.readAsDataURL(file);
        }
    });





    $('#roll-number-order').on('change', function () {
        var val = $(this).val();
        if (val) {
            var parts = val.split(',');
            $('#roll-number-sort-column').val(parts[0]);
            $('#roll-number-sort-order').val(parts[1]);
        } else {
            $('#roll-number-sort-column').val('');
            $('#roll-number-sort-order').val('');
        }
    });
    // 6. File upload info display for extra fields
    $('.file-upload-default').on('change', function () {
        $(this).siblings('.file-upload-info').val($(this)[0].files[0].name);
    });
    // 7. Teacher Student Leave Permissions Toggle
    $('#teacher-leave-permissions-toggle').on('change', function () {
        var enabled = $(this).is(':checked') ? 1 : 0;
        var $toggle = $(this);
        var url = $toggle.data('url') || '/school-settings/toggle-teacher-leave-permissions';
        $.ajax({
            url: url,
            type: 'POST',
            data: {
                enabled: enabled,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                if (response.error) {
                    $toggle.prop('checked', !$toggle.is(':checked'));
                    $.toast({
                        text: response.message,
                        icon: 'error',
                        showHideTransition: 'slide',
                        position: 'top-right',
                        loaderBg: '#f2a654'
                    });
                } else {
                    $.toast({
                        text: response.message,
                        icon: 'success',
                        showHideTransition: 'slide',
                        position: 'top-right',
                        loaderBg: '#f96868'
                    });
                }
            },
            error: function () {
                $toggle.prop('checked', !$toggle.is(':checked'));
                $.toast({
                    text: (window.trans ? window.trans['something_went_wrong'] : 'Something went wrong. Please try again.'),
                    icon: 'error',
                    showHideTransition: 'slide',
                    position: 'top-right',
                    loaderBg: '#f2a654'
                });
            }
        });
    });


    function previewImage(input, previewSelector) {
        if (input.files && input.files[0]) {
            var file = input.files[0];

            var reader = new FileReader();
            reader.onload = function (e) {
                $(previewSelector).attr('src', e.target.result);

                // Update the file name in the input field
                $(input).closest('.form-group').find('.file-upload-info').val(file.name);
            }
            reader.readAsDataURL(file);
        }
    }
});

$('#toggle-maps-key').on('click', function (e) {
    e.preventDefault();
    var $key = $('#google_maps_api_key');
    var isPassword = $key.attr('type') === 'password';
    $key.attr('type', isPassword ? 'text' : 'password');
    $(this).find('i').toggleClass('fa-eye fa-eye-slash');
});


