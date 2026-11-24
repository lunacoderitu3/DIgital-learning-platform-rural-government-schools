"use strict";

/**
 * Bootstrap 4 -> Bootstrap 5.3 compatibility layer.
 *
 * The app still ships jQuery 3.4.1 + Popper + perfect-scrollbar AND Bootstrap
 * 4.3.1 bundled together in `vendor.bundle.base.js` (jQuery/scrollbar are
 * load-bearing, so that file can't simply be deleted). Bootstrap 5.3 is loaded
 * afterwards. This file makes Bootstrap 5 the single, authoritative engine for
 * every Bootstrap component and neutralizes the bundled Bootstrap 4 so the two
 * versions can no longer fight (the root cause of modals not closing).
 *
 * It does three things, in order:
 *   1. Unbinds Bootstrap 4's delegated data-api handlers.
 *   2. Replaces the jQuery plugin methods ($.fn.modal/tab/collapse/dropdown/
 *      tooltip/popover/toast) with thin wrappers over the Bootstrap 5 engine,
 *      so the ~40 existing `$('#x').modal('show')`-style calls keep working.
 *   3. Rewrites legacy BS4 data-api markup (data-toggle / data-target /
 *      data-backdrop / data-keyboard / data-parent / data-dismiss) to the BS5
 *      `data-bs-*` equivalents on load and for dynamically injected DOM, so the
 *      existing Blade markup is driven by Bootstrap 5's native data-api.
 *
 * Load order: immediately AFTER bootstrap.bundle.min.js (BS5) and after the
 * vendor bundle (BS4). See resources/views/layouts/footer_js.blade.php.
 */
