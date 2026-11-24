"use strict";

// Global variables representing database state
// CLASSES is injected via Blade template
let STUDENTS = [];
let FEE_STRUCTURES = [];

// Utility toast notifications triggering method
function triggerToast(message, type = 'success') {
    const toastEl = $('#actionToast');
    const iconEl = $('#toastIcon');

    toastEl.removeClass('bg-success bg-danger bg-warning');
    iconEl.removeClass('fa-check-circle fa-exclamation-triangle');

    if (type === 'error') {
        toastEl.addClass('bg-danger');
        iconEl.addClass('fa-exclamation-triangle');
    } else if (type === 'warning') {
        toastEl.addClass('bg-warning text-dark');
        iconEl.addClass('fa-exclamation-triangle');
    } else {
        toastEl.addClass('bg-success');
        iconEl.addClass('fa-check-circle');
    }

    $('#toastMessage').text(message);
    toastEl.toast('show');
}

// Initialize lists & datastores
function loadClassAndStudentOptions() {
    // Populate classes
    let classHtml = '<option value="">Select Class</option>';
    CLASSES.forEach(c => {
        classHtml += `<option value="${c.id}">${c.name}</option>`;
    });
    $('#classSelector').html(classHtml);
}

// Listen to class selector change
$('#classSelector').on('change', function () {
    const classId = $(this).val();

    let studentHtml = '<option value="">Select Student</option>';
    $('#studentSelector').html(studentHtml);
    $('#studentProfileWidget').hide();

    // Reset all subsequent blocks
    $('#feeStructureSelector').html('<option value="">Select Fee Structure</option>').val('');
    $('#section2Block, #section3Block, #section4Block, #feeStructureInfoBox, #feeComponentsWidget, #livePreviewContainer').hide();
    $('#livePreviewPlaceholder').show();
    $('#optionalFeesChecklist, #installmentChecklistTableBody').html('');
    $('#previewValidationBox').addClass('d-none').removeClass('d-flex');
    $('#discountValueInput').val('');
    $('#discountDescriptionInput').val('');
    $('#discountTargetToggleGroup button').first().trigger('click');
    $('#discountTypeToggleGroup button').first().trigger('click');
    $('input[name="scopeSelection"][value="Entire Fee Structure"]').prop('checked', true).trigger('change');

    if (!classId) return;

    // Fetch dynamic students for this class
    $.ajax({
        url: baseUrl + '/students/list-by-class-section',
        type: "GET",
        data: { class_id: classId },
        success: function (response) {
            STUDENTS = []; // clear current
            response.data.forEach(s => {
                let user = s.user; // user model is nested under student

                // Map to UI model
                STUDENTS.push({
                    id: user.id, // we use user ID for saving
                    name: user.full_name,
                    admNo: user.email,
                    classId: classId,
                    section: s.class_section.full_name,
                    studentIdStr: s.id
                });
            });

            STUDENTS.forEach(s => {
                studentHtml += `<option value="${s.id}">${s.name} (${s.admNo})</option>`;
            });
            $('#studentSelector').html(studentHtml);
        }
    });
});

// Listen to student selector change
$('#studentSelector').on('change', function () {
    const studentId = $(this).val();

    // Reset all subsequent blocks
    $('#feeStructureSelector').html('<option value="">Select Fee Structure</option>').val('');
    $('#section3Block, #section4Block, #feeStructureInfoBox, #feeComponentsWidget, #livePreviewContainer').hide();
    $('#livePreviewPlaceholder').show();
    $('#optionalFeesChecklist, #installmentChecklistTableBody').html('');
    $('#previewValidationBox').addClass('d-none').removeClass('d-flex');
    $('#discountValueInput').val('');
    $('#discountDescriptionInput').val('');
    $('#discountTargetToggleGroup button').first().trigger('click');
    $('#discountTypeToggleGroup button').first().trigger('click');
    $('input[name="scopeSelection"][value="Entire Fee Structure"]').prop('checked', true).trigger('change');

    if (!studentId) {
        $('#studentProfileWidget, #section2Block').hide();
        return;
    }

    const student = STUDENTS.find(s => s.id == studentId);
    const activeClass = CLASSES.find(c => c.id == student.classId);

    // Fetch associated structures to sum up amounts dynamically
    $.ajax({
        url: baseUrl + '/fees/discount/student-fees',
        type: "GET",
        data: { student_id: studentId },
        success: function (response) {
            FEE_STRUCTURES = [];
            let feeHtml = '<option value="">Select Fee Structure</option>';

            let accountTotalDues = 0;

            response.data.forEach(fs => {
                // Calculate total amount from fees_class_type if total_amount is undefined
                let totalAmount = fs.total_amount !== undefined ? parseFloat(fs.total_amount) : 0;
                if (fs.total_amount === undefined && fs.fees_class_type) {
                    fs.fees_class_type.forEach(fct => {
                        totalAmount += parseFloat(fct.amount);
                    });
                }

                let paidAmount = 0;
                if (fs.fees_paid) {
                    fs.fees_paid.forEach(fp => {
                        paidAmount += parseFloat(fp.amount);
                    });
                }

                let totalDiscount = 0;
                if (response.existing_discounts) {
                    response.existing_discounts.forEach(d => {
                        if (d.fees_id === fs.id) {
                            totalDiscount += parseFloat(d.discount_value);
                        }
                    });
                }

                let pendingAmount = Math.max(0, totalAmount - paidAmount - totalDiscount);

                // We just store a basic object first. The full details come when selected.
                FEE_STRUCTURES.push({
                    id: fs.id,
                    name: fs.name,
                    classId: fs.class_id,
                    totalAmount: totalAmount,
                    pendingAmount: pendingAmount,
                    paidAmount: paidAmount,
                    totalDiscount: totalDiscount
                });

                accountTotalDues += pendingAmount;
                feeHtml += `<option value="${fs.id}">${fs.name} (Gross Amount: ${globalCurrencySymbol}${totalAmount.toLocaleString()})</option>`;
            });
            $('#feeStructureSelector').html(feeHtml);

            // Populate Student profile widget
            $('#lblAdmissionNo').text(student.admNo);
            $('#lblStudentName').text(student.name);
            // console.log(activeClass);

            $('#lblClassSection').text(`${student.section}`);
            $('#lblTotalDues').text(`${globalCurrencySymbol}${accountTotalDues.toLocaleString()}`);

            $('#studentProfileWidget').show();
            $('#section2Block').slideDown();

            // Hide lower sections if they were open for a previous student
            $('#section3Block, #section4Block').hide();
        }
    });
});

