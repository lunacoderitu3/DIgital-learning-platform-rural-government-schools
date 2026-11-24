/**
 * Pay Compulsory Fees – Premium UI JavaScript
 * Accordion toggles + Payment logic (extracted from inline script)
 */

$(document).ready(function () {
    'use strict';

    // Datepicker init
    $('.datepicker-popup').datepicker({
        format: "dd-mm-yyyy",
        rtl: isRTL()
    }).datepicker("setDate", 'now');

    // --- Accordion Toggle ---
    // Commented out to prevent conflict with Bootstrap 5 native collapse handling.
    // Bootstrap 5 natively handles elements with data-bs-toggle="collapse" and correctly updates classes/attributes.
    /*
    $(document).on('click', '.pc-accordion-btn', function (e) {
        e.preventDefault();
        var target = $(this).attr('data-target') || $(this).attr('href');
        if (target) {
            $(target).collapse('toggle');
        }
    });
    */
});

function successFunction() {
    window.location.reload();
}

/**
 * Initialise the payment logic for the compulsory fee page.
 *
 * @param {Object} cfg Configuration object passed from the Blade view:
 *   - currencySymbol {string}
 *   - studentAdvance {number}
 *   - remainingAmount {number}
 *   - minPayableAmount {number}
 *   - totalPayableAmount {number}
 *   - overdueInstallmentIds {Array<number>}
 *   - instalmentFeesFullPaid {number}
 *   - hasInstallments {boolean}
 */
