<div class="card border-0 shadow-sm p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h5 class="mb-0 font-weight-bold text-dark">
                <i class="fa fa-calendar-times theme-color mr-2"></i>{{ __('leave_report') }}
            </h5>
            <small class="text-muted">{{ __('student_leave_history_details') }}</small>
        </div>
    </div>

    @php
        $totalPendingLeaves = 0;
        $totalPendingDays = 0;
        $totalApprovedLeaves = 0;
        $totalApprovedDays = 0;
        $totalRejectedLeaves = 0;
        $totalRejectedDays = 0;

        if (isset($leaves)) {
            foreach ($leaves as $leave) {
                $startDate = \Carbon\Carbon::parse($leave->from_date);
                $endDate = \Carbon\Carbon::parse($leave->to_date);
                $days = $startDate->diffInDays($endDate) + 1;
                $leave->total_days = $days;

                if ($leave->status == 0) {
                    $totalPendingLeaves++;
                    $totalPendingDays += $days;
                } elseif ($leave->status == 1) {
                    $totalApprovedLeaves++;
                    $totalApprovedDays += $days;
                } elseif ($leave->status == 2) {
                    $totalRejectedLeaves++;
                    $totalRejectedDays += $days;
                }
            }
        }
    @endphp

    <div class="row mb-4">
        <div class="col-md-4 mb-3 mb-lg-0">
            <div class="card bg-light border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-warning text-white rounded p-3 mr-3">
                        <i class="fa fa-clock fa-lg"></i>
                    </div>
                    <div>
                        <div class="text-muted small font-weight-bold text-uppercase">{{ __('pending') }}</div>
                        <h4 class="mb-0 font-weight-bold">{{ $totalPendingLeaves }} <small
                                class="text-muted h6">({{ $totalPendingDays }} {{ __('days') }})</small></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3 mb-lg-0">
            <div class="card bg-light border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-success text-white rounded p-3 mr-3">
                        <i class="fa fa-check-circle fa-lg"></i>
                    </div>
                    <div>
                        <div class="text-muted small font-weight-bold text-uppercase">{{ __('approved') }}</div>
                        <h4 class="mb-0 font-weight-bold">{{ $totalApprovedLeaves }} <small
                                class="text-muted h6">({{ $totalApprovedDays }} {{ __('days') }})</small></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3 mb-lg-0">
            <div class="card bg-light border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-danger text-white rounded p-3 mr-3">
                        <i class="fa fa-times-circle fa-lg"></i>
                    </div>
                    <div>
                        <div class="text-muted small font-weight-bold text-uppercase">{{ __('rejected') }}</div>
                        <h4 class="mb-0 font-weight-bold">{{ $totalRejectedLeaves }} <small
                                class="text-muted h6">({{ $totalRejectedDays }} {{ __('days') }})</small></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (isset($leaves) && count($leaves) > 0)
        <div class="table-responsive">
            <table class="table table-hover border-bottom mb-0">
                <thead class="bg-light text-uppercase text-muted small font-weight-bold">
                    <tr>
                        <th class="border-0 px-3 py-3">{{ __('no') }}</th>
                        <th class="border-0 px-3 py-3">{{ __('from_date') }}</th>
                        <th class="border-0 px-3 py-3">{{ __('to_date') }}</th>
                        <th class="border-0 px-3 py-3">{{ __('total_days') }}</th>
                        <th class="border-0 px-3 py-3">{{ __('reason') }}</th>
                        <th class="border-0 px-3 py-3">{{ __('added_by') }}</th>
                        <th class="border-0 px-3 py-3">{{ __('status') }}</th>
                        <th class="border-0 px-3 py-3">{{ __('reject_reason') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($leaves as $key => $leave)
                        <tr>
                            <td class="align-middle px-3 py-3">{{ $key + 1 }}</td>
                            <td class="align-middle px-3 py-3 text-nowrap">
                                <i class="fa fa-calendar text-muted mr-1"></i>
                                {{ date('d-m-Y', strtotime($leave->from_date)) }}
                            </td>
                            <td class="align-middle px-3 py-3 text-nowrap">
                                <i class="fa fa-calendar text-muted mr-1"></i>
                                {{ date('d-m-Y', strtotime($leave->to_date)) }}
                            </td>
                            <td class="align-middle px-3 py-3">{{ $leave->total_days }}</td>
                            <td class="align-middle px-3 py-3 text-muted">
                                {{ $leave->reason }}
                            </td>
                            <td class="align-middle px-3 py-3 text-muted">
                                @if ($leave->added_by_user)
                                    {{ $leave->added_by_user->first_name }} {{ $leave->added_by_user->last_name }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="align-middle px-3 py-3">
                                @if ($leave->status == 0)
                                    <span class="badge badge-warning px-2 py-1"><i
                                            class="fa fa-clock mr-1"></i>{{ __('pending') }}</span>
                                @elseif($leave->status == 1)
                                    <span class="badge badge-success px-2 py-1"><i
                                            class="fa fa-check-circle mr-1"></i>{{ __('approved') }}</span>
                                @elseif($leave->status == 2)
                                    <span class="badge badge-danger px-2 py-1"><i
                                            class="fa fa-times-circle mr-1"></i>{{ __('rejected') }}</span>
                                @endif
                            </td>
                            <td class="align-middle px-3 py-3 text-muted">
                                @if ($leave->status == 2)
                                    {{ $leave->note ?? '-' }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="text-center py-5">
            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                style="width: 80px; height: 80px;">
                <i class="fa fa-folder-open text-muted fa-2x"></i>
            </div>
            <h6 class="text-muted font-weight-bold">{{ __('no_leaves_found') }}</h6>
            <p class="text-muted small mb-0">{{ __('no_leave_records_available_for_this_student') }}</p>
        </div>
    @endif
</div>