// Listen to Target Fee selection changes
$('#feeStructureSelector').on('change', function () {
    const feeId = $(this).val();
    const studentId = $('#studentSelector').val();

    // Reset all subsequent blocks
    $('#section3Block, #section4Block, #feeStructureInfoBox, #feeComponentsWidget, #livePreviewContainer').hide();
    $('#livePreviewPlaceholder').show();
    $('#optionalFeesChecklist, #installmentChecklistTableBody').html('');
    $('#previewValidationBox').addClass('d-none').removeClass('d-flex');
    $('#discountValueInput').val('1000');
    $('#discountDescriptionInput').val('');

    if (!feeId || !studentId) {
        return;
    }

    // Fetch details of selected fee structure
    $.ajax({
        url: baseUrl + '/fees/discount/fee-details',
        type: "GET",
        data: { fee_id: feeId, student_id: studentId },
        success: function (response) {
            let feeData = response.data;
            let existingDiscounts = response.discounts;

            // Find in array to update it
            let fee = FEE_STRUCTURES.find(fs => fs.id == feeId);
            if (!fee) return;

            // totalAmount was already computed in getStudentFees. Let's make sure it's accurate.
            let totalAmount = fee.totalAmount;
            let pendingAmount = fee.pendingAmount; // Sync with previously computed pending

            fee.include_installment = feeData.include_fee_installments;
            fee.compulsoryComponents = [];
            fee.optionalComponents = [];
            fee.installments = [];
            fee.existingDiscounts = [];

            // Add existing discounts
            let installmentDiscounts = {};
            existingDiscounts.forEach(d => {
                fee.existingDiscounts.push({
                    id: d.id,
                    name: d.discount_name,
                    value: parseFloat(d.discount_value),
                    type: d.discount_type,
                    scope: d.discount_scope,
                    totalDiscount: parseFloat(d.discount_value),
                    items: d.items
                });

                // Map installment-wise discounts
                if (d.items) {
                    d.items.forEach(item => {
                        if (item.fee_type === 'installment') {
                            installmentDiscounts[item.fee_type_id] = (installmentDiscounts[item.fee_type_id] || 0) + parseFloat(item.discount_amount);
                        }
                    });
                }
            });
            fee.pendingAmount = Math.max(0, pendingAmount);

            let paidOptionalsData = response.paid_optionals || [];

            // Add components
            feeData.fees_class_type.forEach(fct => {
                if (fct.optional == 0) {
                    fee.compulsoryComponents.push({
                        id: fct.id,
                        name: fct.fees_type.name,
                        amount: parseFloat(fct.amount),
                        pending: parseFloat(fct.amount)
                    });
                } else {
                    let paidOptionalAmount = 0;
                    let pOptData = paidOptionalsData.find(p => p.fees_class_id == fct.id);
                    if (pOptData) {
                        paidOptionalAmount = parseFloat(pOptData.total_paid);
                    }

                    // Add existing discounts applied to this optional fee component?
                    // Optional fees discounts might not be subtracted from pending here because the existing logic doesn't subtract discounts from optional pending yet either, but let's just subtract paid amount for now.
                    let optionalDiscount = 0;
                    if (fee.existingDiscounts) {
                        fee.existingDiscounts.forEach(d => {
                            if (d.scope === 'optional' && d.items) {
                                d.items.forEach(item => {
                                    if (item.fee_type === 'optional' && item.fee_type_id == fct.id) {
                                        optionalDiscount += parseFloat(item.discount_amount);
                                    }
                                });
                            }
                        });
                    }

                    fee.optionalComponents.push({
                        id: fct.id,
                        name: fct.fees_type.name,
                        amount: parseFloat(fct.amount),
                        pending: Math.max(0, parseFloat(fct.amount) - paidOptionalAmount - optionalDiscount),
                        assigned: true // Assuming assigned for simulation
                    });
                }
            });

            if (feeData.include_fee_installments) {
                let paidData = response.paid_installments || [];
                feeData.installments.forEach(inst => {
                    let paidAmount = 0;
                    let pData = paidData.find(p => p.installment_id == inst.id);
                    if (pData) {
                        paidAmount = parseFloat(pData.total_paid);
                    }
                    let instDiscount = installmentDiscounts[inst.id] || 0;
                    fee.installments.push({
                        id: inst.id,
                        name: inst.name,
                        amount: parseFloat(inst.installment_amount),
                        paid: paidAmount,
                        discount: instDiscount
                    });
                });
            }

            renderFeeStructureInfo(fee);
            $('#section3Block, #section4Block').slideDown();
        }
    });
});

