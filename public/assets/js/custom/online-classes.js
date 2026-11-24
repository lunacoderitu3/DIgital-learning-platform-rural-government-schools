/**
 * Custom JS logic for Online Classes (Redesign)
 */
$(document).ready(function () {
    const config = window.onlineClassConfig;

    function currentRepeat() {
        return $('input[name="repeat"]:checked').val();
    }

    // Toggle the Start Date and End Date fields : hidden for "No Repeat".
    function toggleEndDate() {
        if (currentRepeat() === config.repeatNoRepeat) {
            $('#start_date_group').hide();
            $('#start_date').val('').removeAttr('required');
            $('#end_date_group').hide();
            $('#end_date').val('').removeAttr('required');
        } else {
            $('#start_date_group').show();
            $('#start_date').attr('required', 'required');
            $('#end_date_group').show();
            $('#end_date').attr('required', 'required');
        }
    }

    // For "Every Class Period" every slot is auto-included and locked.
    // For "No Repeat", periods are selectable and date is auto-calculated on the backend.
    function applyRepeatToPeriods() {
        const repeat = currentRepeat();
        const $boxes = $('#periods_container .period-checkbox');
        
        $boxes.each(function() {
            const $box = $(this);
            const $label = $box.closest('label');
            
            // Reset state
            $box.prop('disabled', false);
            $label.removeClass('opacity-50 text-decoration-line-through');
            
            if (repeat === config.repeatEveryClassPeriod) {
                $box.prop('checked', true).prop('disabled', true);
            }
        });

        if (repeat === config.repeatEveryClassPeriod) {
            $('.period-help').text(config.translations.all_periods_included);
        } else {
            $('.period-help').text(config.translations.select_one_or_more);
        }
    }

    function loadPeriods() {
        const subjectTeacherId = $('#subject_teacher_id').val();
        if (!subjectTeacherId) {
            $('#periods_wrapper').hide();
            $('#periods_container').empty();
            return;
        }
        $('#periods_wrapper').show();
        $('#periods_container').empty();
        $('#periods_empty').hide();
        $('#periods_loader').show();

        $.get(config.periodsUrl, { subject_teacher_id: subjectTeacherId }, function (response) {
            $('#periods_loader').hide();
            const periods = response.data || [];
            if (!periods.length) {
                $('#periods_empty').show();
                return;
            }
            let html = '';
            periods.forEach(function (p) {
                const badge = p.has_link
                    ? '<i class="fa-solid fa-link text-success ms-2" title="' + config.translations.configured + '"></i>'
                    : '';
                
                html += `
                    <div class="col-xl-3 col-md-6 col-sm-12">
                        <label class="period-card-checkbox w-100">
                            <input class="form-check-input period-checkbox" type="checkbox" name="period_ids[]" value="${p.id}" id="period_${p.id}" data-status="${p.status}">
                            <div class="period-card-inner">
                                <div class="day-label">
                                    ${p.day} <i class="fa-regular fa-circle-check check-indicator"></i>
                                </div>
                                <div class="time-label">${p.start_time} - ${p.end_time} ${badge}</div>
                            </div>
                        </label>
                    </div>
                `;
            });
            $('#periods_container').html(html);
            applyRepeatToPeriods();
        });
    }

    toggleEndDate();

    $('#subject_teacher_id').on('change', loadPeriods);

    // Repeat pill toggle (create form)
    $('input[name="repeat"]').on('change', function () {
        toggleEndDate();
        applyRepeatToPeriods();
    });

    // Refresh list when a toolbar filter changes
    $('#filter_oc_class_section_id, #filter_oc_subject_id, #filter_oc_repeat').on('change', function () {
        $('#table_list').bootstrapTable('refresh');
    });

    // Fetch subjects when class section filter changes
    $('#filter_oc_class_section_id').on('change', function () {
        const classSectionId = $(this).val();
        const subjectDropdown = $('#filter_oc_subject_id');
        
        // Save the currently selected subject to reselect if possible
        const currentSubjectId = subjectDropdown.val();

        // If no class section is selected, we might want to load all subjects again,
        // but for now, we just clear or reload them from server. 
        // We'll let the server return the appropriate list.
        subjectDropdown.html('<option value="">' + (config.translations.all || 'All') + '</option>');

        if (!classSectionId) {
            // We can refresh the table directly
            return;
        }

        $.ajax({
            url: config.subjectsUrl,
            type: "GET",
            data: { class_section_id: classSectionId },
            success: function (response) {
                let html = '<option value="">' + (config.translations.all || 'All') + '</option>';
                if (response && response.length > 0) {
                    response.forEach(function (subject) {
                        let subjectName = subject.name;
                        if (subject.type) {
                            subjectName += ' - ' + subject.type;
                        }
                        html += '<option value="' + subject.id + '">' + subjectName + '</option>';
                    });
                }
                subjectDropdown.html(html);
                
                // Try to retain previous selection if it exists in the new list
                if (currentSubjectId && subjectDropdown.find('option[value="' + currentSubjectId + '"]').length > 0) {
                    subjectDropdown.val(currentSubjectId);
                }
                
                // Trigger change so the table refreshes with the updated subject
                subjectDropdown.trigger('change');
            }
        });
    });

    // Edit modal : toggle Start/End Date based on selected repeat
    $('#edit_repeat').on('change', function () {
        if ($(this).val() === config.repeatNoRepeat) {
            $('#edit_start_date_group').hide();
            $('#edit_start_date').removeAttr('required');
            $('#edit_end_date_group').hide();
            $('#edit_end_date').removeAttr('required');
        } else {
            $('#edit_start_date_group').show();
            $('#edit_start_date').attr('required', 'required');
            $('#edit_end_date_group').show();
            $('#edit_end_date').attr('required', 'required');
        }
    });
});
