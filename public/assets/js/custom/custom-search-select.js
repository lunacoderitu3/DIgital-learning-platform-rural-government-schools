/**
 * Initializes a custom search select dropdown for a targeted standard SELECT element.
 * This preserves option attributes and propagates change events.
 * @param {HTMLSelectElement} selectEl - The original native SELECT element.
 * @param {boolean} forceRebuild - If true, destroys existing wrapper and rebuilds.
 */
function initCustomSearchSelect(selectEl, forceRebuild = false) {
    if (!selectEl) return;

    if (forceRebuild) {
        const existingWrapper = selectEl.closest('.custom-select-wrapper');
        if (existingWrapper) {
            existingWrapper.parentNode.insertBefore(selectEl, existingWrapper);
            existingWrapper.remove();
            selectEl.classList.remove('sr-select-hidden');
            delete selectEl.dataset.customSearchSelectInitialized;
        }
    }

    if (selectEl.dataset.customSearchSelectInitialized) return; // Prevent double init
    selectEl.dataset.customSearchSelectInitialized = 'true';

    // 1. Create wrapper container
    const wrapper = document.createElement('div');
    wrapper.className = 'custom-select-wrapper';
    selectEl.parentNode.insertBefore(wrapper, selectEl);

    // Hide original select visually but keep it focusable & standard (for validation support)
    selectEl.classList.add('sr-select-hidden');
    wrapper.appendChild(selectEl);

    // 2. Create the Trigger element (which looks like the input/select field)
    const trigger = document.createElement('div');
    trigger.className = 'custom-select-trigger';
    trigger.tabIndex = 0; // Make focusable
    // trigger.textContent = selectEl.options[selectEl.selectedIndex]?.text || '-- Choose an option --';
    trigger.textContent =
        selectEl.options[selectEl.selectedIndex]?.text ||
        window.customSelectTranslations.chooseOption;
    wrapper.appendChild(trigger);

    // 3. Create the Dropdown container
    const dropdown = document.createElement('div');
    dropdown.className = 'custom-select-dropdown';

    // 4. Create search container and search input
    const searchContainer = document.createElement('div');
    searchContainer.className = 'custom-select-search-container';

    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = window.trans['search_here'];
    // searchInput.placeholder = window.customSelectTranslations.searchHere;
    searchInput.className = 'custom-select-search-input';

    searchContainer.appendChild(searchInput);
    dropdown.appendChild(searchContainer);

    // 5. Create options list container
    const optionsList = document.createElement('ul');
    optionsList.className = 'custom-select-options-list';

    // No results feedback label
    const noResults = document.createElement('div');
    noResults.className = 'custom-select-no-results';
    // noResults.textContent = 'No matching options found';
    noResults.textContent = window.customSelectTranslations.noMatchingOptions;

    dropdown.appendChild(optionsList);
    dropdown.appendChild(noResults);
    wrapper.appendChild(dropdown);

    // 6. Function to populate custom options from the native select options
    function populateOptions() {
        optionsList.innerHTML = '';

        Array.from(selectEl.options).forEach((option) => {
            // Skip disabled/hidden options and placeholders
            if (option.disabled || option.style.display === 'none' || option.classList.contains('d-none')) return;
            if (option.value === "") return;

            const li = document.createElement('li');
            li.className = 'custom-select-option';
            li.dataset.value = option.value;

            // Keep classes, attributes, etc. in syncing dataset
            Object.assign(li.dataset, option.dataset);

            // Option Text Title
            const optionTextSpan = document.createElement('span');
            optionTextSpan.className = 'option-title';
            optionTextSpan.textContent = option.text;
            li.appendChild(optionTextSpan);

            if (option.dataset.subtext) {
                const metaSpan = document.createElement('span');
                metaSpan.className = 'option-sub-details';
                metaSpan.textContent = option.dataset.subtext;
                li.appendChild(metaSpan);
            }

            // Handle click on specific option
            li.addEventListener('click', (e) => {
                e.stopPropagation();
                selectOption(option.value);
            });

            optionsList.appendChild(li);
        });

        syncCustomSelect();
    }

    // Initialize lists
    populateOptions();

    // 7. Select specific option logic
    function selectOption(val) {
        selectEl.value = val;

        // Set select elements as selected
        Array.from(optionsList.children).forEach(item => {
            if (item.dataset.value === val) {
                item.classList.add('selected');
                trigger.textContent = item.querySelector('.option-title').textContent;
            } else {
                item.classList.remove('selected');
            }
        });

        // Dispatch the change event to the original select
        if (typeof jQuery !== 'undefined') {
            jQuery(selectEl).trigger('change');
        } else {
            const changeEvent = new Event('change', { bubbles: true });
            selectEl.dispatchEvent(changeEvent);
        }

        closeDropdown();
    }

    // 8. Open & Close mechanics
    function toggleDropdown(e) {
        e.stopPropagation();
        const isOpen = dropdown.classList.contains('show');
        if (isOpen) {
            closeDropdown();
        } else {
            openDropdown();
        }
    }

    function openDropdown() {
        // Close any other open custom dropdowns on the page
        document.querySelectorAll('.custom-select-dropdown.show').forEach(el => {
            if (el !== dropdown) el.classList.remove('show');
        });

        dropdown.classList.add('show');
        searchInput.focus();
        searchInput.select();

        // Scroll selected into view if any
        const selectedOption = optionsList.querySelector('.selected');
        if (selectedOption) {
            selectedOption.scrollIntoView({ block: 'nearest' });
        }
    }

    function closeDropdown() {
        dropdown.classList.remove('show');
        searchInput.value = '';
        filterOptions('');
    }

    // 9. Input filtering logic (Search)
    function filterOptions(searchTerm) {
        const query = searchTerm.toLowerCase().trim();
        const items = optionsList.querySelectorAll('.custom-select-option');
        let matchedCount = 0;

        items.forEach(item => {
            const text = item.querySelector('.option-title').textContent.toLowerCase();
            const subtext = (item.querySelector('.option-sub-details')?.textContent || '').toLowerCase();

            const matches = text.includes(query) || subtext.includes(query);

            if (matches) {
                item.style.display = 'flex';
                matchedCount++;
            } else {
                item.style.display = 'none';
            }
        });

        if (matchedCount === 0 && query !== '') {
            noResults.style.display = 'block';
        } else {
            noResults.style.display = 'none';
        }
    }

    // Event Listeners
    trigger.addEventListener('click', toggleDropdown);

    trigger.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openDropdown();
        }
    });

    searchInput.addEventListener('input', (e) => {
        filterOptions(e.target.value);
    });

    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeDropdown();
            trigger.focus();
        }
    });

    const closestForm = selectEl.closest('form');
    if (closestForm) {
        closestForm.addEventListener('reset', () => {
            setTimeout(() => {
                const defaultVal = selectEl.value;
                const defaultText = selectEl.options[selectEl.selectedIndex]?.text || '-- Choose an option --';
                trigger.textContent = defaultText;

                Array.from(optionsList.children).forEach(item => {
                    if (item.dataset.value === defaultVal) {
                        item.classList.add('selected');
                    } else {
                        item.classList.remove('selected');
                    }
                });
            }, 10);
        });
    }

    selectEl.addEventListener('update-custom-select', () => {
        populateOptions();
    });

    function syncCustomSelect() {
        const val = selectEl.value;
        let matched = false;

        Array.from(optionsList.children).forEach(item => {
            if (item.dataset.value == val) {
                item.classList.add('selected');
                trigger.textContent = item.querySelector('.option-title').textContent;
                matched = true;
            } else {
                item.classList.remove('selected');
            }
        });

        if (!matched) {
            // trigger.textContent = selectEl.options[selectEl.selectedIndex]?.text || selectEl.options[0]?.text || '-- Choose an option --';
            trigger.textContent =
                selectEl.options[selectEl.selectedIndex]?.text ||
                selectEl.options[0]?.text ||
                window.customSelectTranslations.chooseOption;
        }
    };

    // Listen for native change events
    selectEl.addEventListener('change', syncCustomSelect);

    // Listen for jQuery change events (since jQuery .trigger('change') doesn't fire native listeners)
    if (typeof jQuery !== 'undefined') {
        jQuery(selectEl).on('change', syncCustomSelect);
    }

    const observer = new MutationObserver((mutations) => {
        populateOptions();
    });
    // Observe childList for added/removed options, and subtree attributes for jQuery .prop('disabled') or .hide()
    observer.observe(selectEl, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['disabled', 'style', 'class']
    });

    // Handle HTML5 validation styling
    selectEl.addEventListener('invalid', () => {
        trigger.style.borderColor = '#dc3545';
        setTimeout(() => trigger.style.borderColor = '', 3000);
    });
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.custom-select-wrapper')) {
        document.querySelectorAll('.custom-select-dropdown.show').forEach(el => {
            el.classList.remove('show');
        });
    }
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.custom-search-select').forEach(select => {
        initCustomSearchSelect(select);
    });

    // Watch for dynamically added elements (like from form repeaters)
    const bodyObserver = new MutationObserver((mutations) => {
        // Disconnect to avoid infinite loop from our own DOM manipulations
        bodyObserver.disconnect();

        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === 1) { // HTMLElement
                    if (node.matches && node.matches('.custom-search-select')) {
                        initCustomSearchSelect(node, true);
                    } else if (node.querySelectorAll) {
                        node.querySelectorAll('.custom-search-select').forEach(select => {
                            initCustomSearchSelect(select, true);
                        });
                    }
                }
            });
        });

        // Reconnect after processing
        bodyObserver.observe(document.body, { childList: true, subtree: true });
    });
    bodyObserver.observe(document.body, { childList: true, subtree: true });
});