function renderFeeStructureInfo(fee) {
    // Summary panel data injection
    $('#lblFS_Total').text(`${globalCurrencySymbol}${fee.totalAmount.toLocaleString()}`);
    $('#lblFS_Pending').text(`${globalCurrencySymbol}${fee.pendingAmount.toLocaleString()}`);

    // Identify fee components combinations
    let classificationText = '';
    const hasCompulsory = fee.compulsoryComponents && fee.compulsoryComponents.length > 0;
    const hasOptional = fee.optionalComponents && fee.optionalComponents.filter(o => o.assigned).length > 0;

    if (hasCompulsory && hasOptional) {
        classificationText = 'Both Compulsory and Optional Components';
    } else if (hasCompulsory) {
        classificationText = 'Compulsory Fee Components Only';
    } else if (hasOptional) {
        classificationText = 'Optional Fee Components Only';
    } else {
        classificationText = 'No active components found';
    }
    $('#lblFS_Classification').text(classificationText);
    $('#feeStructureInfoBox').show();

    // Compulsory and Optional components breakdown load
    let compulsoryHtml = '';
    if (hasCompulsory) {
        fee.compulsoryComponents.forEach(c => {
            compulsoryHtml += `
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-secondary">${c.name}</span>
                    <span class="fw-bold">${globalCurrencySymbol}${c.amount.toLocaleString()}</span>
                </div>`;
        });
    } else {
        compulsoryHtml = '<span class="text-muted font-italic" style="font-size: 10px;">No compulsory components</span>';
    }
    $('#lblCompulsoryComponentsList').html(compulsoryHtml);

    let optionalHtml = '';
    if (hasOptional) {
        fee.optionalComponents.filter(o => o.assigned).forEach(o => {
            optionalHtml += `
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-secondary">${o.name}</span>
                    <span class="fw-bold">${globalCurrencySymbol}${o.amount.toLocaleString()}</span>
                </div>`;
        });
    } else {
        optionalHtml = '<span class="text-muted font-italic" style="font-size: 10px;">No active optional components</span>';
    }
    $('#lblOptionalComponentsList').html(optionalHtml);

    $('#feeComponentsWidget').show();

    // Load Installments configurations
    syncScopeSelectionRadios(fee);

    syncActiveTargetFields();
    calculateLiveExemptionPreview();
}

function syncScopeSelectionRadios(fee) {
    const targetType = $('#discountTargetToggleGroup button.active').data('target');

    if (targetType === 'Optional Fees') {
        $('input[name="scopeSelection"][value="Entire Fee Structure"]').prop('checked', true);
        return;
    }

    if (fee.include_installment == 1 || fee.include_installment == true) {
        $('input[name="scopeSelection"][value="Installment Wise"]').prop('checked', true);
        $('#scopeInstallmentLabel').removeClass('opacity-50').find('input').removeAttr('disabled');
        $('#scopeEntireLabel').addClass('opacity-50').find('input').attr('disabled', true);
    } else {
        $('input[name="scopeSelection"][value="Entire Fee Structure"]').prop('checked', true);
        $('#scopeEntireLabel').removeClass('opacity-50').find('input').removeAttr('disabled');
        $('#scopeInstallmentLabel').addClass('opacity-50').find('input').attr('disabled', true);
    }
}