(function () {
    var $ = window.jQuery;
    var bs = window.bootstrap;

    if (!bs || !bs.Modal) {
        // Bootstrap 5 missing - do nothing rather than break the page.
        if (window.console) console.warn('[bs5-compat] Bootstrap 5 not found; skipping.');
        return;
    }

    // ------------------------------------------------------------------
    // 1) Neutralize Bootstrap 4's delegated data-api (registered on document
    //    by vendor.bundle.base.js). Without this, BS4 would still open/toggle
    //    components on the legacy attributes and collide with BS5.
    //    Only Bootstrap's own namespaces are removed; bootstrap-table
    //    (data-toggle="table"), the admin template's off-canvas/minimize
    //    handlers, glightbox, etc. are untouched.
    // ------------------------------------------------------------------
    if ($) {
        $(document)
            .off('.bs.modal.data-api')
            .off('.bs.dropdown.data-api')
            .off('.bs.tab.data-api')
            .off('.bs.collapse.data-api')
            .off('.bs.button.data-api')
            .off('.bs.alert.data-api')
            .off('.bs.carousel.data-api');
    }

    // ------------------------------------------------------------------
    // 2) jQuery plugin methods backed by Bootstrap 5.
    //    BS5 dropped the jQuery interface; these wrappers restore it so the
    //    existing jQuery call sites run on the BS5 engine.
    // ------------------------------------------------------------------
    if ($ && $.fn) {
        // showOnEmpty: BS4's `.modal()` / `.modal({..})` (no string method)
        // also SHOWS the modal; other components only initialize.
        function makePlugin(Ctor, showOnEmpty) {
            return function (config) {
                var args = Array.prototype.slice.call(arguments, 1);
                return this.each(function () {
                    var options = (config && typeof config === 'object') ? config : {};
                    var instance = Ctor.getOrCreateInstance(this, options);
                    if (typeof config === 'string') {
                        if (typeof instance[config] === 'function') {
                            instance[config].apply(instance, args);
                        }
                    } else if (showOnEmpty && typeof instance.show === 'function') {
                        instance.show();
                    }
                });
            };
        }

        $.fn.modal    = makePlugin(bs.Modal, true);
        $.fn.tab      = makePlugin(bs.Tab, false);
        $.fn.collapse = makePlugin(bs.Collapse, false);
        $.fn.dropdown = makePlugin(bs.Dropdown, false);
        $.fn.tooltip  = makePlugin(bs.Tooltip, false);
        if (bs.Popover) $.fn.popover = makePlugin(bs.Popover, false);
        // Bootstrap toast instances, e.g. $('#actionToast').toast('show').
        // NOTE: the static $.toast({...}) belongs to the separate
        // jquery-toast-plugin and is left completely alone.
        if (bs.Toast) $.fn.toast = makePlugin(bs.Toast, false);
    }

    // ------------------------------------------------------------------
    // 3) Rewrite legacy BS4 data-api attributes to BS5 (data-bs-*).
    //    Only Bootstrap toggle values are migrated; data-toggle="table"
    //    (bootstrap-table), "lightbox", "minimize", "offcanvas", "buttons"
    //    and any non-Bootstrap data-target are intentionally left as-is.
    // ------------------------------------------------------------------
    var BS_TOGGLES = { modal: 1, tab: 1, pill: 1, collapse: 1, dropdown: 1, tooltip: 1, popover: 1 };

    function copyAttr(el, from, to) {
        if (el.hasAttribute(from) && !el.hasAttribute(to)) {
            el.setAttribute(to, el.getAttribute(from));
        }
    }

    function migrateEl(el) {
        if (!el || el.nodeType !== 1 || typeof el.getAttribute !== 'function') return;

        // The sidebar has its own bespoke collapse manager (sidebar-handler.js)
        // that fully owns expand/collapse and must NOT be driven by Bootstrap.
        // Do not add data-bs-* here, or BS5's collapse engine fights the custom
        // manager and collapses parent menus when a nested submenu opens.
        if (typeof el.closest === 'function' && el.closest('#sidebar')) return;

        var toggle = el.getAttribute('data-toggle');
        var isBsToggle = toggle && BS_TOGGLES[toggle];

        if (isBsToggle && !el.hasAttribute('data-bs-toggle')) {
            el.setAttribute('data-bs-toggle', toggle);
        }

        // Trigger-side attributes: only when this element is a Bootstrap control.
        if (isBsToggle || el.hasAttribute('data-dismiss')) {
            copyAttr(el, 'data-target', 'data-bs-target');
            copyAttr(el, 'data-parent', 'data-bs-parent');
            copyAttr(el, 'data-dismiss', 'data-bs-dismiss');
        }

        // Modal option attributes live on the .modal element itself.
        copyAttr(el, 'data-backdrop', 'data-bs-backdrop');
        copyAttr(el, 'data-keyboard', 'data-bs-keyboard');
    }

    function migrateTree(root) {
        if (!root || root.nodeType !== 1) return;
        migrateEl(root);
        var nodes = root.querySelectorAll(
            '[data-toggle],[data-dismiss],[data-backdrop],[data-keyboard],[data-parent]'
        );
        for (var i = 0; i < nodes.length; i++) migrateEl(nodes[i]);
    }

    function initTooltips(root) {
        if (!bs.Tooltip) return;
        var els = root.querySelectorAll('[data-bs-toggle="tooltip"]');
        for (var i = 0; i < els.length; i++) {
            try { bs.Tooltip.getOrCreateInstance(els[i]); } catch (e) { /* noop */ }
        }
    }

    function onReady(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    onReady(function () {
        migrateTree(document.body);
        initTooltips(document.body);

        // Keep dynamically injected DOM (AJAX modals, bootstrap-table rows and
        // their action dropdowns, etc.) working under Bootstrap 5's data-api.
        if (typeof MutationObserver === 'function') {
            var observer = new MutationObserver(function (mutations) {
                for (var i = 0; i < mutations.length; i++) {
                    var added = mutations[i].addedNodes;
                    for (var j = 0; j < added.length; j++) {
                        if (added[j].nodeType === 1) migrateTree(added[j]);
                    }
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    });
})();
