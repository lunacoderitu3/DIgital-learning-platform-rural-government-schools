<style>
    /* Minimal custom styles — only for selection states that Bootstrap cannot express.
       All layout, spacing, grid, and typography use Bootstrap classes.
       Scoped to the page wrapper (.pkg-create) so it applies to both Create & Edit forms. */

    /* Billing type selectable cards (hidden radios + Bootstrap-bordered cards) */
    .pkg-create .billing-option {
        display: block;
        height: 100%;
        cursor: pointer;
    }

    .pkg-create .billing-option>input {
        position: absolute;
        opacity: 0;
        width: 1px;
        height: 1px;
        margin: 0;
    }

    .pkg-create .billing-option .billing-card {
        height: 100%;
        border: 2px solid #e2e8f0 !important;
        border-radius: .6rem !important;
        padding: 1rem !important;
        transition: all .15s ease;
    }

    .pkg-create .billing-option>input:checked+.billing-card {
        border-color: #2563eb !important;
        background-color: #eff6ff !important;
    }

    .pkg-create .billing-option>input:checked+.billing-card .billing-title {
        color: #2563eb !important;
    }

    /* Feature toggles — built on the existing .feature-list / .feature-list-default hooks */
    .pkg-create .feature-list,
    .pkg-create .feature-list-default {
        position: relative;
        display: flex;
        align-items: center;
        width: 100%;
        min-height: 52px;
        margin: 0;
        padding: .75rem 1rem .75rem 2.85rem;
        border: 1px solid #e2e8f0;
        border-radius: .5rem;
        line-height: 1.3;
        font-size: .875rem;
        font-weight: 500;
        color: #475569;
        cursor: pointer;
        transition: all .15s ease;
    }

    .pkg-create .feature-list::before {
        content: '';
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        width: 18px;
        height: 18px;
        border: 2px solid #cbd5e1;
        border-radius: 50%;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-family: "FontAwesome";
        font-size: 10px;
    }

    .pkg-create .feature-list:hover {
        border-color: #cbd5e1;
    }

    .pkg-create input:checked+.feature-list {
        border: 1px solid #2563eb !important;
        background: #eff6ff;
        color: #2563eb !important;
    }

    .pkg-create input:checked+.feature-list::before {
        content: "\f00c";
        border-color: #2563eb;
        background: #2563eb;
    }

    .pkg-create .feature-list-default {
        border: 1px solid #e2e8f0 !important;
        background: #f8fafc;
        color: #64748b !important;
        cursor: not-allowed;
    }

    .pkg-create input:checked+.feature-list-default::before {
        content: "\f023";
        font-family: "FontAwesome";
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 13px;
        margin: 0;
    }

    /* ---- Reference-matched polish (scoped — no shared/global styles touched) ---- */
    .pkg-create>.card {
        border-radius: .85rem;
        border-color: #e6e9ef;
        overflow: hidden;
    }

    /* Clip nested category cards so the header background follows the rounded corners */
    .pkg-create .pkg-selectable-card {
        overflow: hidden;
    }

    .pkg-create .form-control {
        /* background: #f8fafc; */
        border: 1px solid #e2e8f0;
        border-radius: .55rem;
        padding: .6rem .85rem;
        height: auto;
        font-size: .9rem;
        color: #1e293b;
        transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }

    .pkg-create textarea.form-control {
        min-height: 96px;
    }

    .pkg-create .form-control:focus {
        background: #fff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, .18);
    }

    .pkg-create .form-control::placeholder {
        color: #94a3b8;
    }

    /* Leading-icon fields ($ / calendar) — seamless single field */
    .pkg-create .input-group-text {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-right: 0;
        color: #94a3b8;
        border-radius: .55rem 0 0 .55rem;
    }

    .pkg-create .input-group>.form-control {
        border-left: 0;
        border-radius: 0 .55rem .55rem 0;
    }

    .pkg-create .input-group:focus-within .input-group-text {
        border-color: #3b82f6;
        background: #fff;
    }

    /* Selectable-module headers: keep the category title on a single line */
    .pkg-create .pkg-selectable-card .card-header .custom-control {
        flex: 1 1 auto;
        min-width: 0;
    }

    .pkg-create .pkg-selectable-card .card-header .custom-control-label {
        white-space: nowrap;
        line-height: 1.2;
    }

    /* Vertically center the checkbox box + check mark with the title text */
    .pkg-create .pkg-selectable-card .card-header .custom-control-label::before,
    .pkg-create .pkg-selectable-card .card-header .custom-control-label::after {
        top: 50%;
        transform: translateY(-50%);
    }

    /* Highlight / Instant-effects — plain native checkbox + label (no .custom-control
       pseudo-elements, so labels can never overflow/overlap). Blue tint via accent-color. */
    .pkg-create .pkg-check {
        display: inline-flex;
        align-items: center;
        margin: 0;
        cursor: pointer;
        font-weight: 500;
        color: #334155;
        white-space: nowrap;
    }

    /* Hide the native input; the in-flow .pkg-check-box span is the visual checkbox
       (same robust pattern as the feature cards — nothing absolute, so it can't overlap). */
    .pkg-create .pkg-check-input {
        position: absolute;
        opacity: 0;
        width: 1px;
        height: 1px;
        margin: 0;
    }

    .pkg-create .pkg-check-box {
        width: 18px;
        height: 18px;
        flex: 0 0 auto;
        margin-right: .5rem;
        border: 2px solid #cbd5e1;
        border-radius: 5px;
        background: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-family: "FontAwesome";
        font-size: 10px;
        transition: border-color .15s ease, background-color .15s ease;
    }

    .pkg-create .pkg-check-input:checked+.pkg-check-box {
        border-color: #2563eb;
        background: #2563eb;
    }

    .pkg-create .pkg-check-input:checked+.pkg-check-box::after {
        content: "\f00c";
    }

    .pkg-create .pkg-check-input:focus-visible+.pkg-check-box {
        box-shadow: 0 0 0 3px rgba(59, 130, 246, .2);
    }

    .pkg-create .popover-container .fa-info-circle {
        cursor: pointer;
    }

    /* jQuery-validate error messages: for $/icon fields the message is inserted inside the
       .input-group flex row — force it onto its own full-width line below the field. */
    .pkg-create .input-group label.error,
    .pkg-create .input-group .error {
        flex: 0 0 100%;
        max-width: 100%;
        width: 100%;
        margin-top: .4rem;
    }
</style>