function syncActiveTargetFields() {
    const targetType = $('#discountTargetToggleGroup button.active').data('target');
    const feeId = $('#feeStructureSelector').val();
    if (!feeId) return;

    const fee = FEE_STRUCTURES.find(fs => fs.id == feeId);

    // Handle optional checklist panel rendering
    if (targetType === 'Optional Fees') {
        let checkboxHtml = '';
        const activeOptionals = fee.optionalComponents ? fee.optionalComponents.filter(o => o.assigned) : [];

        if (activeOptionals.length > 0) {
            activeOptionals.forEach(opt => {
                const isPaid = opt.pending === 0;
                checkboxHtml += `
                    <div class="col-md-6">
                        <div class="px-3 bg-white rounded border d-flex justify-content-between align-items-center" style="min-height: 52px;">
                            <div class="form-check mt-0 mb-0">
                                <label class="form-check-label fw-bold text-dark mb-0" style="font-size: 12px; cursor: pointer;" for="chkOpt_${opt.id}">
                                    <input class="form-check-input optional-checkbox-item" type="checkbox" id="chkOpt_${opt.id}" value="${opt.id}" data-id="${opt.id}" data-pending="${opt.pending}" data-original="${opt.amount}" ${isPaid ? 'disabled' : 'checked'}>
                                    ${opt.name}
                                    <i class="input-helper"></i>
                                </label>
                            </div>
                            <span class="text-muted" style="font-size: 11px; font-weight: 500;">
                                ${isPaid ? '<span class="badge badge-success">' + window.trans['paid'] + '</span>' : `${globalCurrencySymbol}${opt.amount}`}
                            </span>
                        </div>
                    </div>`;
            });
        } else {
            checkboxHtml = '<div class="col-12 text-center text-muted font-italic" style="font-size:11px;">No active optional components.</div>';
        }
        $('#optionalFeesChecklist').html(checkboxHtml);
        $('#optionalFeesChecklistContainer').slideDown(150);
        $('#discountScopeRadiosContainer').slideUp(150);
        $('input[name="scopeSelection"][value="Entire Fee Structure"]').prop('checked', true); // Reset implicit entire
    } else {
        $('#optionalFeesChecklistContainer').slideUp(150);
        $('#discountScopeRadiosContainer').slideDown(150);
        syncScopeSelectionRadios(fee);
    }

    // Handle installments rendering
    const isInstallmentWise = $('input[name="scopeSelection"]:checked').val() === 'Installment Wise';
    if (isInstallmentWise && (fee.include_installment == 1 || fee.include_installment == true)) {
        let instHtml = '';
        fee.installments.forEach(inst => {
            const discount = inst.discount || 0;
            const isFullyPaid = inst.amount - discount - inst.paid <= 0;
            instHtml += `
                <tr>
                    <td class="text-center">
                        <input class="form-check-input installment-checkbox-item" type="checkbox" value="${inst.id}" data-id="${inst.id}" data-original="${inst.amount}" data-paid="${inst.paid}" data-discount="${discount}" ${isFullyPaid ? 'disabled' : 'checked'}>
                    </td>
                    <td class="fw-bold">${inst.name}</td>
                    <td>${globalCurrencySymbol}${inst.amount}</td>
                    <td class="text-success">${globalCurrencySymbol}${inst.paid}</td>
                    <td class="fw-bold">
                        ${isFullyPaid ? '<span class="badge badge-success">Paid</span>' : `<span class="text-danger">${globalCurrencySymbol}${Math.max(0, inst.amount - discount - inst.paid)}</span>`}
                    </td>
                </tr>`;
        });
        $('#installmentChecklistTableBody').html(instHtml);
        $('#installmentListContainer').slideDown(150);
    } else {
        $('#installmentListContainer').slideUp(150);
    }

    bindCheckboxChangeEvents();
}

// Toggle buttons group click handlers
$('#discountTargetToggleGroup button').on('click', function () {
    $('#discountTargetToggleGroup button').removeClass('active');
    $(this).addClass('active');
    syncActiveTargetFields();
    calculateLiveExemptionPreview();
});

// $('#discountTypeToggleGroup button').on('click', function () {
//     $('#discountTypeToggleGroup button').removeClass('active');
//     $(this).addClass('active');

//     const type = $(this).data('type');
//     if (type === 'Percentage') {
//         $('#lblDiscountValueInput').text('Concession Percentage Value (%) *');
//         $('#discountValueInput').attr('placeholder', 'E.g., 10').val('10');
//     } else {
//         $('#lblDiscountValueInput').text('Concession Amount Value (' + globalCurrencySymbol + ') *');
//         $('#discountValueInput').attr('placeholder', 'E.g., 1000').val('1000');
//     }
//     calculateLiveExemptionPreview();
// });
$('#discountTypeToggleGroup button').on('click', function () {

    $('#discountTypeToggleGroup button').removeClass('active');
    $(this).addClass('active');

    const type = $(this).data('type');

    if (type === 'Percentage') {

        $('#lblDiscountValueInput').text(
            window.translations.concessionPercentageValue
        );

        $('#discountValueInput')
            .attr('placeholder', window.translations.eg10)
            .val('10');

    } else {

        $('#lblDiscountValueInput').text(
            `${window.translations.concessionAmountValue} (${globalCurrencySymbol}) *`
        );

        $('#discountValueInput')
            .attr('placeholder', window.translations.eg1000)
            .val('1000');
    }

    calculateLiveExemptionPreview();
});

$('input[name="scopeSelection"]').on('change', function () {
    syncActiveTargetFields();
    calculateLiveExemptionPreview();
});

$('#discountValueInput').on('input', function () {
    calculateLiveExemptionPreview();
});

function bindCheckboxChangeEvents() {
    $('.optional-checkbox-item, .installment-checkbox-item').off('change').on('change', function () {
        calculateLiveExemptionPreview();
    });
}

