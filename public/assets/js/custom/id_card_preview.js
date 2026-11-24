document.addEventListener('DOMContentLoaded', function () {

    // Zoom handling
    let currentZoom = 1;
    const canvasWrapper = document.getElementById('id-card-canvas-wrapper');
    const zoomInBtn = document.getElementById('zoom-in');
    const zoomOutBtn = document.getElementById('zoom-out');
    const zoomResetBtn = document.getElementById('zoom-reset');

    function setZoom(zoom) {
        currentZoom = Math.max(0.2, Math.min(zoom, 3));
        if (canvasWrapper) {
            canvasWrapper.style.transform = `scale(${currentZoom})`;
        }
    }

    if (zoomInBtn) zoomInBtn.addEventListener('click', (e) => { e.preventDefault(); setZoom(currentZoom + 0.1); });
    if (zoomOutBtn) zoomOutBtn.addEventListener('click', (e) => { e.preventDefault(); setZoom(currentZoom - 0.1); });
    if (zoomResetBtn) zoomResetBtn.addEventListener('click', (e) => { e.preventDefault(); setZoom(1); });

    // Reactivity setup
    const updatePreview = () => {
        const type = document.querySelector('input[name="type"]:checked')?.value || 'Student';
        const prefix = type === 'Student' ? '' : 'staff_';

        const canvasId = type === 'Student' ? 'preview-student-canvas' : 'preview-staff-canvas';
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        // Colors
        const headerColor = document.getElementById(prefix + 'header_color')?.value || document.querySelector(`input[name="${prefix}header_color"]`)?.value;
        const footerColor = document.getElementById(prefix + 'footer_color')?.value || document.querySelector(`input[name="${prefix}footer_color"]`)?.value;
        const headerFooterTextColor = document.getElementById(prefix + 'header_footer_text_color')?.value || document.querySelector(`input[name="${prefix}header_footer_text_color"]`)?.value;
        const textColor = document.getElementById(prefix + 'text_color')?.value || document.querySelector(`input[name="${prefix}text_color"]`)?.value || '#333333';

        if (headerColor) canvas.style.setProperty('--header-color', headerColor);
        if (footerColor) canvas.style.setProperty('--footer-color', footerColor);
        if (headerFooterTextColor) canvas.style.setProperty('--header-text-color', headerFooterTextColor);
        if (textColor) canvas.style.setProperty('--text-color', textColor);

        // Dimensions
        const width = document.getElementById(prefix + 'page_width')?.value || document.querySelector(`input[name="${prefix}page_width"]`)?.value;
        const height = document.getElementById(prefix + 'page_height')?.value || document.querySelector(`input[name="${prefix}page_height"]`)?.value;

        if (width) canvas.style.setProperty('--card-width', width + 'mm');
        if (height) canvas.style.setProperty('--card-height', height + 'mm');

        // Update badge dimensions
        const dimBadge = document.getElementById('preview-dimensions');
        if (dimBadge && width && height) dimBadge.innerText = `${width}mm x ${height}mm`;

        // Layout Type
        const layoutType = document.querySelector(`input[name="${prefix}layout_type"]:checked`)?.value;
        if (layoutType === 'horizontal') {
            canvas.classList.add('horizontal');
            // Swap width and height for horizontal layout
            if (width) canvas.style.setProperty('--card-width', width + 'mm');
            if (height) canvas.style.setProperty('--card-height', height + 'mm');
        } else {
            canvas.classList.remove('horizontal');
            if (width) canvas.style.setProperty('--card-width', width + 'mm');
            if (height) canvas.style.setProperty('--card-height', height + 'mm');
        }

        // Profile Image Style
        const profileStyle = document.querySelector(`input[name="${prefix}profile_image_style"]:checked`)?.value;
        const profileElement = canvas.querySelector('.id-card-profile');
        if (profileElement) {
            profileElement.classList.remove('round', 'square');
            if (profileStyle === 'round') profileElement.classList.add('round');
            else if (profileStyle === 'square' || profileStyle === 'squre') profileElement.classList.add('square');
        }

        // Font Family
        const fontFamily = document.querySelector(`select[name="${prefix}font_family"]`)?.value || 'Inter';
        canvas.style.setProperty('--font-family', fontFamily);

        // Border Radius
        const borderRadius = document.querySelector(`input[name="${prefix}border_radius"]`)?.value || 0;
        canvas.style.setProperty('--border-radius', borderRadius + 'px');

        // Opacity
        const opacity = document.querySelector(`input[name="${prefix}bg_opacity"]`)?.value || 100;
        canvas.style.setProperty('--bg-opacity', opacity / 100);

        // Fields toggle
        const fieldNamePrefix = type === 'Student' ? 'student_id_card_fields[]' : 'staff_id_card_fields[]';
        const checkboxes = document.querySelectorAll(`input[name="${fieldNamePrefix}"]`);

        checkboxes.forEach(cb => {
            const fieldRow = canvas.querySelector(`.preview-field-${cb.value}`);
            if (fieldRow) {
                fieldRow.style.display = cb.checked ? 'flex' : 'none';
            }
        });

        // Extra Fields toggle
        const extraCheckboxes = document.querySelectorAll(`input[name="extra_form_fields[]"]`);
        extraCheckboxes.forEach(cb => {
            const fieldRow = canvas.querySelector(`.preview-field-extra-${cb.value}`);
            if (fieldRow) {
                // Determine if this extra field belongs to current user type
                // The HTML wrapper could hold user_type data attribute
                const wrapper = cb.closest('.feature-chip');
                if (wrapper && wrapper.dataset.usertype == (type === 'Student' ? '1' : '2')) {
                    fieldRow.style.display = cb.checked ? 'flex' : 'none';
                } else {
                    fieldRow.style.display = 'none'; // hide if wrong user type
                }
            }
        });

    };

    // Attach listeners
    const formInputs = document.querySelectorAll('.id-card-settings-panel input, .id-card-settings-panel select');
    formInputs.forEach(input => {
        input.addEventListener('input', updatePreview);
        input.addEventListener('change', updatePreview);
    });

    // Special handling for Type (Student/Staff)
    const typeInputs = document.querySelectorAll('input[name="type"]');
    typeInputs.forEach(input => {
        input.addEventListener('change', function () {
            if (this.value === 'Student') {
                document.getElementById('student-id-card').style.display = 'block';
                document.getElementById('staff-id-card').style.display = 'none';

                document.getElementById('preview-student-canvas').style.display = 'flex';
                document.getElementById('preview-staff-canvas').style.display = 'none';
            } else {
                document.getElementById('student-id-card').style.display = 'none';
                document.getElementById('staff-id-card').style.display = 'block';

                document.getElementById('preview-student-canvas').style.display = 'none';
                document.getElementById('preview-staff-canvas').style.display = 'flex';
            }
            // Trigger update
            setTimeout(updatePreview, 50);
        });
    });

    // Background Image upload preview
    const handleBgUpload = (inputName, canvasSelector) => {
        const input = document.querySelector(`input[name="${inputName}"]`);
        if (input) {
            input.addEventListener('change', function () {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const canvas = document.querySelector(canvasSelector);
                        if (canvas) canvas.style.setProperty('--bg-image', `url('${e.target.result}')`);
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
    };

    handleBgUpload('background_image', '#preview-student-canvas');
    handleBgUpload('staff_background_image', '#preview-staff-canvas');

    // Initial render
    setTimeout(() => {
        const activeType = document.querySelector('input[name="type"]:checked');
        if (activeType) {
            // Manually trigger change event to setup visibility
            const event = new Event('change');
            activeType.dispatchEvent(event);
        } else {
            updatePreview();
        }
    }, 100);

    // Initial Background image load from existing settings
    const stuBgInput = document.querySelector('input[name="existing_background_image"]');
    if (stuBgInput && stuBgInput.value) {
        document.getElementById('preview-student-canvas').style.setProperty('--bg-image', `url('${stuBgInput.value}')`);
    }
    const staffBgInput = document.querySelector('input[name="existing_staff_background_image"]');
    if (staffBgInput && staffBgInput.value) {
        document.getElementById('preview-staff-canvas').style.setProperty('--bg-image', `url('${staffBgInput.value}')`);
    }

});