function initPayCompulsory(cfg) {
   'use strict';

    var currencySymbol     = cfg.currencySymbol;
    var studentAdvance     = cfg.studentAdvance;
    var remainingAmount    = cfg.remainingAmount;
    var minPayableAmount   = cfg.minPayableAmount;
    var totalPayableAmount = cfg.totalPayableAmount;
    var overdueInstallmentIds = cfg.overdueInstallmentIds;
    var instalmentFeesFullPaid = cfg.instalmentFeesFullPaid;

    // Collect Installments Data (Due Amounts)
    var installments = [];
    $('.installment-amount').each(function () {
        var amount = parseFloat($(this).data('amount'));
        if (amount > 0) {
            installments.push(amount);
        }
    });

    // Cash / Cheque toggle
    $('input[name="mode"]').on('change', function () {
        if ($(this).val() == 2) {
            $('#cheque_no_div').removeClass('d-none');
        } else {
            $('#cheque_no_div').addClass('d-none');
        }
    });

    // Payment Mode Toggle Logic (Installment vs Full)
    // Support both radio and custom segmented control
    $('.payment-mode-toggle').on('change', function () {
        calculateAutoFill($(this).val());
    });

    // Custom mode buttons (premium UI segmented control)
    $(document).on('click', '.pc-mode-btn', function () {
        var mode = $(this).data('mode');
        $('.pc-mode-btn').removeClass('active');
        $(this).addClass('active');
        // Also update hidden radio if present
        $('.payment-mode-toggle[value="' + mode + '"]').prop('checked', true).trigger('change');
    });

    function calculateAutoFill(mode) {
        var fillAmount = 0;
        var inputField = $('#enter_amount');
        var paymentMode = mode || ($('.payment-mode-toggle:checked').val());

        // Reset Functionality
        inputField.attr('readonly', false);
        $('.installment-card, .pc-timeline-item').removeClass('border-danger shadow-lg highlight-overdue');

        if (mode == 1) { // Installment Mode
            if (minPayableAmount > 0) {
                fillAmount = minPayableAmount;

                if (overdueInstallmentIds.length > 0) {
                    overdueInstallmentIds.forEach(function (id) {
                        var card = $('.installment-card[data-id="' + id + '"], .pc-timeline-item[data-id="' + id + '"]');
                        card.addClass('border-danger shadow-lg highlight-overdue');
                    });
                } else {
                    var firstUnpaid = $('.installment-card[data-status="0"], .installment-card[data-status="2"], .pc-timeline-item[data-status="0"], .pc-timeline-item[data-status="2"]').first();
                    if (firstUnpaid.length) {
                        firstUnpaid.addClass('border-danger shadow-lg');
                    }
                }
            } else {
                if (installments.length > 0) {
                    fillAmount = installments[0];
                    var firstUnpaid = $('.installment-card[data-status="0"], .installment-card[data-status="2"], .pc-timeline-item[data-status="0"], .pc-timeline-item[data-status="2"]').first();
                    if (firstUnpaid.length) {
                        firstUnpaid.addClass('border-danger shadow-lg');
                    }
                }
            }
        } else { // Full Amount Mode
            fillAmount = totalPayableAmount;

            $('.installment-card, .pc-timeline-item').each(function () {
                var status = $(this).data('status');
                if (status == 0 || status == 2) {
                    $(this).addClass('border-danger shadow-lg');
                }
            });

            inputField.attr('readonly', true);
        }

        // Adjust for Advance
        var netPayable = Math.max(0, fillAmount - studentAdvance);
        inputField.val(netPayable.toFixed(2));

        // Set Max Attribute
        var effectiveMax = Math.max(0, remainingAmount - studentAdvance);

        if (paymentMode == 0) {
            if (typeof instalmentFeesFullPaid !== 'undefined' && instalmentFeesFullPaid > 0) {
                effectiveMax = Math.max(0, instalmentFeesFullPaid - studentAdvance);
            }
            inputField.val(effectiveMax.toFixed(2));
            inputField.attr('min', effectiveMax.toFixed(2));
        } else {
            var effectiveMin = Math.max(0, minPayableAmount - studentAdvance);
            if (effectiveMin > 0) {
                inputField.attr('min', effectiveMin.toFixed(2));
            } else {
                inputField.attr('min', 1);
            }
        }
        inputField.attr('max', effectiveMax.toFixed(2));

        inputField.trigger('input');
    }

    // Initial Setup
    var initialMode = $('.payment-mode-toggle:checked').val();
    if (typeof initialMode === 'undefined') initialMode = 1;
    calculateAutoFill(initialMode);

    // Dynamic Validation on Input
    $('#enter_amount').on('input', function () {
        var inputAmount = parseFloat($(this).val());
        var feedback = $('#amount_feedback');
        var submitBtn = $('#collect_btn');
        var maxVal = parseFloat($(this).attr('max'));
        var minVal = parseFloat($(this).attr('min')) || 0;

        feedback.hide();
        submitBtn.prop('disabled', false);

        if (isNaN(inputAmount) || inputAmount <= 0) {
            submitBtn.prop('disabled', true);
            return;
        }

        if (inputAmount < (minVal - 0.01)) {
            feedback.text(window.pcTranslations.minPaymentRequired + ' ' + currencySymbol + minVal.toFixed(2) + ' ' + window.pcTranslations.toCoverOverdue)
                .removeClass('text-success text-warning').addClass('text-danger').show();
            submitBtn.prop('disabled', true);
            return;
        }

        if (inputAmount > (maxVal + 0.01)) {
            var advance = inputAmount - maxVal;
            feedback.text(window.pcTranslations.excessAmount + ' ' + currencySymbol + advance.toFixed(2) + ' ' + window.pcTranslations.willBeAdvance)
                .removeClass('text-danger text-success').addClass('text-warning').show();
        } else if (inputAmount > (minVal + 0.01)) {
            var extra = inputAmount - minVal;
            if (Math.abs(inputAmount - maxVal) < 1.00) {
                feedback.text(window.pcTranslations.clearingAllDues)
                    .removeClass('text-danger text-warning').addClass('text-success').show();
            } else {
                feedback.text(window.pcTranslations.paymentCoversOverdue + ' + ' + currencySymbol + extra.toFixed(2) + ' ' + window.pcTranslations.towardsFuture)
                    .removeClass('text-danger text-warning').addClass('text-success').show();
            }
        } else {
            feedback.hide();
        }
    });

    // Delete Transaction Logic
    $(document).on('click', '.delete-transaction-btn', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        var url = window.pcDeleteUrl.replace(':id', id);

        showDeletePopupModal(url, {
            successCallBack: function () {
                setTimeout(function () {
                    window.location.reload();
                }, 500);
            }
        });
    });

    // Copy Transaction ID logic
    $(document).on('click', '.copy-txn-btn', function (e) {
        e.preventDefault();
        var textToCopy = $(this).data('clipboard-text');
        var btn = $(this);
        
        navigator.clipboard.writeText(textToCopy).then(function() {
            var originalText = btn.html();
            btn.html('<i class="fa fa-check"></i> ' + window.pcTranslations.copied || 'COPIED');
            setTimeout(function() {
                btn.html(originalText);
            }, 2000);
        }).catch(function(err) {
            console.error('Failed to copy text: ', err);
        });
    });
}