function calculateLiveExemptionPreview() {
    const studentId = $('#studentSelector').val();
    const feeId = $('#feeStructureSelector').val();

    if (!studentId || !feeId) {
        $('#livePreviewContainer').hide();
        $('#livePreviewPlaceholder').show();
        return;
    }

    const student = STUDENTS.find(s => s.id == studentId);
    const fee = FEE_STRUCTURES.find(fs => fs.id == feeId);

    // Fetch target fields
    const targetType = $('#discountTargetToggleGroup button.active').data('target');
    const scopeSelection = $('input[name="scopeSelection"]:checked').val();
    const discType = $('#discountTypeToggleGroup button.active').data('type');
    const discVal = parseFloat($('#discountValueInput').val()) || 0;

    let scopeOriginalAmount = 0;
    let scopePendingAmount = 0;
    let isValid = true;
    let validationMsg = '';

    // Sum up optionals if active
    if (targetType === 'Optional Fees') {
        let optionalPendingSum = 0;
        let optionalOriginalSum = 0;
        let checkedCount = 0;

        $('.optional-checkbox-item:checked').each(function () {
            optionalPendingSum += parseFloat($(this).data('pending'));
            optionalOriginalSum += parseFloat($(this).data('original'));
            checkedCount++;
        });

        scopeOriginalAmount = optionalOriginalSum;
        scopePendingAmount = optionalPendingSum;
        $('#lblSelectedOptionalsSum').text(`${globalCurrencySymbol}${scopePendingAmount.toLocaleString()}`);
        $('#lblPreviewOptionalsCount').text(`${checkedCount} checked`);
        $('#lblPreviewOptionalListRow').show();

        if (checkedCount === 0) {
            isValid = false;
            validationMsg = 'Please check at least one optional fee checkbox.';
        }
    } else {
        $('#lblPreviewOptionalListRow').hide();

        if (targetType === 'Compulsory Fees') {
            // Sum of compulsory pending
            scopeOriginalAmount = fee.compulsoryComponents.reduce((acc, c) => acc + c.amount, 0);
            if (fee.include_installment == 1 || (fee.installments && fee.installments.length > 0)) {
                scopePendingAmount = fee.compulsoryComponents.reduce((acc, c) => acc + c.pending, 0);
            } else {
                scopePendingAmount = Math.max(0, scopeOriginalAmount - (fee.paidAmount || 0) - (fee.totalDiscount || 0));
            }
        } else {
            // Entire Fee Structure
            scopeOriginalAmount = fee.totalAmount;
            scopePendingAmount = fee.pendingAmount; // Simplified, in real app subtract paid
        }
    }

    // Sum up installments if active
    const isInstallmentWise = scopeSelection === 'Installment Wise';
    let checkedInstCount = 0;
    let installmentOriginalCombined = 0;
    let installmentPendingCombined = 0;

    if (isInstallmentWise) {
        $('.installment-checkbox-item:checked').each(function () {
            const original = parseFloat($(this).data('original'));
            const paid = parseFloat($(this).data('paid'));
            const discount = parseFloat($(this).data('discount')) || 0;
            installmentOriginalCombined += original;
            installmentPendingCombined += Math.max(0, original - discount - paid);
            checkedInstCount++;
        });

        $('#lblSelectedInstallmentsSum').text(`${globalCurrencySymbol}${installmentPendingCombined.toLocaleString()}`);
        $('#lblPreviewInstallmentsCount').text(`${checkedInstCount} checked`);
        $('#lblPreviewInstallmentListRow').show();

        if (checkedInstCount === 0) {
            isValid = false;
            validationMsg = 'Please check at least one installment checkbox.';
        }

        // Installments override basic bounds
        scopeOriginalAmount = installmentOriginalCombined;
        scopePendingAmount = installmentPendingCombined;
    } else {
        $('#lblPreviewInstallmentListRow').hide();
    }

    // Live calculator reduction logic
    let computedDiscount = 0;
    const updatedInstallments = [];

    if (discVal <= 0) {
        computedDiscount = 0;
        isValid = false;
        validationMsg = 'Please enter a valid concession value greater than zero.';
    } else if (discType === 'Percentage') {
        if (discVal > 100) {
            isValid = false;
            validationMsg = 'Percentage concession limit cannot exceed 100%';
        } else {
            if (isInstallmentWise) {
                // Apply percentage per selected installment
                $('.installment-checkbox-item:checked').each(function () {
                    const original = parseFloat($(this).data('original'));
                    const paid = parseFloat($(this).data('paid'));
                    const discount = parseFloat($(this).data('discount')) || 0;
                    const pending = Math.max(0, original - discount - paid);
                    let instDisc = Math.round((discVal / 100) * original);
                    if (instDisc > pending) instDisc = pending; // capped

                    computedDiscount += instDisc;
                    updatedInstallments.push({
                        name: fee.installments.find(i => i.id == $(this).data('id')).name,
                        original: original,
                        final: Math.max(0, original - discount - instDisc)
                    });
                });
            } else {
                computedDiscount = Math.round((discVal / 100) * scopeOriginalAmount);
                if (computedDiscount > scopePendingAmount) {
                    computedDiscount = scopePendingAmount; // capped
                }
            }
        }
    } else { // Fixed direct reduction
        if (isInstallmentWise) {
            // Check if fixed amount exceeds any selected installment pending
            let hasExceeded = false;
            $('.installment-checkbox-item:checked').each(function () {
                const original = parseFloat($(this).data('original'));
                const paid = parseFloat($(this).data('paid'));
                const discount = parseFloat($(this).data('discount')) || 0;
                const pending = Math.max(0, original - discount - paid);

                if (discVal > pending) {
                    hasExceeded = true;
                }

                computedDiscount += discVal;
                updatedInstallments.push({
                    name: fee.installments.find(i => i.id == $(this).data('id')).name,
                    original: original,
                    final: Math.max(0, original - discount - discVal)
                });
            });

            if (hasExceeded) {
                isValid = false;
                validationMsg = 'Fixed discount per installment cannot exceed individual pending outstanding limits.';
            }
        } else {
            if (discVal > scopePendingAmount) {
                isValid = false;
                validationMsg = `Concession value ${globalCurrencySymbol}${discVal.toLocaleString()} exceeds the current outstanding dues limit of ${globalCurrencySymbol}${scopePendingAmount.toLocaleString()}`;
            }
            computedDiscount = discVal;
        }
    }

    // Exemption outputs rendering
    $('#lblPreviewInitials').text(student.name.charAt(0) + (student.name.split(' ')[1] ? student.name.split(' ')[1].charAt(0) : ''));
    $('#lblPreviewStudentName').text(student.name);
    $('#lblPreviewStudentAdm').text(student.admNo);
    $('#lblPreviewStructureName').text(fee.name);
    $('#lblPreviewTargetType').text(`${targetType} (${scopeSelection})`);
    $('#lblPreviewOriginalAmount').text(`${globalCurrencySymbol}${scopePendingAmount.toLocaleString()}`);
    $('#lblPreviewValType').text(discType === 'Percentage' ? `${discVal}% (Percentage)` : `${globalCurrencySymbol}${discVal.toLocaleString()} (Fixed Amount)`);
    $('#lblPreviewExemptionReduction').text(`- ${globalCurrencySymbol}${computedDiscount.toLocaleString()}`);

    // Installment dynamic updates list
    if (isInstallmentWise && updatedInstallments.length > 0) {
        let updateHtml = '';
        updatedInstallments.forEach(u => {
            updateHtml += `
                <div class="p-2 rounded border d-flex justify-content-between align-items-center my-1" style="background-color: rgba(0,0,0,0.4); border-color: #334155;">
                    <span class="text-secondary">${u.name}:</span>
                    <span class="fw-bold text-white">${globalCurrencySymbol}${u.original.toLocaleString()} → <span class="text-success">${globalCurrencySymbol}${u.final.toLocaleString()}</span></span>
                </div>`;
        });
        $('#previewUpdatedInstallmentsList').html(updateHtml);
        $('#previewUpdatedInstallmentsContainer').show();
    } else {
        $('#previewUpdatedInstallmentsContainer').hide();
    }

    // Calculation outputs update
    const totalRemainingTarget = Math.max(0, scopePendingAmount - computedDiscount);
    const totalOutstandingNew = Math.max(0, fee.pendingAmount - computedDiscount);

    $('#lblPreviewOldTarget').text(`${globalCurrencySymbol}${scopePendingAmount.toLocaleString()}`);
    $('#lblPreviewRemainingTarget').text(`${globalCurrencySymbol}${totalRemainingTarget.toLocaleString()}`);
    $('#lblPreviewTotalOutstanding').text(`${globalCurrencySymbol}${totalOutstandingNew.toLocaleString()}`);

    // Show validation errors visually
    if (!isValid) {
        $('#lblPreviewValidationMsg').text(validationMsg);
        $('#previewValidationBox').removeClass('d-none').addClass('d-flex');
        $('#btnApplyDiscountSubmit').attr('disabled', true).addClass('opacity-50');
    } else {
        $('#previewValidationBox').addClass('d-none').removeClass('d-flex');
        $('#btnApplyDiscountSubmit').removeAttr('disabled').removeClass('opacity-50');
    }

    $('#livePreviewPlaceholder').hide();
    $('#livePreviewContainer').show();
}

