document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('assignment_report_container');
    if (!container) return;

    const reportUrl = container.dataset.reportUrl;

    // Get parameters from data attributes (matching the blade file)
    const studentId = container.dataset.studentId;
    const sessionYearId = container.dataset.sessionYearId;

    const semesterFilter = document.getElementById('assignment_semester_filter');
    const statusFilter = document.getElementById('assignment_status_filter');

    if (semesterFilter) {
        semesterFilter.addEventListener('change', loadAssignmentData);
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', loadAssignmentData);
    }

    // Load initially if tab is active, or wait for tab switch
    const assignmentsTab = document.getElementById('assignments-tab');
    let loaded = false;

    if (assignmentsTab) {
        // Need jQuery here since Bootstrap 4 uses jQuery for events
        $('#assignments-tab').on('shown.bs.tab', function (e) {
            if (!loaded) {
                loadAssignmentData();
                loaded = true;
            }
        });

        if (assignmentsTab.classList.contains('active')) {
            loadAssignmentData();
            loaded = true;
        }
    } else {
        // Fallback if tabs aren't bootstrap initialized
        loadAssignmentData();
    }

    function loadAssignmentData() {
        if (!studentId || !sessionYearId) return;

        const loadingEl = document.getElementById('asr_loading');
        const noDataEl = document.getElementById('asr_no_data');
        const subjectContainer = document.getElementById('asr_subject_container');

        loadingEl.classList.remove('d-none');
        noDataEl.classList.add('d-none');
        subjectContainer.classList.add('d-none');

        let url = `${reportUrl}?student_id=${studentId}&session_year_id=${sessionYearId}`;
        if (semesterFilter && semesterFilter.value) {
            url += `&semester_id=${semesterFilter.value}`;
        }
        if (statusFilter && statusFilter.value) {
            url += `&status=${statusFilter.value}`;
        }

        fetch(url)
            .then(response => response.json())
            .then(data => {
                loadingEl.classList.add('d-none');
                if (data.success && data.subjects && data.subjects.length > 0) {
                    renderDashboard(data);
                    subjectContainer.classList.remove('d-none');
                } else {
                    renderEmptyDashboard();
                    noDataEl.classList.remove('d-none');
                }
            })
            .catch(error => {
                console.error("Error fetching assignment data", error);
                loadingEl.classList.add('d-none');
                renderEmptyDashboard();
                noDataEl.classList.remove('d-none');
            });
    }

    function renderDashboard(data) {
        // Update KPIs
        const kpis = data.kpis;
        document.getElementById('asr_overall_obtained').textContent = kpis.overall_obtained;
        document.getElementById('asr_overall_max').textContent = kpis.overall_max;
        document.getElementById('asr_overall_percentage').textContent = kpis.percentage;
        document.getElementById('asr_accepted_count').textContent = kpis.accepted_count;
        document.getElementById('asr_pending_count').textContent = kpis.pending_count;
        document.getElementById('asr_critical_count').textContent = kpis.critical_count;

        // Progress Tracker
        const total = kpis.total_assignments;
        const completed = kpis.accepted_count + kpis.rejected_count; // basically everything that has a submission

        document.getElementById('asr_total_completed').textContent = completed;
        document.getElementById('asr_total_assignments').textContent = total;
        document.getElementById('asr_completion_percentage').textContent = total > 0 ? Math.round((completed / total) * 100) : 0;

        const progressBarContainer = document.getElementById('asr_progress_bar_container');
        progressBarContainer.innerHTML = '';

        if (total > 0) {
            const createSegment = (count, className) => {
                if (count > 0) {
                    const width = (count / total) * 100;
                    return `<div class="progress-bar ${className}" role="progressbar" style="width: ${width}%" aria-valuenow="${width}" aria-valuemin="0" aria-valuemax="100"></div>`;
                }
                return '';
            };

            let html = '';
            html += createSegment(kpis.accepted_count, 'bg-success');
            html += createSegment(kpis.late_submitted_count, 'bg-warning');
            html += createSegment(kpis.pending_count, 'bg-info');
            html += createSegment(kpis.critical_count, 'bg-danger');

            progressBarContainer.innerHTML = html;
        }

        // Render Subjects
        const subjectContainer = document.getElementById('asr_subject_container');
        subjectContainer.innerHTML = '';

        data.subjects.forEach(subjectGroup => {
            const kpi = subjectGroup.kpi;
            const subjectPct = kpi.percentage;

            let subjectHtml = `
                <div class="card border mb-4" style="border-radius: 12px; border-color: #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03);">
                    <div class="card-header border-bottom py-3 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center" style="background-color: #f8fafc; border-color: #f1f5f9; gap: 12px;">
                        <div class="d-flex align-items-center">
                            <span class="mr-3" style="width: 10px; height: 32px; background-color: #1e4e79; border-radius: 10px; display: inline-block;"></span>
                            <div>
                                <h5 class="mb-0 font-weight-bold" style="color: #1e293b; font-size: 14px;">${subjectGroup.subject_name}</h5>
                                <small style="color: #94a3b8; font-size: 11px; font-weight: 500;">${window.trans['instructor_assessor']}: ${subjectGroup.instructor}</small>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center bg-white border rounded px-3 py-2 shadow-sm" style="gap: 10px; border-color: #e2e8f0;">
                            <span class="text-uppercase font-weight-bold" style="font-size: 10px; color: #94a3b8;">${window.trans['subject_GPA_ratio']}</span>
                            <span class="font-weight-bold" style="font-size: 12px; color: #1e4e79;">${kpi.obtained_points} / ${kpi.max_points} pts</span>
                            <span class="font-weight-bold px-2 py-1 rounded" style="font-size: 10px; background-color: #eff6ff; color: #1d4ed8;">${subjectPct}%</span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr class="text-uppercase font-weight-bold" style="background-color: rgba(248, 250, 252, 0.4); color: #94a3b8; font-size: 10px; letter-spacing: 0.05em; border-bottom: 1px solid #f1f5f9;">
                                    <th class="border-top-0 px-4 py-3">${window.trans['assignment_details']}</th>
                                    <th class="border-top-0 px-4 py-3">${window.trans['timeline_check']}</th>
                                    <th class="border-top-0 px-4 py-3">${window.trans['status_review']}</th>
                                    <th class="border-top-0 px-4 py-3">${window.trans['graded_marks']}</th>
                                    <th class="border-top-0 px-4 py-3 text-right">${window.trans['action']}</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            subjectGroup.assignments.forEach(assignment => {
                let statusBadge = '';
                let statusDesc = '';

                if (assignment.is_late) {
                    statusBadge = `<span class="badge badge-pill badge-warning px-3 py-2 text-white"><i class="fa fa-clock mr-1"></i> ${window.trans['late_submitted']}</span>`;
                    statusDesc = assignment.status === 'Accepted' ? '<span class="text-success small"><i class="fa fa-check"></i> ' + window.trans['accepted'] + '</span>' :
                        assignment.status === 'Rejected' ? '<span class="text-danger small"><i class="fa fa-times"></i> ' + window.trans['rejected'] + '</span>' :
                            '<span class="text-info small"><i class="fa fa-hourglass-half"></i> ' + window.trans['pending_review'] + '</span>';
                } else if (assignment.status === 'Accepted') {
                    statusBadge = `<span class="badge badge-pill badge-success px-3 py-2 text-white"><i class="fa fa-check mr-1"></i> ` + window.trans['accepted'] + `</span>`;
                    statusDesc = `<span class="text-muted small font-italic">${window.trans['approved_submission']}</span>`;
                } else if (assignment.status === 'Pending') {
                    statusBadge = `<span class="badge badge-pill badge-info px-3 py-2 text-white"><i class="fa fa-hourglass-half mr-1"></i> ` + window.trans['pending'] + `</span>`;
                    statusDesc = `<span class="text-warning small font-italic font-weight-bold">${window.trans['awaiting_evaluate']}</span>`;
                } else if (assignment.status === 'Rejected') {
                    statusBadge = `<span class="badge badge-pill badge-danger px-3 py-2 text-white"><i class="fa fa-times mr-1"></i> ${window.trans['rejected']}</span>`;
                    statusDesc = `<span class="text-muted small font-italic text-truncate d-inline-block" style="max-width: 150px;">${assignment.submission?.feedback || 'Requires correction'}</span>`;
                } else if (assignment.status === 'Overdue') {
                    statusBadge = `<span class="badge badge-pill badge-danger px-3 py-2 text-white"><i class="fa fa-exclamation-circle mr-1"></i> ${window.trans['overdue']}</span>`;
                    statusDesc = `<span class="text-muted small font-italic">${window.trans['no_submission_date']}</span>`;
                }

                let gradedMarks = '-';
                if (assignment.submission && assignment.status !== 'Pending' && !assignment.is_late) { // Show if graded
                    gradedMarks = `<strong>${assignment.submission.points}</strong> / ${assignment.max_points}`;
                } else if (assignment.submission && assignment.status === 'Accepted' && assignment.is_late) {
                    gradedMarks = `<strong>${assignment.submission.points}</strong> / ${assignment.max_points}`;
                } else {
                    gradedMarks = `- / ${assignment.max_points}`;
                }

                // File icon logic for UI
                let fileIcon = '';
                if (assignment.files && assignment.files.length > 0) {
                    let ext = assignment.files[0].file_extension || 'pdf';
                    let iconClass = 'fa-file-pdf-o text-danger';
                    if (['doc', 'docx'].includes(ext)) iconClass = 'fa-file-word-o text-primary';
                    else if (['png', 'jpg', 'jpeg'].includes(ext)) iconClass = 'fa-file-image-o text-info';

                    fileIcon = `<small class="d-block text-muted mt-1"><i class="fa ${iconClass} mr-1"></i> ${assignment.files[0].file_name || 'Document attached'}</small>`;
                }

                subjectHtml += `
                    <tr>
                        <td class="px-4 py-3">
                            <h6 class="mb-1 font-weight-bold" style="color: #1e293b; font-size: 13px;">${assignment.name}</h6>
                            <div class="d-flex align-items-center mt-1" style="gap: 6px;">
                                <span class="text-uppercase font-weight-bold" style="font-size: 9px; color: #94a3b8; letter-spacing: 0.05em;">SEMESTER ID:</span>
                                <span class="font-weight-bold" style="font-size: 10px; color: #64748b;">${assignment.semester_name}</span>
                            </div>
                            ${fileIcon}
                        </td>
                        <td class="px-4 py-3">
                            <small class="d-block mb-1" style="font-size: 11px; color: #64748b;"><span style="color: #94a3b8; font-weight: 500;">Due:</span> ${assignment.due_date || 'N/A'}</small>
                            ${assignment.submission ? `<small class="d-block font-weight-bold" style="font-size: 11px; color: #059669;"><span style="color: #94a3b8; font-weight: 500;">Submitted:</span> ${assignment.submission.submitted_at}</small>` : `<small class="d-block font-italic" style="font-size: 11px; color: #cbd5e1;">No submission date</small>`}
                        </td>
                        <td class="px-4 py-3">
                            <div class="mb-1">${statusBadge}</div>
                            <div>${statusDesc}</div>
                        </td>
                        <td class="align-middle px-4 py-3" style="font-size: 13px; color: #1e293b;">
                            ${gradedMarks}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button class="btn btn-sm btn-theme rounded-pill px-3 view-details-btn" data-assignment='${JSON.stringify(assignment).replace(/'/g, "&#39;")}' style="font-size: 12px; font-weight: 600;">
                                <i class="fa fa-info-circle mr-1"></i> Review details
                            </button>
                        </td>
                    </tr>
                `;
            });

            subjectHtml += `
                                </tbody>
                            </table>
                        </div>
                </div>
            `;

            subjectContainer.insertAdjacentHTML('beforeend', subjectHtml);
        });

        // Attach click events for modals
        document.querySelectorAll('.view-details-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const assignment = JSON.parse(this.dataset.assignment);
                openDetailsModal(assignment);
            });
        });
    }

    function renderEmptyDashboard() {
        document.getElementById('asr_overall_obtained').textContent = '0';
        document.getElementById('asr_overall_max').textContent = '0';
        document.getElementById('asr_overall_percentage').textContent = '0';
        document.getElementById('asr_accepted_count').textContent = '0';
        document.getElementById('asr_pending_count').textContent = '0';
        document.getElementById('asr_critical_count').textContent = '0';

        document.getElementById('asr_total_completed').textContent = '0';
        document.getElementById('asr_total_assignments').textContent = '0';
        document.getElementById('asr_completion_percentage').textContent = '0';
        document.getElementById('asr_progress_bar_container').innerHTML = '';
        document.getElementById('asr_subject_container').innerHTML = '';
    }

    function openDetailsModal(assignment) {
        document.getElementById('modal_header_subtitle').textContent = `Viewing assignment details and grading for ${assignment.subject_name}`;
        document.getElementById('modal_subject_name').textContent = assignment.subject_name;

        let subAt = 'Not submitted';
        if (assignment.submission) {
            subAt = `Submitted ${assignment.submission.submitted_at} (${assignment.is_late ? 'LATE' : 'ON-TIME'})`;
        }
        document.getElementById('modal_submitted_at').textContent = subAt;

        // Files
        const filesContainer = document.getElementById('modal_attached_files');
        filesContainer.innerHTML = '';

        let allFiles = [];
        if (assignment.files) allFiles = allFiles.concat(assignment.files);
        if (assignment.submission && assignment.submission.files) allFiles = allFiles.concat(assignment.submission.files);

        if (allFiles.length > 0) {
            allFiles.forEach(file => {
                let ext = file.file_extension || 'file';
                let iconClass = 'fa-file-pdf-o text-danger';
                if (['doc', 'docx'].includes(ext)) iconClass = 'fa-file-word-o text-primary';
                else if (['png', 'jpg', 'jpeg'].includes(ext)) iconClass = 'fa-file-image-o text-info';

                filesContainer.innerHTML += `
                    <div class="card border-0 shadow-sm mb-2">
                        <div class="card-body py-2 px-3 d-flex align-items-center">
                            <div class="bg-light rounded p-2 mr-3">
                                <i class="fa ${iconClass} fa-lg"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0 small font-weight-bold">${file.file_name || 'Document'}</h6>
                                <small class="text-muted">Document</small>
                            </div>
                            ${file.file_url ? `<a href="${file.file_url}" target="_blank" class="btn btn-sm btn-light rounded-circle"><i class="fa fa-download text-muted"></i></a>` : ''}
                        </div>
                    </div>
                `;
            });
        } else {
            filesContainer.innerHTML = `<div class="card border-0 shadow-sm border-light" style="border-style: dashed !important;"><div class="card-body text-center py-3"><span class="text-muted font-italic">No files attached</span></div></div>`;
        }

        // Links - Assuming instructions might contain links or we can just show instructions
        document.getElementById('modal_submission_link').innerHTML = assignment.instructions ? assignment.instructions : 'No link assets provided';

        // Status Card
        const statusCard = document.getElementById('modal_status_card');
        const statusIcon = document.getElementById('modal_status_icon');
        const statusText = document.getElementById('modal_status_text');
        const statusDesc = document.getElementById('modal_status_desc');

        statusCard.className = 'card border-0 shadow-sm mb-4'; // reset

        if (assignment.status === 'Accepted') {
            statusCard.classList.add('bg-light-success', 'border-success');
            statusCard.style.backgroundColor = '#e8f7ee';
            statusIcon.innerHTML = '<i class="fa fa-check-circle fa-2x text-success"></i>';
            statusText.textContent = 'Accepted & Approved';
            statusText.className = 'font-weight-bold mb-0 text-success';
            statusDesc.textContent = 'Assignment has been successfully graded.';
            statusDesc.className = 'text-success small';
        } else if (assignment.status === 'Rejected') {
            statusCard.classList.add('bg-light-danger');
            statusCard.style.backgroundColor = '#fce8e8';
            statusIcon.innerHTML = '<i class="fa fa-times-circle fa-2x text-danger"></i>';
            statusText.textContent = 'Rejected';
            statusText.className = 'font-weight-bold mb-0 text-danger';
            statusDesc.textContent = 'Submission was rejected by the instructor.';
            statusDesc.className = 'text-danger small';
        } else if (assignment.status === 'Pending') {
            statusCard.classList.add('bg-light-info');
            statusCard.style.backgroundColor = '#e8f4f8';
            statusIcon.innerHTML = '<i class="fa fa-hourglass-half fa-2x text-info"></i>';
            statusText.textContent = 'Pending Review';
            statusText.className = 'font-weight-bold mb-0 text-info';
            statusDesc.textContent = 'Awaiting teacher evaluation.';
            statusDesc.className = 'text-info small';
        } else {
            statusCard.classList.add('bg-light-secondary');
            statusCard.style.backgroundColor = '#f8f9fa';
            statusIcon.innerHTML = '<i class="fa fa-exclamation-circle fa-2x text-secondary"></i>';
            statusText.textContent = 'Overdue';
            statusText.className = 'font-weight-bold mb-0 text-secondary';
            statusDesc.textContent = 'No submission received before deadline.';
            statusDesc.className = 'text-secondary small';
        }

        // Grades
        const pointsObtained = document.getElementById('modal_points_obtained');
        const pointsMax = document.getElementById('modal_points_max');
        const pointsBadge = document.getElementById('modal_points_badge');

        pointsMax.textContent = assignment.max_points;
        if (assignment.submission && (assignment.status === 'Accepted' || assignment.status === 'Rejected')) {
            pointsObtained.textContent = assignment.submission.points;
            let p = assignment.max_points > 0 ? Math.round((assignment.submission.points / assignment.max_points) * 100) : 0;
            pointsBadge.textContent = `${p}%`;
            pointsBadge.className = p >= 50 ? 'badge badge-success p-2' : 'badge badge-danger p-2';
        } else {
            pointsObtained.textContent = '-';
            pointsBadge.textContent = 'N/A';
            pointsBadge.className = 'badge badge-secondary p-2';
        }

        // Feedback
        const feedbackEl = document.getElementById('modal_feedback');
        if (assignment.submission && assignment.submission.feedback) {
            feedbackEl.textContent = `"${assignment.submission.feedback}"`;
        } else {
            feedbackEl.textContent = 'No feedback provided yet.';
        }

        $('#assignmentDetailsModal').modal('show');
    }
});
