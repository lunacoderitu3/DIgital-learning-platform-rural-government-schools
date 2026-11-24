$(document).ready(function() {
    var $tableList = $('#table_list');

    function populateFilterSubjects() {
        var $classSelect = $('#filter-class-section-id');
        var classId = $classSelect.find(':selected').data('class-id');
        var includeSemesters = $classSelect.find(':selected').data('include-semesters');
        var selectedSemesterId = $('#filter-semester-id').val();
        var sessionYearId = window.sessionYearId;

        // Reset elective subject filter dropdown
        $('#filter-elective-subject-id').html(
            '<option value="">' + window.transSelectOption + '</option>');

        if (classId) {
            // If class includes semesters, but no semester is selected yet, we should show empty/placeholder
            if (includeSemesters == 1 && !selectedSemesterId) {
                return;
            }

            // Find elective subject groups for the selected class
            var classData = window.electiveSubjectGroups.find(function(cls) {
                return cls.id == classId;
            });

            if (classData && classData.elective_subject_groups && classData.elective_subject_groups.length > 0) {
                // Collect all subjects from all groups
                var allSubjects = [];
                classData.elective_subject_groups.forEach(function(group) {
                    if (sessionYearId && group.session_year_id && parseInt(group.session_year_id) !== parseInt(sessionYearId)) {
                        return;
                    }
                    // Filter by semester if semesters are included
                    if (includeSemesters == 1 && selectedSemesterId && parseInt(group.semester_id) !== parseInt(selectedSemesterId)) {
                        return;
                    }
                    if (group.subjects && group.subjects.length > 0) {
                        group.subjects.forEach(function(subject) {
                            // Try to get the class_subject_id from different possible locations
                            var classSubjectId = null;

                            if (subject.pivot && subject.pivot.class_subject_id) {
                                classSubjectId = subject.pivot.class_subject_id;
                            } else if (subject.class_subject_id) {
                                classSubjectId = subject.class_subject_id;
                            } else if (subject.id) {
                                classSubjectId = subject.id;
                            }

                            if (classSubjectId) {
                                var subjectName = subject.name;
                                if (subject.type) {
                                    subjectName += ' (' + subject.type + ')';
                                }

                                // Check if subject already added (avoid duplicates)
                                if (!allSubjects.find(function(s) {
                                        return s.id == classSubjectId;
                                    })) {
                                    allSubjects.push({
                                        id: classSubjectId,
                                        name: subjectName
                                    });
                                }
                            }
                        });
                    }
                });

                // Populate subjects filter dropdown
                if (allSubjects.length > 0) {
                    allSubjects.forEach(function(subject) {
                        $('#filter-elective-subject-id').prop('disabled', false);
                        $('#filter-elective-subject-id').append('<option value="' + subject.id + '">' + subject.name + '</option>');
                    });
                } else {
                    $('#filter-elective-subject-id').append(
                        '<option value="data-not-found">-- ' + window.transNoDataFound + ' --</option>'
                    );
                }
            } else {
                $('#filter-elective-subject-id').append(
                    '<option value="data-not-found">-- ' + window.transNoDataFound + ' --</option>');
            }
        }
    }

    // Handle class section change
    $('#filter-class-section-id').on('change', function() {
        var includeSemesters = $(this).find(':selected').data('include-semesters');

        if (includeSemesters == 1) {
            $('#semester-filter-div').show();
            $('#filter-semester-id').val('').trigger('change.select2');
        } else {
            $('#semester-filter-div').hide();
            $('#filter-semester-id').val('').trigger('change.select2');
        }

        populateFilterSubjects();
        $('#table_list').bootstrapTable('refresh');
    });

    // Handle semester change
    $('#filter-semester-id').on('change', function() {
        populateFilterSubjects();
        $('#table_list').bootstrapTable('refresh');
    });

    // Handle elective subject filter change
    $('#filter-elective-subject-id').on('change', function() {
        $('#table_list').bootstrapTable('refresh');
    });

    // Handle status filter change
    $('#filter-status').on('change', function() {
        $('#table_list').bootstrapTable('refresh');
    });

    // Helper function to find group in window.electiveSubjectGroups
    function findGroupInWindowData(selectedGroupId) {
        var classId = $('#filter-class-section-id').find(':selected').data('class-id');

        if (classId) {
            var classData = window.electiveSubjectGroups.find(function(cls) {
                return parseInt(cls.id) === parseInt(classId);
            });

            if (classData && classData.elective_subject_groups) {
                var foundGroup = classData.elective_subject_groups.find(function(g) {
                    return parseInt(g.id) === parseInt(selectedGroupId);
                });
                return foundGroup || null;
            }
        }

        // Fallback: search all classes for the group
        for (var i = 0; i < window.electiveSubjectGroups.length; i++) {
            var cls = window.electiveSubjectGroups[i];
            if (cls.elective_subject_groups) {
                var foundGroup = cls.elective_subject_groups.find(function(g) {
                    return parseInt(g.id) === parseInt(selectedGroupId);
                });
                if (foundGroup) {
                    return foundGroup;
                }
            }
        }

        return null;
    }

    // Global function to open and populate the modal (accessible from actionEvents.js)
    window.openAssignElectiveSubjectModal = function(rowData, sessionYearId, classSectionId) {
        // Use provided sessionYearId and classSectionId, or get from filters
        sessionYearId = sessionYearId || $('#filter-session-year-id').val() || window.sessionYearId || '';
        classSectionId = classSectionId || $('#filter-class-section-id').val() || '';

        // Set hidden fields
        $('#edit-student-id').val(rowData.id);
        $('#edit-user-id').val(rowData.user_id || rowData.user?.id || '');
        $('#edit-session-year-id').val(sessionYearId);
        $('#edit-class-section-id').val(classSectionId);

        // Set student name and class section
        var studentName = '';
        if (rowData.user) {
            if (rowData.user.first_name) {
                studentName = (rowData.user.first_name || '') + ' ' + (rowData.user.last_name || '');
                studentName = studentName.trim();
            }
        }

        if (!studentName) {
            studentName = 'Student #' + rowData.id;
        }

        // Get class section name
        var classSectionName = '';
        if (rowData.class_section) {
            classSectionName = rowData.class_section.class.full_name;
        } else {
            var selectedSection = $('#filter-class-section-id').find(':selected').text();
            classSectionName = selectedSection || '';
        }

        // Display: "John Smith • 10A"
        var displayText = studentName;
        if (classSectionName) {
            displayText += ' • ' + classSectionName;
        }
        $('#modal-student-name').text(displayText);

        // Get class ID from selected class section
        var classId = $('#filter-class-section-id').find(':selected').data('class-id');
        var includeSemesters = $('#filter-class-section-id').find(':selected').data('include-semesters');
        var selectedSemesterId = $('#filter-semester-id').val();

        // Get elective subject groups from row data or from window.electiveSubjectGroups variable
        var groupsData = null;
        var classData = null;

        // First, try to get class data from window.electiveSubjectGroups (has full subject data)
        if (classId) {
            classData = window.electiveSubjectGroups.find(function(cls) {
                return parseInt(cls.id) === parseInt(classId);
            });
        }

        if (classData && classData.elective_subject_groups) {
            // Use groups from window.electiveSubjectGroups (has full subject data)
            groupsData = classData.elective_subject_groups.filter(function(g) {
                var match = parseInt(g.session_year_id) === parseInt(sessionYearId);
                if (match && includeSemesters == 1 && selectedSemesterId) {
                    match = parseInt(g.semester_id) === parseInt(selectedSemesterId);
                }
                return match;
            });
        } else if (rowData.class_section && rowData.class_section.class && rowData.class_section.class.elective_subject_groups) {
            // Fallback to groups from row data, but enrich with subjects if available
            groupsData = rowData.class_section.class.elective_subject_groups.filter(function(g) {
                var match = parseInt(g.session_year_id) === parseInt(sessionYearId);
                if (match && includeSemesters == 1 && selectedSemesterId) {
                    match = parseInt(g.semester_id) === parseInt(selectedSemesterId);
                }
                return match;
            });

            // If groups don't have subjects, try to enrich them from window.electiveSubjectGroups
            if (classData && classData.elective_subject_groups) {
                groupsData = groupsData.map(function(group) {
                    var enrichedGroup = classData.elective_subject_groups.find(function(g) {
                        return parseInt(g.id) === parseInt(group.id);
                    });
                    return enrichedGroup || group;
                });
            }
        }

        // Get currently assigned subjects for this student
        var assignedSubjectIds = getAssignedSubjectIds(rowData.user_id, sessionYearId, classSectionId);

        // Populate elective subject groups dropdown
        populateElectiveSubjectGroups(groupsData, assignedSubjectIds);

        // Show modal
        $('#editModal').modal('show');
    };

    // Helper function to get assigned subject IDs for a student
    function getAssignedSubjectIds(userId, sessionYearId, classSectionId) {
        var assignedIds = [];
        var allRows = $tableList.bootstrapTable('getData');
        var rowData = allRows.find(function(row) {
            return row.user_id == userId;
        });

        if (rowData && rowData.assigned_class_subject_ids && Array.isArray(rowData.assigned_class_subject_ids)) {
            assignedIds = rowData.assigned_class_subject_ids;
        }

        return assignedIds;
    }

    // Helper function to populate elective subject groups dropdown
    function populateElectiveSubjectGroups(groupsData, assignedSubjectIds) {
        var $groupSelect = $('#edit-elective-subject-group-id');
        $groupSelect.empty().append(
            '<option value="">' + window.transSelectElectiveGroup + '</option>'
        );

        // Hide validation message initially when populating groups
        $('#validation-message').addClass('hidden').hide().css({
            'display': 'none !important',
            'visibility': 'hidden !important',
            'opacity': '0 !important',
            'height': '0 !important',
            'padding': '0 !important',
            'margin': '0 !important',
            'overflow': 'hidden !important'
        });

        if (!groupsData || groupsData.length === 0) {
            $('#group-info-text').text(window.transGroupNotFound);
            return;
        }

        // Populate groups dropdown
        groupsData.forEach(function(group) {
            var optionText = group.name || ('Group #' + group.id);
            if (group.total_subjects && group.total_selectable_subjects) {
                optionText += ' (' + group.total_selectable_subjects + ' of ' + group.total_subjects + ' selectable)';
            }
            $groupSelect.append('<option value="' + group.id + '">' + optionText + '</option>');
        });

        // Reinitialize select2
        if ($groupSelect.hasClass('select2-hidden-accessible')) {
            $groupSelect.select2('destroy');
        }

        $groupSelect.off('change select2:select');

        $groupSelect.select2({
            dropdownParent: $('#editModal'),
            width: '100%'
        });

        // Handle group selection change
        $groupSelect.on('change select2:select', function(e) {
            var selectedGroupId = $(this).val();

            $('#validation-message').addClass('hidden').hide().css({
                'display': 'none !important',
                'visibility': 'hidden !important',
                'opacity': '0 !important',
                'height': '0 !important',
                'padding': '0 !important',
                'margin': '0 !important',
                'overflow': 'hidden !important'
            }).removeClass('alert-info alert-warning alert-success');
            $('#validation-text').text('');

            if (selectedGroupId && selectedGroupId !== 'data-not-found') {
                var groupData = findGroupInWindowData(selectedGroupId);

                if (groupData) {
                    if (groupData.subjects && groupData.subjects.length > 0) {
                        populateSubjectsForGroup(groupData, assignedSubjectIds);
                    } else {
                        $('#available-subjects-list').html(
                            '<p class="text-muted mb-0 text-center">' + window.transNoSubjectsFound + '</p>'
                        );
                        $('#validation-message').addClass('hidden').hide().css({
                            'display': 'none !important',
                            'visibility': 'hidden !important',
                            'opacity': '0 !important',
                            'height': '0 !important',
                            'padding': '0 !important',
                            'margin': '0 !important',
                            'overflow': 'hidden !important'
                        });
                    }
                } else {
                    $('#available-subjects-list').html(
                        '<p class="text-muted mb-0 text-center">' + window.transGroupNotFound + '</p>');
                    $('#validation-message').addClass('hidden').hide().css({
                        'display': 'none !important',
                        'visibility': 'hidden !important',
                        'opacity': '0 !important',
                        'height': '0 !important',
                        'padding': '0 !important',
                        'margin': '0 !important',
                        'overflow': 'hidden !important'
                    });
                }
            } else {
                $('#available-subjects-list').html(
                    '<p class="text-muted mb-0 text-center">' + window.transPleaseSelectGroup + '</p>'
                );
                $('#validation-message').addClass('hidden').hide().css({
                    'display': 'none !important',
                    'visibility': 'hidden !important',
                    'opacity': '0 !important',
                    'height': '0 !important',
                    'padding': '0 !important',
                    'margin': '0 !important',
                    'overflow': 'hidden !important'
                }).removeClass('alert-info alert-warning alert-success');
                $('#validation-text').text('');
            }
        });
    }

    // Helper function to populate subjects for selected group
    function populateSubjectsForGroup(group, assignedSubjectIds) {
        var $subjectsList = $('#available-subjects-list');
        $subjectsList.html('<p class="text-muted mb-0 text-center">' + window.transLoadingSubjects + '</p>');

        var totalSelectable = group.total_selectable_subjects || 0;
        var subjectsHtml = '';

        if (group.subjects && group.subjects.length > 0) {
            group.subjects.forEach(function(subject) {
                var classSubjectId = null;

                if (subject.pivot && subject.pivot.class_subject_id) {
                    classSubjectId = subject.pivot.class_subject_id;
                } else if (subject.class_subject_id) {
                    classSubjectId = subject.class_subject_id;
                } else if (subject.id) {
                    classSubjectId = subject.id;
                }

                if (classSubjectId) {
                    var isAssigned = assignedSubjectIds.some(function(id) {
                        return String(id) === String(classSubjectId);
                    });

                    var checked = isAssigned ? 'checked' : '';
                    var subjectCode = subject.code ? subject.code : '';
                    var subjectName = subject.name_with_type || 'Unknown Subject';

                    subjectsHtml += '<div class="card mb-3 border shadow-sm" style="border-radius: 8px;">';
                    subjectsHtml += '<div class="card-body p-3">';
                    subjectsHtml += '<div class="form-check">';
                    subjectsHtml += '<label class="form-check-label d-flex align-items-center" for="subject_' +
                        classSubjectId + '">';
                    subjectsHtml += '<input class="form-check-input subject-checkbox" type="checkbox" value="' +
                        classSubjectId + '" id="subject_' + classSubjectId + '" ' + checked + '>';
                    subjectsHtml += '<i class="input-helper"></i>';
                    subjectsHtml += '<div class="d-flex flex-column ml-2">';
                    subjectsHtml += '<strong class="mb-1" style="font-size: 15px;">' + subjectName +
                        '</strong>';
                    if (subjectCode) {
                        subjectsHtml += '<span class="text-muted" style="font-size: 13px;">Code: ' +
                            subjectCode + '</span>';
                    }
                    subjectsHtml += '</div>';
                    subjectsHtml += '</label>';
                    subjectsHtml += '</div>';
                    subjectsHtml += '</div>';
                    subjectsHtml += '</div>';
                }
            });
        } else {
            subjectsHtml = '<p class="text-muted mb-0 text-center">' + window.transNoSubjectsFound + '</p>';
        }

        $subjectsList.html(subjectsHtml);

        if (totalSelectable > 0) {
            $('#validation-text').text(window.transYouMustSelect + ' ' + totalSelectable + ' ' + window.transSubjects + '.');
            $('#validation-message').removeClass('hidden').css({
                'display': 'flex',
                'visibility': 'visible',
                'opacity': '1',
                'height': 'auto',
                'padding': '',
                'margin': '',
                'overflow': 'visible'
            }).show();
        } else {
            $('#validation-message').addClass('hidden').hide().css({
                'display': 'none !important',
                'visibility': 'hidden !important',
                'opacity': '0 !important',
                'height': '0 !important',
                'padding': '0 !important',
                'margin': '0 !important',
                'overflow': 'hidden !important'
            });
        }

        $('.subject-checkbox').off('change').on('change', function() {
            updateValidationMessage(totalSelectable);
        });

        updateValidationMessage(totalSelectable);
    }

    // Helper function to update validation message
    function updateValidationMessage(totalSelectable) {
        var checkedCount = $('.subject-checkbox:checked').length;

        if (totalSelectable > 0) {
            if (checkedCount < totalSelectable) {
                $('#validation-text').text(window.transYouMustSelect + ' ' + totalSelectable +
                    ' ' + window.transSubjects + '. ' + window.transCurrentlySelected + ': ' + checkedCount);
                $('#validation-message').removeClass('hidden alert-success alert-warning').addClass('alert-info').css({
                    'display': 'flex',
                    'visibility': 'visible',
                    'opacity': '1',
                    'height': 'auto',
                    'padding': '',
                    'margin': '',
                    'overflow': 'visible'
                }).show();
                $('#save-assignment-btn').prop('disabled', true);
            } else if (checkedCount > totalSelectable) {
                $('#validation-text').text(window.transYouCanSelectMax + ' ' + totalSelectable +
                    ' ' + window.transSubjects + '. ' + window.transCurrentlySelected + ': ' + checkedCount);
                $('#validation-message').removeClass('hidden alert-success alert-info').addClass('alert-warning').css({
                    'display': 'flex',
                    'visibility': 'visible',
                    'opacity': '1',
                    'height': 'auto',
                    'padding': '',
                    'margin': '',
                    'overflow': 'visible'
                }).show();
                $('#save-assignment-btn').prop('disabled', true);
            } else {
                $('#validation-text').text(window.transPerfectSelected + ' ' + checkedCount +
                    ' ' + window.transSubjects + '.');
                $('#validation-message').removeClass('hidden alert-info alert-warning').addClass('alert-success').css({
                    'display': 'flex',
                    'visibility': 'visible',
                    'opacity': '1',
                    'height': 'auto',
                    'padding': '',
                    'margin': '',
                    'overflow': 'visible'
                }).show();
                $('#save-assignment-btn').prop('disabled', false);
            }
        } else {
            $('#validation-message').addClass('hidden').hide().css({
                'display': 'none !important',
                'visibility': 'hidden !important',
                'opacity': '0 !important',
                'height': '0 !important',
                'padding': '0 !important',
                'margin': '0 !important',
                'overflow': 'hidden !important'
            });
        }
    }

    // Handle save button click
    $('#save-assignment-btn').on('click', function(e) {
        e.preventDefault();

        var selectedSubjects = [];
        $('.subject-checkbox:checked').each(function() {
            selectedSubjects.push($(this).val());
        });

        if (selectedSubjects.length === 0) {
            showErrorToast('Please select at least one elective subject.');
            return;
        }

        var $submitBtn = $('#save-assignment-btn');
        var originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true).html(
            '<i class="fa fa-spinner fa-spin"></i> ' + window.transSaving + '...');

        var studentId = $('#edit-student-id').val();
        var classSectionId = $('#edit-class-section-id').val();
        var sessionYearId = $('#edit-session-year-id').val();

        var formData = {
            student_ids: studentId,
            class_subject_ids: selectedSubjects,
            class_section_id: classSectionId,
            session_year_id: sessionYearId
        };

        $.ajax({
            url: window.assignElectiveSubjectStoreRoute,
            type: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $submitBtn.prop('disabled', false).html(originalText);

                if (!response.error) {
                    showSuccessToast(response.message || 'Elective subjects assigned successfully.');
                    $('#editModal').modal('hide');
                    $('#table_list').bootstrapTable('refresh');
                } else {
                    showErrorToast(response.message || 'Failed to assign elective subjects.');
                }
            },
            error: function(xhr) {
                $submitBtn.prop('disabled', false).html(originalText);
                var errorMessage = 'Failed to assign elective subjects.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                showErrorToast(errorMessage);
            }
        });
    });

    // Reset form when modal is closed
    $('#editModal').on('hidden.bs.modal', function() {
        $('#edit-form')[0].reset();
        var $groupSelect = $('#edit-elective-subject-group-id');
        if ($groupSelect.hasClass('select2-hidden-accessible')) {
            $groupSelect.select2('destroy');
        }
        $groupSelect.empty().append(
            '<option value="">' + window.transSelectElectiveGroup + '</option>'
        );
        $('#available-subjects-list').html(
            '<p class="text-muted mb-0 text-center">' + window.transPleaseSelectGroup + '</p>'
        );
        $('#validation-message').addClass('hidden').hide().css({
            'display': 'none !important',
            'visibility': 'hidden !important',
            'opacity': '0 !important',
            'height': '0 !important',
            'padding': '0 !important',
            'margin': '0 !important',
            'overflow': 'hidden !important'
        });
        $('#validation-text').text('');
        $('#group-info-text').text('');
    });

    // Ensure select2 is properly initialized after modal is shown
    $('#editModal').on('shown.bs.modal', function() {
        var $groupSelect = $('#edit-elective-subject-group-id');
        if (!$groupSelect.hasClass('select2-hidden-accessible')) {
            $groupSelect.select2({
                dropdownParent: $('#editModal'),
                width: '100%'
            });
        }
    });

    $('#table_list').bootstrapTable({
        formatNoMatches: function() {
            return "No elective subjects found.";
        }
    });
});
