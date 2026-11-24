function formSuccessFunction(response) {
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

$(document).ready(function() {
    $('#filter-subject-id, #filter-class-section-id, #filter-semester-id').on('change', function() {
        let selectedSubjectId = $('#filter-subject-id').find(':selected').data('subject-id') || $('#filter-subject-id').val();
        let selectedSections = $('#filter-class-section-id').val();
        let selectedSemesterId = $('#filter-semester-id').val() || $('#filter-subject-id').find(':selected').data('semester-id');

        if (selectedSemesterId !== undefined && selectedSemesterId !== null && selectedSemesterId !== '') {
            selectedSemesterId = String(selectedSemesterId);
        } else {
            selectedSemesterId = '';
        }

        let $lessonSelect = $('#filter-lesson-id');

        // Reset
        $lessonSelect.find('option').hide();
        $lessonSelect.prop('disabled', true);

        if (!selectedSubjectId || !selectedSections) {
            $lessonSelect.val("");
            return;
        }

        let found = false;
        $('#filter-lesson-id option').each(function() {
            let optionVal = this.value;
            if (!optionVal || optionVal === 'data-not-found') {
                return;
            }

            let subjectId = String($(this).data('subject'));
            let classSectionId = String($(this).data('class-section'));
            let semesterId = $(this).data('semester-id');

            if (semesterId !== undefined && semesterId !== null && semesterId !== '') {
                semesterId = String(semesterId);
            } else {
                semesterId = '';
            }

            if (subjectId !== String(selectedSubjectId)) {
                return;
            }

            if (classSectionId !== String(selectedSections)) {
                return;
            }

            // Semester Check
            if (selectedSemesterId) {
                if (semesterId && semesterId !== selectedSemesterId) {
                    return;
                }
            }

            $(this).show();
            found = true;
        });

        if (found) {
            $lessonSelect.val("").removeAttr('disabled').show();
            $lessonSelect.find('option[value=""]').show();
        } else {
            $lessonSelect.val("data-not-found").attr('disabled', true).show();
            $lessonSelect.find('option[value="data-not-found"]').show();
        }
    });

    $('#filter-lesson-id, #filter-semester-id').on('change', function() {
        $('#table_list').bootstrapTable('refresh');
    });
});
