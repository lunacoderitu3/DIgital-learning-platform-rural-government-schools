@extends('layouts.master')

@section('title')
    {{ __('manage_fee_reliefs') }}
@endsection

@section('content')
    <!-- Custom CSS for Fee Discount Module -->
    <link rel="stylesheet" href="{{ asset('assets/css/custom/fee_discount.css') }}">

    <div class="content-wrapper bg-white p-3 p-md-4">

        <!-- BREADCRUMB & INTRO ROW -->
        <div class="row align-items-center justify-content-between g-3 mb-4">
            <div class="col-md-8">
                <h2 class="h4 fw-bold mb-1">{{ __('manage_fee_relief_and_waivers') }}
                </h2>
            </div>
        </div>

        <!-- NAVIGATION TABS -->
        <div class="bg-white rounded-top-3 border-bottom shadow-sm">
            <ul class="nav nav-tabs border-0" id="discountTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="workspace-tab" data-toggle="tab" data-target="#workspace-pane"
                        type="button" role="tab"><i
                            class="fa fa-sliders-h me-2"></i>{{ __('student_releif_workspace') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="ledger-tab" data-toggle="tab" data-target="#ledger-pane" type="button"
                        role="tab"><i class="fa fa-users me-2"></i>{{ __('student_fees_ledger') }}</button>
                </li>
                {{-- <li class="nav-item" role="presentation">
                    <button class="nav-link" id="logs-tab" data-toggle="tab" data-target="#logs-pane" type="button"
                        role="tab"><i class="fa fa-history me-1.5"></i>Concession Audit Logs / Rollbacks</button>
                </li> --}}
            </ul>
        </div>

        <div class="tab-content pt-3" id="discountTabContent">

            <!-- TAB 1: WORKSPACE TAB -->
            <div class="tab-pane fade show active" id="workspace-pane" role="tabpanel" tabindex="0">

                <!-- Main form grid workspace -->
                <div class="row g-4">

                    <!-- Left Col: Form fields -->
                    <div class="col-xl-8 col-lg-7 space-y-4">
                        <form id="discountCreationForm" class="row g-4">

                            <!-- SECTION 1: Student Selection -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header"><i class="fa fa-user-tag me-2"></i>1.
                                        {{ __('student_selection_and_target_criteria') }}
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">{{ __('select_class') }} <span
                                                        class="text-danger">*</span></label>
                                                <select class="form-select form-control custom-search-select"
                                                    id="classSelector">
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">{{ __('select_student') }} <span
                                                        class="text-danger">*</span></label>
                                                <select class="form-select form-control custom-search-select"
                                                    id="studentSelector">
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Student details panel -->
                                        <div id="studentProfileWidget" class="bg-light p-3 rounded border"
                                            style="display:none; border-color: #e2e8f0;">
                                            <div class="row g-3 text-center text-md-start">
                                                <div class="col-md-3 border-end">
                                                    <small
                                                        class="text-muted mb-2 d-block text-uppercase fw-bold">{{ __('admission_number') }}</small>
                                                    <span class="fw-bold" style="color: #334155;"
                                                        id="lblAdmissionNo">-</span>
                                                </div>
                                                <div class="col-md-3 border-end">
                                                    <small
                                                        class="text-muted mb-2 d-block text-uppercase fw-bold">{{ __('student_name') }}</small>
                                                    <span class="fw-bold" style="color: #334155;"
                                                        id="lblStudentName">-</span>
                                                </div>
                                                <div class="col-md-3 border-end">
                                                    <small
                                                        class="text-muted mb-2 d-block text-uppercase fw-bold">{{ __('class_section') }}</small>
                                                    <span class="fw-bold" style="color: #334155;"
                                                        id="lblClassSection">-</span>
                                                </div>
                                                <div class="col-md-3">
                                                    <small
                                                        class="text-muted mb-2 d-block text-uppercase fw-bold">{{ __('account_total_dues') }}</small>
                                                    <span class="fw-bold text-danger" id="lblTotalDues">-</span>
                                                </div>
                                            </div>

                                            <!-- Sibling/Scholarship indicators list -->
                                            <div id="existingDiscountsBadgeRow" class="mt-3 pt-2 border-top d-none">
                                                <span
                                                    class="text-secondary fw-bold text-uppercase d-block mb-1">{{ __('active_relief_on_student_account') }}</span>
                                                <div id="lblExistingDiscountsList" class="d-flex flex-wrap gap-1.5">
                                                    <!-- Badges populated dynamically -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 2: Select Target Fee Structure -->
                            <div class="col-12" id="section2Block" style="display:none;">
                                <div class="card">
                                    <div class="card-header"><i class="fa fa-list-check me-2"></i>2.
                                        {{ __('select_target_fee_structure') }}</div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">{{ __('target_fee_structure_dropdown') }} <span
                                                        class="text-danger">*</span></label>
                                                <select class="form-select form-control custom-search-select"
                                                    id="feeStructureSelector">
                                                    <!-- Populated dynamically based on student -->
                                                </select>
                                            </div>
                                            <div class="col-md-6" id="feeStructureInfoBox" style="display:none;">
                                                <div class="bg-light p-3 rounded border">
                                                    <span
                                                        class="text-muted text-uppercase fw-bold d-block mb-1">{{ __('selected_fee_profile') }}</span>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span>{{ __('total_amount') }}</span>
                                                        <span class="fw-bold text-dark"
                                                            id="lblFS_Total">{{ $currencySymbol }}0</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span>{{ __('pending_dues') }}</span>
                                                        <span class="fw-bold text-danger"
                                                            id="lblFS_Pending">{{ $currencySymbol }}0</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <span>{{ __('classification') }}</span>
                                                        <span class="fw-bold text-primary"
                                                            id="lblFS_Classification">-</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Dynamic display of selected components -->
                                        <div id="feeComponentsWidget" class="mt-3 bg-light p-3 rounded border"
                                            style="display:none;">
                                            <h6 class="fw-bold text-dark mb-2">{{ __('fee_structure_balance_breakdown') }}
                                            </h6>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="p-2 bg-white rounded border border-light">
                                                        <span
                                                            class="fw-bold text-primary text-uppercase d-block mb-2 pb-1 border-bottom">{{ __('compulsory_components') }}</span>
                                                        <div id="lblCompulsoryComponentsList">
                                                            <!-- Rendered dynamically -->
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="p-2 bg-white rounded border border-light">
                                                        <span
                                                            class="fw-bold text-success text-uppercase d-block mb-2 pb-1 border-bottom">{{ __('optional_fees_assigned') }}</span>
                                                        <div id="lblOptionalComponentsList">
                                                            <!-- Rendered dynamically -->
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 3: Select Target Constraints -->
                            <div class="col-12" id="section3Block" style="display:none;">
                                <div class="card">
                                    <div class="card-header"><i class="fa fa-sliders me-2"></i>3.
                                        {{ __('configure_relief_scope_selection_and_target') }}</div>
                                    <div class="card-body">
                                        <div class="row g-3">

                                            <div class="col-12">
                                                <div id="discountConfigurationBox">
                                                    <div class="mb-4">
                                                        <label class="form-label d-block mb-2">{{ __('apply_releif_to') }}
                                                            <span class="text-danger">*</span></label>
                                                        <div class="btn-toggle-group w-100"
                                                            id="discountTargetToggleGroup">
                                                            <button type="button" class="btn active"
                                                                data-target="Compulsory Fees"><i
                                                                    class="fa fa-shield-alt me-2"></i>{{ __('compulsory_fees_only') }}</button>
                                                            <button type="button" class="btn"
                                                                data-target="Optional Fees"><i
                                                                    class="fa fa-tag me-2"></i>{{ __('optional_fees_only') }}</button>
                                                        </div>
                                                    </div>

                                                    <!-- Conditional Checklist for Optional Fees -->
                                                    <div id="optionalFeesChecklistContainer"
                                                        class="mb-4 bg-light p-3 rounded border" style="display:none;">
                                                        <label
                                                            class="form-label text-success">{{ __('select_target_optional_fees') }}
                                                            <span class="text-danger">*</span></label>
                                                        <div id="optionalFeesChecklist" class="row g-2 mt-1">
                                                            <!-- Checkboxes rendered dynamically -->
                                                        </div>
                                                        <div
                                                            class="bg-white p-2 border rounded mt-2 d-flex justify-content-between text-success fw-bold">
                                                            <span>{{ __('selected_optional_target_sum') }}</span>
                                                            <span
                                                                id="lblSelectedOptionalsSum">{{ $currencySymbol }}0</span>
                                                        </div>
                                                    </div>

                                                    <!-- Discount Scope Radio Option -->
                                                    <div class="row g-3 mb-4" id="discountScopeRadiosContainer">
                                                        <div class="col-md-6">
                                                            <label
                                                                class="border rounded p-3 d-flex align-items-start w-100 bg-light"
                                                                style="cursor:pointer;" id="scopeEntireLabel">
                                                                <input type="radio" name="scopeSelection"
                                                                    value="Entire Fee Structure" checked
                                                                    class="mt-1 mr-3">
                                                                <div>
                                                                    <span
                                                                        class="d-block fw-bold text-dark">{{ __('entire_fee_structure') }}</span>
                                                                    <span class="d-block text-muted"
                                                                        style="line-height: 1.4;">{{ __('concession_applies_over_the_overall_structure_balance') }}</span>
                                                                </div>
                                                            </label>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label
                                                                class="border rounded p-3 d-flex align-items-start w-100 bg-light"
                                                                style="cursor:pointer;" id="scopeInstallmentLabel">
                                                                <input type="radio" name="scopeSelection"
                                                                    value="Installment Wise" class="mt-1 mr-3">
                                                                <div>
                                                                    <span
                                                                        class="d-block fw-bold text-dark">{{ __('installment_wise_relief') }}</span>
                                                                    <span class="d-block text-muted"
                                                                        style="line-height: 1.4;">{{ __('apply_and_target_specific_active_installment') }}</span>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    </div>

                                                    <!-- Installment checklist row -->
                                                    <div id="installmentListContainer"
                                                        class="mb-4 bg-light p-3 rounded border" style="display:none;">
                                                        <label
                                                            class="form-label text-primary">{{ __('select_target_installments') }}
                                                            <span class="text-danger">*</span></label>
                                                        <div class="table-responsive bg-white rounded border">
                                                            <table class="table table-sm table-hover mb-0">
                                                                <thead class="table-light">
                                                                    <tr>
                                                                        <th class="text-center" style="width: 50px;">
                                                                            {{ __('select') }}</th>
                                                                        <th>{{ __('installment') }}</th>
                                                                        <th>{{ __('original_amount') }}</th>
                                                                        <th>{{ __('paid_amount') }}</th>
                                                                        <th>{{ __('pending_outstanding') }}</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="installmentChecklistTableBody">
                                                                    <!-- Rendered dynamically -->
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                        <div
                                                            class="bg-white p-2 border rounded mt-2 d-flex justify-content-between text-primary fw-bold">
                                                            <span>{{ __('selected_installments_combined_dues') }}</span>
                                                            <span
                                                                id="lblSelectedInstallmentsSum">{{ $currencySymbol }}0</span>
                                                        </div>
                                                    </div>

                                                    <!-- Discount Value Configuration fields -->
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label
                                                                class="form-label">{{ __('relief_value_type') }}</label>
                                                            <div class="btn-toggle-group w-100"
                                                                id="discountTypeToggleGroup">
                                                                <button type="button" class="btn active"
                                                                    data-type="Fixed"><i
                                                                        class="fa fa-rupee-sign me-2"></i>{{ __('fixed_amount') }}
                                                                    ({{ $currencySymbol }})</button>
                                                                <button type="button" class="btn"
                                                                    data-type="Percentage"><i
                                                                        class="fa fa-percent me-2"></i>{{ __('percentage') }}
                                                                    (%)</button>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label"
                                                                id="lblDiscountValueInput">{{ __('releif_amount_value') }}
                                                                ({{ $currencySymbol }}) *</label>
                                                            <input type="number" class="form-control fw-bold"
                                                                id="discountValueInput" placeholder="E.g., 1000"
                                                                value="1000">
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 4: Discount Details -->
                            <div class="col-12" id="section4Block" style="display:none;">
                                <div class="card">
                                    <div class="card-header"><i class="fa fa-file-invoice-dollar me-2"></i>4.
                                        {{ __('relief_details_and_verification') }}</div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label">{{ __('relief_name_title') }} <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control"
                                                    id="discountNameSelector" placeholder="{{ __('e.g_scholarship_concession') }}"
                                                    required>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label">{{ __('relief_allocation_date') }} <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" class="form-control datepicker-popup"
                                                    id="discountDateInput">
                                            </div>
                                            <div class="col-12">
                                                <label
                                                    class="form-label">{{ __('description_internal_references') }}</label>
                                                <textarea class="form-control" id="discountDescriptionInput" rows="2"
                                                    placeholder="{{ __('describe_relief_rationale') }}"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </form>
                    </div>

                    <!-- Right Col: Live preview ledger -->
                    <div class="col-xl-4 col-lg-5">
                        <div class="preview-sticky">
                            <div class="preview-panel-dark p-4 shadow-lg">
                                <h4 class="h6 text-uppercase text-secondary fw-bold mb-3 d-flex align-items-center gap-2"
                                    style="letter-spacing: 0.05em;">
                                    <i class="fa fa-calculator text-info mr-2"></i>
                                    {{ __('fee_relief_live_summary') }}
                                </h4>

                                <!-- Preview details rendered dynamically -->
                                <div id="livePreviewContainer" style="display:none;">
                                    <div class="d-flex align-items-center gap-3 pb-3 border-bottom"
                                        style="border-color: #334155 !important;">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-info"
                                            style="width: 38px; height: 38px; background-color: rgba(14, 165, 233, 0.25);"
                                            id="lblPreviewInitials">
                                            RP
                                        </div>
                                        <div>
                                            <h5 class="h6 mb-0 text-white fw-bold" id="lblPreviewStudentName">Raj Patel
                                            </h5>
                                            <span class="text-secondary" id="lblPreviewStudentAdm">TS-2026-091</span>
                                        </div>
                                    </div>

                                    <div class="pb-3 border-bottom text-secondary"
                                        style="border-color: #334155 !important;">
                                        <div class="d-flex justify-content-between my-2 mt-3">
                                            <span>{{ __('selected_fee_structure') }}:</span>
                                            <strong class="text-white"
                                                id="lblPreviewStructureName">{{ __('admission_fees') }}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between my-2">
                                            <span>{{ __('relief_applied_to') }}:</span>
                                            <strong class="text-success"
                                                id="lblPreviewTargetType">{{ __('compulsory_fees') }}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between my-2" id="lblPreviewOptionalListRow"
                                            style="display:none !important;">
                                            <span>{{ __('selected_optionals') }}:</span>
                                            <strong class="text-light" id="lblPreviewOptionalsCount">0
                                                {{ __('checked') }}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between my-2" id="lblPreviewInstallmentListRow"
                                            style="display:none !important;">
                                            <span>{{ __('selected_installments') }}:</span>
                                            <strong class="text-info" id="lblPreviewInstallmentsCount">0
                                                {{ __('checked') }}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between my-2">
                                            <span>{{ __('original_gross_pending') }}:</span>
                                            <strong class="text-white"
                                                id="lblPreviewOriginalAmount">{{ $currencySymbol }}0</strong>
                                        </div>
                                        <div class="d-flex justify-content-between my-2">
                                            <span>{{ __('relief_value_type') }}:</span>
                                            <strong class="text-white"
                                                id="lblPreviewValType">{{ __('fixed_amount') }}</strong>
                                        </div>
                                    </div>

                                    <!-- Display calculated savings -->
                                    <div class="p-3 rounded text-center border my-3"
                                        style="background-color: rgba(0,0,0,0.5); border-color: #334155 !important;">
                                        <small class="text-secondary text-uppercase fw-bold d-block mb-1"
                                            style="letter-spacing: 0.5px;">{{ __('estimated_waived_reduction') }}</small>
                                        <h3 class="fw-bold text-success mb-0" id="lblPreviewExemptionReduction">-
                                            {{ $currencySymbol }}0</h3>
                                    </div>

                                    <!-- Detailed updated installment checklist changes -->
                                    <div id="previewUpdatedInstallmentsContainer" class="mt-3" style="display:none;">
                                        <span
                                            class="text-secondary text-uppercase fw-bold d-block mb-1">{{ __('updated_installment_amounts') }}:</span>
                                        <div id="previewUpdatedInstallmentsList"
                                            style="max-height: 160px; overflow-y: auto;">
                                            <!-- Dynamic item rows -->
                                        </div>
                                    </div>

                                    <div class="pt-3 border-top text-white" style="border-color: #334155 !important;">
                                        <div class="d-flex justify-content-between my-2">
                                            <span
                                                class="text-secondary font-weight-bold">{{ __('remaining_target_dues') }}</span>
                                            <span class="fw-bold" style="text-decoration: line-through;"
                                                id="lblPreviewOldTarget">{{ $currencySymbol }}0</span>
                                            <span class="fw-bold"
                                                id="lblPreviewRemainingTarget">{{ $currencySymbol }}0</span>
                                        </div>
                                        <div class="d-flex justify-content-between p-2 rounded border mt-3"
                                            style="background-color: #000; border-color: #1e293b !important;">
                                            <span
                                                class="text-warning font-weight-bold">{{ __('new_outstanding_payable') }}</span>
                                            <strong class="text-warning font-weight-bold"
                                                id="lblPreviewTotalOutstanding">{{ $currencySymbol }}0</strong>
                                        </div>
                                    </div>

                                    <!-- Form errors validation box -->
                                    <div id="previewValidationBox"
                                        class="alert alert-danger border-danger text-danger p-2 mt-3 rounded d-none gap-2 align-items-center"
                                        style="background-color: rgba(239, 68, 68, 0.1);">
                                        <i class="fa fa-exclamation-triangle text-danger fs-5 mr-2"></i>
                                        <span id="lblPreviewValidationMsg">{{ __('validation_alert') }}</span>
                                    </div>

                                    <!-- Submit layout trigger -->
                                    <div class="pt-3 mt-2">
                                        <button type="button"
                                            class="btn btn-success w-100 fw-bold py-2 shadow-sm text-uppercase"
                                            id="btnApplyDiscountSubmit">
                                            <i class="fa fa-check-double me-1"></i> {{ __('apply_fee_relief') }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Empty preview prompt -->
                                <div id="livePreviewPlaceholder" class="text-center py-5 text-secondary">
                                    <i class="fa fa-calculator fa-2x mb-3"></i>
                                    <p class="mb-0 font-italic">
                                        {{ __('select_a_student_and_fee_structure_to_generate_live_bill_simulations') }}.
                                    </p>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <!-- TAB 2: STUDENT FEES LEDGER -->
            <div class="tab-pane fade" id="ledger-pane" role="tabpanel" tabindex="0">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
                            <div>
                                <h5 class="fw-bold mb-1">{{ __('student_accounts_ledger_summary') }}</h5>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table aria-describedby="mydesc" class="table table-hover table-bordered mb-0 align-middle"
                                id="table_list" data-toggle="table" data-url="{{ route('fees.discount.list') }}"
                                data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-show-columns="true"
                                data-show-refresh="true" data-fixed-columns="true" data-fixed-number="2"
                                data-fixed-right-number="1" data-trim-on-search="false" data-mobile-responsive="true"
                                data-sort-name="id" data-sort-order="desc" data-maintain-selected="true">
                                <thead class="bg-light text-secondary text-uppercase">
                                    <tr>
                                        <th scope="col" data-field="no">{{ __('no') }}</th>
                                        <th scope="col" data-field="student_name">
                                            {{ __('student_name') }}</th>
                                        <th scope="col" data-field="class_name">{{ __('class_section') }}</th>
                                        <th scope="col" data-field="fee_name">{{ __('fee_structure') }}</th>
                                        <th scope="col" data-field="discount_scope">{{ __('scope') }}</th>
                                        <th scope="col" data-field="discount_amount">
                                            {{ __('total_relief') }}</th>
                                        <th scope="col" data-field="discount_date">
                                            {{ __('date') }}</th>
                                        <th scope="col" data-field="operate" data-events="discountEvents">
                                            {{ __('action') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: AUDIT LOGS -->
            <div class="tab-pane fade" id="logs-pane" role="tabpanel" tabindex="0">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold text-primary mb-1">Fee Relief & Concession Audit Trail</h5>
                        <p class="text-muted mb-3">Continuous security timeline tracking all
                            approved waivers and historical rollbacks for financial transparency.</p>

                        <div class="table-responsive border rounded">
                            <table aria-describedby="mydesc" class="table table-hover table-bordered mb-0 align-middle"
                                id="table_logs" data-toggle="table" data-url="{{ route('fees.discount.logs') }}"
                                data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-show-columns="true"
                                data-show-refresh="true" data-fixed-columns="true" data-fixed-number="2"
                                data-fixed-right-number="1" data-trim-on-search="false" data-mobile-responsive="true"
                                data-sort-name="id" data-sort-order="desc" data-maintain-selected="true">
                                <thead class="bg-light text-secondary text-uppercase">
                                    <tr>
                                        <th scope="col" data-field="no">{{ __('no') }}</th>
                                        <th scope="col" data-field="logged_date">{{ __('logged_date') }}</th>
                                        <th scope="col" data-field="student_name">{{ __('student_profile') }}</th>
                                        <th scope="col" data-field="fee_name">{{ __('fee_structure_name') }}</th>
                                        <th scope="col" data-field="action_name">Action Status</th>
                                        <th scope="col" data-field="done_by">Approver Office</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal: Concession & Discount Breakdown -->
    <div class="modal fade" id="discountBreakdownModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header text-white" style="background-color: var(--brand-primary);">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-white bg-opacity-10 p-2 rounded mr-2"
                            style="background-color: rgba(255,255,255,0.1);">
                            <i class="fa fa-info-circle text-warning" style="font-size: 1.25rem;"></i>
                        </div>
                        <div>
                            <small class="text-uppercase font-monospace d-block text-white-50"
                                style="font-size: 9px; letter-spacing: 0.5px;">{{ __('institutional_concession_detail') }}</small>
                            <h5 class="modal-title fw-extrabold mb-0" id="lblBreakdownTitle">
                                {{ __('relief_breakdown') }}</h5>
                        </div>
                    </div>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true"><i class="fa fa-close"></i></span>
                    </button>
                </div>
                <div class="modal-body p-4" style="font-size: 13px; background-color: #f8fafc;">

                    <!-- Top Student Card Info -->
                    <div class="card mb-4 border-0 shadow-sm" style="background-color: white;">
                        <div class="card-body p-3">
                            <div class="row g-3 text-center text-md-start">
                                <div class="col-md-3 border-right border-end">
                                    <span class="text-muted text-uppercase fw-bold d-block mb-1"
                                        style="font-size: 9px; letter-spacing: 0.5px;">{{ __('student_profile') }}</span>
                                    <h6 class="fw-bold text-dark mb-0" id="lblB_StudentName">-</h6>
                                    <small class="text-secondary font-monospace" id="lblB_StudentAdm">-</small>
                                </div>
                                <div class="col-md-3 border-right border-end">
                                    <span class="text-muted text-uppercase fw-bold d-block mb-1"
                                        style="font-size: 9px; letter-spacing: 0.5px;">{{ __('class_section') }}</span>
                                    <h6 class="fw-bold text-dark mb-0" id="lblB_ClassSection">-</h6>
                                </div>
                                <div class="col-md-3 border-right border-end">
                                    <span class="text-muted text-uppercase fw-bold d-block mb-1"
                                        style="font-size: 9px; letter-spacing: 0.5px;">{{ __('target_fee_record') }}</span>
                                    <h6 class="fw-bold text-primary mb-0" id="lblB_FeeStructure">-</h6>
                                </div>
                                <div class="col-md-3">
                                    <span class="text-muted text-uppercase fw-bold d-block mb-1"
                                        style="font-size: 9px; letter-spacing: 0.5px;">{{ __('concession_scheme') }}</span>
                                    <span class="badge text-warning-emphasis font-bold uppercase py-1 px-2.5"
                                        id="lblB_ReliefType"
                                        style="font-size: 10px; background-color: #fef3c7; color: #d97706;">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mathematical Subledger Breakdown Matrix Row -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4 mb-2">
                            <div class="bg-white border rounded p-3 text-center shadow-sm">
                                <span class="text-secondary uppercase font-semibold d-block mb-1"
                                    style="font-size: 10px;">{{ __('total_fee_amount') }}</span>
                                <h4 class="fw-black text-slate-800 mb-0 font-weight-bold" id="lblB_TotalFees">
                                    {{ $currencySymbol }}0</h4>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="border rounded p-3 text-center shadow-sm"
                                style="background-color: #ecfdf5; border-color: #a7f3d0;">
                                <span class="text-success uppercase font-bold d-block mb-1"
                                    style="font-size: 10px;">{{ __('relief_amount_granted') }}</span>
                                <h4 class="fw-black text-success mb-0 font-weight-bold" id="lblB_DiscountAmount">-
                                    {{ $currencySymbol }}0</h4>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="border rounded p-3 text-center shadow-sm"
                                style="background-color: #eff6ff; border-color: #bfdbfe;">
                                <span class="text-primary uppercase font-bold d-block mb-1"
                                    style="font-size: 10px;">{{ __('net_adjusted_outstanding') }}</span>
                                <h4 class="fw-black text-primary mb-0 font-weight-bold" id="lblB_NetAdjusted">
                                    {{ $currencySymbol }}0</h4>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Details Row based on scope selection -->
                    <div class="card mb-4 border-0 shadow-sm" style="background-color: white;">
                        <div class="card-header border-bottom bg-light bg-opacity-25 py-2.5 px-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="fw-bold text-primary uppercase tracking-wide" style="font-size: 11px;">
                                    <i class="fa fa-list-check me-1 mr-1"></i>
                                    {{ __('target_concession_scope_parameters') }}
                                </span>
                                <span class="badge badge-secondary text-uppercase py-1 px-2 font-monospace"
                                    id="lblB_ScopeBadge" style="font-size: 9px;">-</span>
                            </div>
                        </div>
                        <div class="card-body p-3" id="lblB_ScopeDetailsContainer">
                            <!-- Populated Dynamically with Installment tables or Optional lists -->
                        </div>
                    </div>

                    <!-- Footer Details Stamps (Approved date, Ref logs) -->
                    <div class="row g-2 text-muted border-top pt-3" style="font-size: 11px;">
                        <div class="col-md-6 d-flex align-items-center gap-2 mb-2">
                            <i class="fa fa-calendar-check text-slate-400 mr-2"></i>
                            <span>{{ __('concession_date') }}: <strong class="text-dark"
                                    id="lblB_Date">-</strong></span>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">{{ __('close') }}</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Simulated Payment Collection -->
    <div class="modal fade" id="paymentCollectionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white border-bottom-0 py-3">
                    <h6 class="modal-title fw-bold"><i class="fa fa-cash-register me-2 text-warning"></i> Quick Collect
                        Outstanding Payment</h6>
                    <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4 bg-light">

                    <div class="bg-white p-3 rounded border mb-3 text-center shadow-sm">
                        <span class="d-block text-secondary text-uppercase fw-bold mb-1">Target
                            Payment For</span>
                        <h5 class="text-dark fw-bold mb-0" id="lblPaymentStudentName">-</h5>
                        <span class="text-primary font-weight-bold" style="font-size: 12px;"
                            id="lblPaymentFeeName">-</span>
                    </div>

                    <div class="row g-2 mb-4 text-center">
                        <div class="col-4">
                            <div class="p-2 border rounded bg-white">
                                <small class="text-muted d-block">Gross Total</small>
                                <span class="fw-bold" id="lblPaymentFeeGross">{{ $currencySymbol }}0</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border rounded bg-white border-warning">
                                <small class="text-warning d-block">Total Relief</small>
                                <span class="fw-bold text-warning"
                                    id="lblPaymentFeeDiscounts">-{{ $currencySymbol }}0</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border rounded bg-white border-primary">
                                <small class="text-primary d-block">Net Payable</small>
                                <span class="fw-bold text-primary" id="lblPaymentFeeNet">{{ $currencySymbol }}0</span>
                            </div>
                        </div>
                    </div>

                    <div id="paymentInputsContainer">
                        <form id="paymentCollectionForm">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-end mb-1">
                                    <label class="form-label text-dark mb-0">Current Pending Outstanding
                                        ({{ $currencySymbol }})</label>
                                    <span class="fw-bold text-danger fs-5"
                                        id="lblPaymentFeePending">{{ $currencySymbol }}0</span>
                                </div>
                                <input type="number" class="form-control form-control-lg fw-bold"
                                    id="paymentAmountInput" required min="1">
                            </div>
                            <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm"><i
                                    class="fa fa-wallet me-2"></i> Process Payment Entry</button>
                        </form>
                    </div>

                    <div id="paymentSuccessFeedback"
                        class="d-none alert alert-success text-center border-0 shadow-sm mb-0">
                        <i class="fa fa-check-circle fs-2 d-block mb-2 text-success"></i>
                        <span class="fw-bold text-success">Payment collected successfully!</span>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Simple Toast Element -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
        <div id="actionToast" class="toast align-items-center text-white bg-success border-0" role="alert"
            aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i id="toastIcon" class="fa fa-check-circle mr-2"></i>
                    <span id="toastMessage">Action successful!</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                    aria-label="Close"></button>
            </div>
        </div>
    </div>
@endsection

@section('script')

    <script>
        window.translations = {
    concessionPercentageValue: @json(__('Concession Percentage Value (%) *')),
    concessionAmountValue: @json(__('Concession Amount Value')),
    eg10: @json(__('E.g., 10')),
    eg1000: @json(__('E.g., 1000'))
};
        let globalCurrencySymbol = '{{ $currencySymbol }}';
        let CLASSES = @json(
            $classes->map(function ($c) {
                return ['id' => $c->id, 'name' => $c->name . ' ' . $c->medium->name, 'short' => $c->name];
            }));
    </script>
    <script src="{{ asset('assets/js/custom/fee_discount.js') }}"></script>
@endsection
