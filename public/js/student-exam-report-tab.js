$(function () {
    const container = document.getElementById('exam_reports_container');
    if (!container) return;

    const studentId = container.dataset.studentId;
    const sessionYearId = container.dataset.sessionYearId;
    const reportUrl = container.dataset.reportUrl;

    // Translations
    const trans = {
        loading: container.dataset.transLoading || 'Loading',
        noExamResults: container.dataset.transNoExamResults || 'No exam results available for this student',
        noRecords: container.dataset.transNoRecords || 'No exam records found for the selected session year.',
        exam: container.dataset.transExam || 'Exam',
        subject: container.dataset.transSubject || 'Subject',
        maxMarks: container.dataset.transMaxMarks || 'Max Marks',
        obtained: container.dataset.transObtained || 'Obtained',
        grade: container.dataset.transGrade || 'Grade',
        status: container.dataset.transStatus || 'Status',
        pass: container.dataset.transPass || 'Pass',
        fail: container.dataset.transFail || 'Fail',
        notAttempted: container.dataset.transNotAttempted || 'This exam was not attempted by the student',
        noSubjectData: container.dataset.transNoSubjectData || 'No subject data available',
        total: container.dataset.transTotal || 'Total',
        onlineExams: container.dataset.transOnlineExams || 'Online Exams',
        taken: container.dataset.transTaken || 'Taken'
    };

    const semesterFilter = document.getElementById('exam_semester_filter');
    if (semesterFilter) {
        semesterFilter.addEventListener('change', function () {
            loadExamData();
        });
    }

    loadExamData();

    function loadExamData() {
        const loadingEl = document.getElementById('exam_loading');
        const noDataEl = document.getElementById('no_exam_data');
        const wrapperEl = document.getElementById('exam_results_wrapper');

        if (loadingEl) loadingEl.style.display = 'block';
        if (noDataEl) noDataEl.style.display = 'none';
        if (wrapperEl) wrapperEl.style.display = 'none';

        let url = `${reportUrl}?student_id=${studentId}&session_year_id=${sessionYearId}`;
        const semesterFilter = document.getElementById('exam_semester_filter');
        if (semesterFilter && semesterFilter.value) {
            url += `&semester_id=${semesterFilter.value}`;
        }

        fetch(url)
            .then(response => response.json())
            .then(data => renderExamData(data))
            .catch(error => {
                console.error('Error fetching exam data:', error);
                if (loadingEl) loadingEl.style.display = 'none';
                if (noDataEl) {
                    noDataEl.style.display = 'block';
                    noDataEl.textContent = 'Failed to load exam data. Please try again.';
                }
            });
    }

    function renderExamData(data) {
        const loadingEl = document.getElementById('exam_loading');
        const noDataEl = document.getElementById('no_exam_data');
        const wrapperEl = document.getElementById('exam_results_wrapper');

        if (loadingEl) loadingEl.style.display = 'none';
        if (wrapperEl) wrapperEl.innerHTML = '';

        if (data.success && data.exams && data.exams.length > 0) {
            const offlineExams = data.exams.filter(e => !e.exam_type || e.exam_type === 'Offline Exam');
            const onlineExams = data.exams.filter(e => e.exam_type === 'Online Exam');

            offlineExams.forEach(exam => {
                if (wrapperEl) wrapperEl.appendChild(createOfflineExamSection(exam));
            });

            if (onlineExams.length > 0 && wrapperEl) {
                wrapperEl.appendChild(createOnlineExamSection(onlineExams));
            }

            if (wrapperEl) wrapperEl.style.display = 'block';
            if (typeof $().tooltip === 'function') $('[data-toggle="tooltip"]').tooltip();
        } else {
            if (noDataEl) {
                noDataEl.style.display = 'block';
                noDataEl.textContent = trans.noRecords;
            }
        }
    }

    function createOfflineExamSection(exam) {
        const section = document.createElement('div');
        section.className = 'svr-exam-section card border shadow-sm mb-4';
        section.style.borderRadius = '12px';
        section.style.overflow = 'hidden';

        const examId = exam.id || Math.random().toString(36).substr(2, 9);
        const collapseId = `collapse_exam_${examId}`;

        // Determine overall status
        let badgeClass = 'badge-secondary';
        let statusLabel = 'NOT ATTEMPTED';
        let badgeBg = '#f1f5f9'; // slate-100
        let badgeColor = '#64748b'; // slate-500
        let dotColor = '#94a3b8'; // slate-400

        if (exam.summary) {
            const result = exam.summary.result;
            const percentage = Math.round(exam.summary.percentage || 0);
            if (result === 'Pass') {
                badgeClass = 'badge-success';
                statusLabel = `PASS (${percentage}%)`;
                badgeBg = '#eef6ee'; // Light green bg
                badgeColor = '#1e8449'; // Dark green text
                dotColor = '#28a745'; // Green dot
            } else if (result === 'Fail') {
                badgeClass = 'badge-danger';
                statusLabel = `FAIL (${percentage}%)`;
                badgeBg = '#fdf2f2'; // Light red bg
                badgeColor = '#c81e1e'; // Dark red text
                dotColor = '#e02424'; // Red dot
            } else {
                statusLabel = result ? result.toUpperCase() : 'NOT ATTEMPTED';
            }
        }

        // Build header layout matching the mockup
        section.innerHTML = `
            <div class="svr-exam-section__header d-flex align-items-center justify-content-between flex-wrap p-3" 
                 data-toggle="collapse" 
                 data-target="#${collapseId}" 
                 aria-expanded="false" 
                 aria-controls="${collapseId}" 
                 style="cursor: pointer; background-color: #eaf0f8; border-bottom: 1px solid #dce4f0;">
                
                <div class="d-flex align-items-center">
                    <div style="width: 4px; height: 20px; background-color: #3b82f6; border-radius: 4px; margin-right: 12px;"></div>
                    <h6 class="svr-exam-section__name font-weight-bold mb-0" style="font-size: 1.05rem; color: #1e293b;">
                        ${exam.name || exam.exam_title || trans.exam}
                    </h6>
                </div>
                
                <div class="d-flex align-items-center flex-wrap ml-auto" style="gap: 20px;">
                    <!-- MARKS -->
                    <div class="text-center my-1">
                        <div class="text-uppercase text-muted font-weight-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">
                            ${trans.maxMarks}
                        </div>
                        <div class="font-weight-bold" style="font-size: 14px; color: #1e293b;">
                            ${exam.summary ? `<span style="color: #0f172a;">${exam.summary.obtained_marks}</span> <span style="color: #64748b;">/ ${exam.summary.max_marks}</span>` : '-'}
                        </div>
                    </div>
                    
                    <!-- GRADE -->
                    <div class="text-center my-1" style="min-width: 50px;">
                        <div class="text-uppercase text-muted font-weight-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">
                            ${trans.grade}
                        </div>
                        <div class="font-weight-bold" style="font-size: 14px; color: #2563eb;">
                            ${exam.summary && exam.summary.grade ? exam.summary.grade : '-'}
                        </div>
                    </div>
                    
                    <!-- STATUS -->
                    <div class="my-1">
                        <span style="background: ${badgeBg}; color: ${badgeColor}; font-weight: 700; border-radius: 20px; padding: 6px 12px; font-size: 11px; display: inline-flex; align-items: center; gap: 6px; letter-spacing: 0.3px;">
                            <div style="width: 6px; height: 6px; background: ${dotColor}; border-radius: 50%;"></div>
                            ${statusLabel}
                        </span>
                    </div>
                    
                    <!-- CHEVRON -->
                    <div class="pl-2 my-1">
                        <span class="p-0 bg-transparent border-0">
                            <i class="fa fa-chevron-down collapse-icon" style="font-size: 14px; color: #94a3b8; transition: transform 0.2s;"></i>
                        </span>
                    </div>
                </div>
            </div>
            
            <div id="${collapseId}" class="collapse">
                <div class="table-responsive">
                    <table class="svr-exam-table mb-0 table">
                        <thead class="bg-white">
                            <tr>
                                <th class="text-uppercase text-muted font-weight-bold border-bottom-0 py-3 px-4" style="font-size: 9px; letter-spacing: 0.5px;">${trans.subject}</th>
                                <th class="text-uppercase text-muted font-weight-bold border-bottom-0 py-3 px-4" style="font-size: 9px; letter-spacing: 0.5px;">${trans.maxMarks}</th>
                                <th class="text-uppercase text-muted font-weight-bold border-bottom-0 py-3 px-4" style="font-size: 9px; letter-spacing: 0.5px;">${trans.obtained}</th>
                                <th class="text-uppercase text-muted font-weight-bold border-bottom-0 py-3 px-4" style="font-size: 9px; letter-spacing: 0.5px;">${trans.grade}</th>
                                <th class="text-uppercase text-muted font-weight-bold border-bottom-0 py-3 px-4" style="font-size: 9px; letter-spacing: 0.5px;">${trans.status}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white"></tbody>
                        <tfoot class="bg-light"></tfoot>
                    </table>
                </div>
            </div>
        `;

        const tbody = section.querySelector('tbody');
        const tfoot = section.querySelector('tfoot');

        if (exam.subjects && exam.subjects.length > 0) {
            exam.subjects.forEach(sub => {
                const tr = document.createElement('tr');
                const passBadge = sub.is_pass ?
                    `<span class="svr-pass-badge svr-pass-badge--pass">${trans.pass}</span>` :
                    `<span class="svr-pass-badge svr-pass-badge--fail">${trans.fail}</span>`;
                tr.innerHTML = `
                    <td class="py-3 px-4"><strong>${sub.name}</strong>${sub.code ? ` <small class="text-muted">(${sub.code})</small>` : ''}</td>
                    <td class="py-3 px-4 text-muted font-weight-normal">${sub.max_marks}</td>
                    <td class="py-3 px-4"><strong>${sub.obtained_marks}</strong></td>
                    <td class="py-3 px-4 font-weight-bold">${sub.grade || '-'}</td>
                    <td class="py-3 px-4">${passBadge}</td>
                `;
                tbody.appendChild(tr);
            });
        } else if (exam.summary && exam.summary.result === 'Not Attempted') {
            tbody.innerHTML =
                `<tr><td colspan="5" class="text-center text-muted py-4">${trans.notAttempted}</td></tr>`;
        } else {
            tbody.innerHTML =
                `<tr><td colspan="5" class="text-center text-muted py-4">${trans.noSubjectData}</td></tr>`;
        }

        // Summary / total row
        if (exam.summary && exam.subjects && exam.subjects.length > 0) {
            const hasSummaryRow = exam.subjects.some(s => s.name === 'Overall Result' || s.code === 'ALL');
            if (!hasSummaryRow) {
                tfoot.innerHTML = `
                    <tr style="background-color: #eaf0f8; border-top: 1px solid #dce4f0;">
                        <td colspan="5" class="p-0 border-0">
                            <div class="d-flex align-items-center justify-content-between p-3 px-4">
                                <div class="font-weight-bold" style="font-size: 1.05rem; color: #1e293b;">
                                    ${trans.total}
                                </div>
                                <div class="d-flex align-items-center flex-wrap ml-auto" style="gap: 24px;">
                                    <!-- MAX TOTAL -->
                                    <div class="text-center my-1">
                                        <div class="text-uppercase text-muted font-weight-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">
                                            MAX TOTAL
                                        </div>
                                        <div class="font-weight-bold" style="font-size: 14px; color: #0f172a;">
                                            ${exam.summary.max_marks}
                                        </div>
                                    </div>
                                    
                                    <!-- OBTAINED -->
                                    <div class="text-center my-1">
                                        <div class="text-uppercase text-muted font-weight-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">
                                            ${trans.obtained}
                                        </div>
                                        <div class="font-weight-bold" style="font-size: 14px; color: #0f172a;">
                                            ${exam.summary.obtained_marks}
                                        </div>
                                    </div>
                                    
                                    <!-- GRADE -->
                                    <div class="text-center my-1" style="min-width: 50px;">
                                        <div class="text-uppercase text-muted font-weight-bold mb-1" style="font-size: 10px; letter-spacing: 0.5px;">
                                            ${trans.grade}
                                        </div>
                                        <div class="font-weight-bold" style="font-size: 14px; color: #2563eb;">
                                            ${exam.summary.grade || '-'}
                                        </div>
                                    </div>
                                    
                                    <!-- STATUS -->
                                    <div class="my-1 mr-4">
                                        <span style="background: ${badgeBg}; color: ${badgeColor}; font-weight: 700; border-radius: 20px; padding: 6px 12px; font-size: 11px; display: inline-flex; align-items: center; gap: 6px; letter-spacing: 0.3px;">
                                            <div style="width: 6px; height: 6px; background: ${dotColor}; border-radius: 50%;"></div>
                                            ${statusLabel}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
            }
        }

        return section;
    }

    function createOnlineExamSection(exams) {
        const section = document.createElement('div');
        section.className = 'svr-online-exam-section card border shadow-sm';
        section.style.borderRadius = '12px';
        section.style.overflow = 'hidden';

        section.innerHTML = `
            <div class="svr-online-exam-section__header p-3 bg-white border-bottom">
                <span class="svr-online-exam-label font-weight-bold text-uppercase" style="font-size: 11px; letter-spacing: 1px; color: var(--theme-color);">${trans.onlineExams}</span>
            </div>
            <div id="online_exam_list"></div>
        `;

        const list = section.querySelector('#online_exam_list');
        exams.forEach(exam => {
            const initial = (exam.subject_name || exam.exam_title || 'E').charAt(0).toUpperCase();
            const item = document.createElement('div');
            item.className = 'svr-online-exam-item d-flex align-items-center p-3 border-bottom';
            item.style.gap = '14px';
            item.innerHTML = `
                <div class="svr-online-exam-icon d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px; border-radius: 10px; background-color: #f0edff; color: var(--theme-color); font-size: 16px; font-weight: 700;">
                    ${initial}
                </div>
                <div class="svr-online-exam-info flex-grow-1">
                    <div class="svr-online-exam-title font-weight-bold text-dark" style="font-size: 13px;">${exam.exam_title || exam.name || '-'}</div>
                    <div class="svr-online-exam-date text-muted" style="font-size: 11px;">${trans.taken}: ${exam.created_at || '-'}</div>
                </div>
                <div class="svr-online-exam-result text-right">
                    <div class="svr-online-exam-pct font-weight-bold text-success" style="font-size: 15px;">${Math.round(exam.percentage || 0)}%</div>
                    <div class="svr-online-exam-grade text-muted" style="font-size: 11px;">${trans.grade} ${exam.grade || '-'}</div>
                </div>
            `;
            list.appendChild(item);
        });

        const lastItem = list.lastElementChild;
        if (lastItem) {
            lastItem.style.borderBottom = 'none';
        }

        return section;
    }

    // Delegated collapse toggle event handlers to rotate chevron icons
    $(document).on('show.bs.collapse', '.collapse', function () {
        $(this).closest('.svr-exam-section').find('.collapse-icon')
            .removeClass('fa-chevron-down')
            .addClass('fa-chevron-up');
    });

    $(document).on('hide.bs.collapse', '.collapse', function () {
        $(this).closest('.svr-exam-section').find('.collapse-icon')
            .removeClass('fa-chevron-up')
            .addClass('fa-chevron-down');
    });
});