// Listen to Click action for Apply Concession discount
$('#btnApplyDiscountSubmit').on('click', function (e) {
    e.preventDefault();

    const studentId = $('#studentSelector').val();
    const feeId = $('#feeStructureSelector').val();
    if (!studentId || !feeId) return;

    const targetType = $('#discountTargetToggleGroup button.active').data('target');
    let scopeSelection = $('input[name="scopeSelection"]:checked').val();
    const discType = $('#discountTypeToggleGroup button.active').data('type');
    const discVal = parseFloat($('#discountValueInput').val()) || 0;

    const nameTitle = $('#discountNameSelector').val();
    const discDate = $('#discountDateInput').val();
    const description = $('#discountDescriptionInput').val() || 'Concession applied via school workspace.';

    // Map scope back to DB enums
    let mappedScope = 'entire';
    if (targetType === 'Compulsory Fees') mappedScope = 'compulsory';
    if (targetType === 'Optional Fees') mappedScope = 'optional';

    const fee = FEE_STRUCTURES.find(fs => fs.id == feeId);

    // Compute details for items and main discount_value
    let computedDiscount = 0;
    let optionalFees = [];
    let installments = [];
    let includeInstallment = 0;

    let scopeOriginalAmount = 0;
    let scopePendingAmount = 0;

    if (mappedScope === 'optional') {
        let optionalPendingSum = 0;
        let optionalOriginalSum = 0;

        $('.optional-checkbox-item:checked').each(function () {
            optionalPendingSum += parseFloat($(this).data('pending'));
            optionalOriginalSum += parseFloat($(this).data('original'));
        });

        scopeOriginalAmount = optionalOriginalSum;
        scopePendingAmount = optionalPendingSum;

        let totalOptDisc = 0;
        if (discType === 'Percentage') {
            totalOptDisc = Math.round((discVal / 100) * scopeOriginalAmount);
        } else {
            totalOptDisc = discVal;
        }
        if (totalOptDisc > scopePendingAmount) {
            totalOptDisc = scopePendingAmount;
        }

        computedDiscount = totalOptDisc;

        let remainingDisc = totalOptDisc;
        const checkedItems = $('.optional-checkbox-item:checked');
        checkedItems.each(function (index) {
            const optId = $(this).val();
            const original = parseFloat($(this).data('original'));
            const pending = parseFloat($(this).data('pending'));

            let itemDisc = 0;
            if (index === checkedItems.length - 1) {
                itemDisc = remainingDisc;
            } else {
                if (scopePendingAmount > 0) {
                    itemDisc = Math.round((pending / scopePendingAmount) * totalOptDisc);
                } else {
                    itemDisc = 0;
                }
                itemDisc = Math.min(itemDisc, remainingDisc);
                remainingDisc -= itemDisc;
            }

            optionalFees.push({
                id: optId,
                original_amount: original,
                discount_amount: itemDisc,
                final_amount: Math.max(0, original - itemDisc)
            });
        });
    } else {
        // Compulsory Fees
        let compOriginalSum = 0;
        let compPendingSum = 0;
        if (fee.include_installment == 1 || (fee.installments && fee.installments.length > 0)) {
            fee.installments.forEach(i => {
                let pAmount = parseFloat(i.paid) || 0;
                let dAmount = parseFloat(i.discount) || 0;
                let instOriginal = parseFloat(i.amount) || 0;
                let instPending = Math.max(0, instOriginal - pAmount - dAmount);

                compOriginalSum += instOriginal;
                compPendingSum += instPending;
            });
        } else {
            compOriginalSum = fee.compulsoryComponents.reduce((acc, c) => acc + c.amount, 0);
            compPendingSum = Math.max(0, compOriginalSum - (fee.paidAmount || 0) - (fee.totalDiscount || 0));
        }

        scopeOriginalAmount = compOriginalSum;
        scopePendingAmount = compPendingSum;

        if (scopeSelection === 'Installment Wise') {
            includeInstallment = 1;
            $('.installment-checkbox-item:checked').each(function () {
                const instId = $(this).val();
                const original = parseFloat($(this).data('original'));
                const paid = parseFloat($(this).data('paid'));
                const discount = parseFloat($(this).data('discount')) || 0;
                const pending = Math.max(0, original - discount - paid);

                let instDisc = 0;
                if (discType === 'Percentage') {
                    instDisc = Math.round((discVal / 100) * original);
                    if (instDisc > pending) instDisc = pending;
                } else {
                    instDisc = discVal;
                    if (instDisc > pending) instDisc = pending;
                }

                computedDiscount += instDisc;
                installments.push({
                    id: instId,
                    original_amount: original,
                    discount_amount: instDisc,
                    final_amount: Math.max(0, original - discount - instDisc)
                });
            });
        } else {
            if (discType === 'Percentage') {
                computedDiscount = Math.round((discVal / 100) * scopeOriginalAmount);
            } else {
                computedDiscount = discVal;
            }
            if (computedDiscount > scopePendingAmount) {
                computedDiscount = scopePendingAmount;
            }
        }
    }

    // Submit to DB via AJAX
    let formData = {
        student_id: studentId,
        fees_id: feeId,
        discount_scope: mappedScope,
        discount_name: nameTitle,
        discount_type: discType.toLowerCase(),
        discount_value: computedDiscount,
        discount_date: discDate,
        description: description,
        optional_fees: optionalFees,
        include_installment: includeInstallment,
        installments: installments
    };

    const $submitBtn = $('#btnApplyDiscountSubmit');
    const originalText = $submitBtn.html();
    $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Saving...');

    $.ajax({
        url: baseUrl + '/fees/discount/store',
        type: "POST",
        data: formData,
        success: function (response) {
            if (response.error) {
                showErrorToast(response.message);
            } else {
                showSuccessToast(response.message);

                // Cleanup & Feedback
                $('#discountValueInput').val('1000');
                $('#discountDescriptionInput').val('');

                // Refresh Bootstrap Tables natively
                $('#table_list').bootstrapTable('refresh');
                $('#table_logs').bootstrapTable('refresh');

                // Switch view automatically to ledger tab
                $('#discountTab a[href="#ledger-pane"]').tab('show');
            }
        },
        error: function (xhr) {
            let errorMsg = 'An error occurred while saving. Please check your network and try again.';
            if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                let errors = xhr.responseJSON.errors;
                errorMsg = '';
                for (let field in errors) {
                    errorMsg += errors[field][0] + '<br>';
                }
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            }
            showErrorToast(errorMsg);
            console.error(xhr);
        },
        complete: function () {
            $submitBtn.prop('disabled', false).html(originalText);
        }
    });
});

