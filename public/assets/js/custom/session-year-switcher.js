// session-year-switcher.js

let sysActiveSession = '';
let sysActiveSemester = '';
let sysSemesterEnabled = false;

let sysPendingSession = '';
let sysPendingSemester = '';

function initSessionYearSwitcher(activeSessionId, activeSemesterId, hasSemesters) {
    sysActiveSession = activeSessionId;
    sysActiveSemester = activeSemesterId;
    sysSemesterEnabled = hasSemesters;

    sysPendingSession = activeSessionId;
    sysPendingSemester = activeSemesterId;

    updateSysSwitcherRender();
}

function toggleSysDropdown(event) {
    if (event) event.stopPropagation();

    const menu = document.getElementById('sys-dropdown-menu');
    const arrow = document.getElementById('sys-arrow');

    if (menu) {
        const isHidden = menu.style.display === 'none' || menu.style.display === '';
        if (isHidden) {
            menu.style.display = 'flex';
            if (arrow) arrow.style.transform = 'rotate(180deg)';

            // Reset pending to active when opening
            sysPendingSession = sysActiveSession;
            sysPendingSemester = sysActiveSemester;
            updateSysSwitcherRender();
        } else {
            closeSysDropdown();
        }
    }
}

function closeSysDropdown() {
    const menu = document.getElementById('sys-dropdown-menu');
    const arrow = document.getElementById('sys-arrow');
    if (menu) menu.style.display = 'none';
    if (arrow) arrow.style.transform = 'rotate(0deg)';
}

function fetchSemestersForSession(sessionId) {
    // Make AJAX call to get semesters
    let baseUrl = window.location.origin;
    $.ajax({
        url: baseUrl + '/session-year/get-semesters/' + sessionId,
        type: 'GET',
        success: function (response) {
            if (response.semesters && response.semesters.length > 0) {
                sysSemesterEnabled = true;
                sysPendingSemester = ''; // clear selection to force them to pick a semester
                renderSysSemesters(response.semesters);
            } else {
                sysSemesterEnabled = false;
                sysPendingSemester = '';
            }
            updateSysSwitcherRender();
        },
        error: function () {
            console.error('Failed to fetch semesters');
            sysSemesterEnabled = false;
            updateSysSwitcherRender();
        }
    });
}

function renderSysSemesters(semesters) {
    const container = document.getElementById('sys-semester-list');
    if (!container) return;

    container.innerHTML = '';

    semesters.forEach(sem => {
        const isActive = (sysPendingSemester == sem.id);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'sys-option-btn' + (isActive ? ' active' : '');
        btn.innerHTML = '<span>' + sem.name + '</span>';

        btn.onclick = function (e) {
            e.stopPropagation();
            sysPendingSemester = sem.id;
            updateSysSwitcherRender();

            // Re-render to show active state
            Array.from(container.children).forEach(child => child.classList.remove('active'));
            btn.classList.add('active');

            checkSysApplyButton();
        };

        container.appendChild(btn);
    });
}

function updateSysSwitcherRender() {
    const menu = document.getElementById('sys-dropdown-menu');
    if (!menu) return;

    if (sysSemesterEnabled) {
        menu.classList.add('sys-w-double');
        menu.classList.remove('sys-w-single');
    } else {
        menu.classList.add('sys-w-single');
        menu.classList.remove('sys-w-double');
    }

    // Highlight correct session year
    const sessionBtns = document.querySelectorAll('.sys-session-btn');
    sessionBtns.forEach(btn => {
        if (btn.dataset.id == sysPendingSession) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    checkSysApplyButton();
}

function checkSysApplyButton() {
    const applyBtn = document.getElementById('sys-btn-apply');
    if (!applyBtn) return;

    if (sysSemesterEnabled && !sysPendingSemester) {
        applyBtn.disabled = true;
    } else {
        applyBtn.disabled = false;
    }
}

function selectSysSession(sessionId, event) {
    if (event) event.stopPropagation();

    sysPendingSession = sessionId;
    updateSysSwitcherRender();

    // Fetch semesters for this year
    fetchSemestersForSession(sessionId);
}

function applySysTermChanges() {
    const applyBtn = document.getElementById('sys-btn-apply');
    if (applyBtn) {
        applyBtn.innerHTML = '<span class="sys-spinner"></span> Setting...';
        applyBtn.disabled = true;
    }

    let baseUrl = window.location.origin;

    // Set Session Year
    $.post(baseUrl + '/session-year/set-session-year', {
        session_year_id: sysPendingSession,
        _token: $('meta[name="csrf-token"]').attr('content')
    }, function (response) {
        if (response.error == false) {
            if (sysSemesterEnabled && sysPendingSemester) {
                // Set Semester
                $.post(baseUrl + '/set-semester', {
                    semester_id: sysPendingSemester,
                    _token: $('meta[name="csrf-token"]').attr('content')
                }, function (res) {
                    if (res.error == false) {
                        location.reload();
                    } else {
                        // Error setting semester
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', text: res.message });
                        }
                        if (applyBtn) {
                            applyBtn.innerHTML = 'Set Active Term';
                            applyBtn.disabled = false;
                        }
                    }
                });
            } else {
                location.reload();
            }
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', text: response.message });
            }
            if (applyBtn) {
                applyBtn.innerHTML = 'Set Active Term';
                applyBtn.disabled = false;
            }
        }
    });
}

// Close on outside click
document.addEventListener('click', function (event) {
    const wrapper = document.querySelector('.sys-dropdown-wrapper');
    if (wrapper && !wrapper.contains(event.target)) {
        closeSysDropdown();
    }
});