window.discountEvents = {
    'click .view-breakdown-btn': function (e, value, row, index) {
        $('#lblB_StudentName').text(row.student_name);
        $('#lblB_StudentAdm').text(row.student && row.student.email ? row.student.email : '-');
        $('#lblB_ClassSection').text(row.student.student.class_section.full_name);
        $('#lblB_FeeStructure').text(row.fee_name);
        $('#lblB_ReliefType').text(row.discount_name);

        let compulsoryTotal = 0;
        let optionalTotal = 0;
        let entireTotal = 0;

        if (row.fee && row.fee.fees_class_type) {
            row.fee.fees_class_type.forEach(fct => {
                const amt = parseFloat(fct.amount) || 0;
                entireTotal += amt;
                if (fct.optional == 1) {
                    optionalTotal += amt;
                } else {
                    compulsoryTotal += amt;
                }
            });
        }

        let totalFees = entireTotal;
        if (row.discount_scope === 'compulsory') {
            totalFees = compulsoryTotal;
        } else if (row.discount_scope === 'optional') {
            totalFees = optionalTotal;
        }

        const discountAmount = parseFloat(row.discount_value) || 0;
        const netAdjusted = Math.max(0, totalFees - discountAmount);

        $('#lblB_TotalFees').text(`${globalCurrencySymbol}${totalFees.toLocaleString()}`);
        $('#lblB_DiscountAmount').text(`- ${globalCurrencySymbol}${discountAmount.toLocaleString()}`);
        $('#lblB_NetAdjusted').text(`${globalCurrencySymbol}${netAdjusted.toLocaleString()}`);

        let scopeText = 'Entire Structure';
        if (row.discount_scope === 'compulsory') {
            scopeText = 'Installment Wise (Compulsory Fees)';
        } else if (row.discount_scope === 'optional') {
            scopeText = 'Optional Fees';
        } else if (row.discount_scope === 'entire') {
            scopeText = 'Entire Fee Structure';
        }
        $('#lblB_ScopeBadge').text(scopeText.toUpperCase());

        let scopeHtml = '';
        const items = row.items || [];

        if (row.discount_scope !== 'entire' && items.length > 0) {
            scopeHtml = `
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle" style="font-size:12px;">
                        <thead class="table-light">
                            <tr>
                                <th>Target Element</th>
                                <th>Classification</th>
                                <th class="text-right text-end">Original</th>
                                <th class="text-right text-end text-success">Discount Applied</th>
                                <th class="text-right text-end text-primary">Final Adjusted</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            items.forEach(itm => {
                let name = '';
                let type = '';
                if (row.discount_scope === 'compulsory') {
                    name = itm.installment ? itm.installment.name : 'Installment';
                    type = 'Installment';
                } else {
                    name = itm.optional_fee_type && itm.optional_fee_type.fees_type ? itm.optional_fee_type.fees_type.name : 'Optional Fee';
                    type = 'Optional Fee';
                }

                scopeHtml += `
                    <tr>
                        <td class="fw-bold font-weight-bold">${name}</td>
                        <td><span class="badge badge-light text-dark">${type}</span></td>
                        <td class="text-right text-end">${globalCurrencySymbol}${parseFloat(itm.original_amount).toLocaleString()}</td>
                        <td class="text-right text-end text-success fw-bold font-weight-bold">- ${globalCurrencySymbol}${parseFloat(itm.discount_amount).toLocaleString()}</td>
                        <td class="text-right text-end text-primary fw-extrabold font-weight-bold" style="font-weight: 800;">${globalCurrencySymbol}${parseFloat(itm.final_amount).toLocaleString()}</td>
                    </tr>
                `;
            });
            scopeHtml += `
                        </tbody>
                    </table>
                </div>
            `;
        } else {
            scopeHtml = `
                <div class="p-3 bg-light text-secondary rounded text-center border" style="background-color: #f8fafc;">
                    <i class="fa fa-cubes mb-2 d-block text-primary" style="font-size: 1.5rem;"></i>
                    <p class="mb-0 fw-semibold text-dark font-weight-bold">Scope is set to: "${scopeText}"</p>
                    <span style="font-size: 11px;">The total discount concession of <strong>${globalCurrencySymbol}${discountAmount.toLocaleString()}</strong> has been factored across the gross pending ledger sum of the fee record.</span>
                </div>
            `;
        }

        $('#lblB_ScopeDetailsContainer').html(scopeHtml);
        $('#lblB_Date').text(row.discount_date || '-');
        $('#lblB_Description').text(row.description || 'No allocation remarks declared on account.');

        $('#discountBreakdownModal').modal('show');
    },
    'click .rollback-btn': function (e, value, row, index) {
        if (confirm('Are you sure you want to rollback this concession?')) {
            $.ajax({
                url: baseUrl + '/fees/discount/rollback/' + row.id,
                type: 'DELETE',
                success: function (response) {
                    if (response.error) {
                        showErrorToast(response.message);
                    } else {
                        showSuccessToast(response.message);
                        $('#table_list').bootstrapTable('refresh');
                        $('#table_logs').bootstrapTable('refresh');
                    }
                }
            });
        }
    }
};

$(document).ready(function () {

    loadClassAndStudentOptions();
});
