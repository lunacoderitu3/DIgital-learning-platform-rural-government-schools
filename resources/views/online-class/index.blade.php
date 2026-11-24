@extends('layouts.master')

@section('title')
    {{ __('online_class') }}
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/custom/online-classes.css') }}">
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="fw-bold mb-1 text-dark">{{ __('online_classes') }}</h5>
            </div>
        </div>

        <!-- 1. CREATE ONLINE CLASS CARD -->
        <section class="card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="card-title">{{ __('create_online_class') }}</h5>
                </div>
            </div>

            <div class="card-body">
                <form id="create-form" action="{{ route('online-class.store') }}" method="POST" novalidate="novalidate">
                    @csrf
                    <div class="row g-4">
                        <div class="col-lg-6 col-md-12">
                            <label class="form-label form-label-custom" for="subject_teacher_id">{{ __('subject_class') }}
                                <span class="text-danger">*</span></label>
                            <select name="subject_teacher_id" id="subject_teacher_id"
                                class="form-select form-control custom-search-select" required
                                data-placeholder="{{ __('select_option') }}">
                                <option value="">{{ __('select_option') }}</option>
                                @foreach ($subjectTeachers as $st)
                                    <option value="{{ $st->id }}">
                                        {{ $st->subject->name ?? '' }} - {{ $st->subject->type ?? '' }}
                                        &mdash; {{ optional($st->class_section)->full_name }}
                                        @if ($st->teacher)
                                            &mdash; {{ $st->teacher->full_name }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-6 col-md-12">
                            <label class="form-label form-label-custom">{{ __('repeat') }} <span
                                    class="text-danger">*</span></label>
                            <div class="repeat-radio-group btn-group w-100" role="group">
                                @foreach ($repeatTypes as $i => $type)
                                    <input type="radio" class="btn-check" name="repeat" id="repeat_{{ $i }}"
                                        value="{{ $type }}" {{ $i === 0 ? 'checked' : '' }} autocomplete="off">
                                    <label class="btn btn-outline-primary py-2" for="repeat_{{ $i }}">
                                        @if ($type == 'No Repeat' || $type == \App\Models\Timetable::REPEAT_NO)
                                            <i class="fa-solid fa-ban me-1"></i>
                                        @elseif($type == 'Every Class Period' || $type == \App\Models\Timetable::REPEAT_EVERY_CLASS_PERIOD)
                                            <i class="fa-solid fa-repeat me-1"></i>
                                        @else
                                            <i class="fa-solid fa-calendar-week me-1"></i>
                                        @endif
                                        {{ __($type) }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-12" id="periods_wrapper" style="display:none;">
                            <label class="form-label form-label-custom mb-2">{{ __('which_periods') }} <span
                                    class="text-danger">*</span></label>
                            <p class="text-muted mt-0 mb-3 period-help" style="font-size: 0.8rem;"></p>

                            <div class="row g-3" id="periods_container"></div>

                            <div id="periods_loader" class="text-muted" style="display:none;">{{ __('loading') }}...</div>
                            <div id="periods_empty" class="text-danger" style="display:none;">{{ __('no_periods_found') }}
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-12" id="start_date_group">
                            <label class="form-label form-label-custom" for="start_date">{{ __('start_date') }} <span
                                    class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="text" name="start_date" id="start_date" autocomplete="off"
                                    class="form-control form-control datepicker-popup" placeholder="{{ __('start_date') }}"
                                    required />
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-12" id="end_date_group">
                            <label class="form-label form-label-custom" for="end_date">{{ __('end_date') }} <span
                                    class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="text" name="end_date" id="end_date" autocomplete="off"
                                    class="form-control form-control datepicker-popup"
                                    placeholder="{{ __('end_date') }}" />
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-12">
                            <label class="form-label form-label-custom" for="meeting_link">{{ __('meeting_link') }} <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-video"></i></span>
                                {!! Form::text('meeting_link', null, [
                                    'required',
                                    'class' => 'form-control student-input',
                                    'placeholder' => __('meeting_link'),
                                ]) !!}
                            </div>

                        </div>

                        <div class="col-12">
                            <label class="form-label form-label-custom" for="note">{{ __('note') }}</label>
                            <textarea name="note" id="note" class="form-control form-control" rows="3"
                                placeholder="{{ __('note') }}"></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-3 mt-4 pt-3 border-top border-light-subtle">
                        <button type="reset" class="btn btn-secondary">
                            {{ __('reset') }}
                        </button>
                        <button type="submit" id="create-btn" class="btn btn-theme">
                            {{ __('submit') }}
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- 2. LIST ONLINE CLASSES -->
        <section class="card mt-4">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="card-title">{{ __('list_online_classes') }}</h5>
                </div>

                <div id="toolbar">
                    <div class="row g-3">
                        <div class="col-xl-4 col-md-6 col-sm-12">
                            <div class="align-items-center gap-2">
                                <span class="text-nowrap text-muted">{{ __('class_section') }}:</span>
                                {!! Form::select('class_section_id', $classes, null, [
                                    'class' => 'form-select form-control custom-search-select py-1.5 px-2.5 text-sm ml-1',
                                    'id' => 'filter_oc_class_section_id',
                                    'placeholder' => __('all'),
                                    'style' => 'font-size:0.825rem;',
                                ]) !!}
                            </div>
                        </div>
                        <div class="col-xl-4 col-md-6 col-sm-12">
                            <div class="align-items-center gap-2">
                                <span class="text-nowrap text-muted">{{ __('subject') }}:</span>
                                {!! Form::select('subject_id', $subjects, null, [
                                    'class' => 'form-select form-control custom-search-select py-1.5 px-2.5 text-sm',
                                    'id' => 'filter_oc_subject_id',
                                    'placeholder' => __('all'),
                                    'style' => 'font-size:0.825rem;',
                                ]) !!}
                            </div>
                        </div>
                        <div class="col-xl-4 col-md-6 col-sm-12">
                            <div class="align-items-center gap-2">
                                <span class="text-nowrap text-muted">{{ __('repeat') }}:</span>
                                {!! Form::select('repeat', array_combine($repeatTypes, $repeatTypes), null, [
                                    'class' => 'form-select form-control custom-search-select py-1.5 px-2.5 text-sm',
                                    'id' => 'filter_oc_repeat',
                                    'placeholder' => __('all'),
                                    'style' => 'font-size:0.825rem;',
                                ]) !!}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table aria-describedby="mydesc" class='table custom-table table-hover align-middle' id='table_list'
                        data-toggle="table" data-url="{{ route('online-class.show') }}" data-click-to-select="true"
                        data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                        data-search="true" data-show-columns="true" data-show-refresh="true" data-trim-on-search="false"
                        data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc"
                        data-maintain-selected="true" data-query-params="OnlineClassQueryParams" data-toolbar="#toolbar"
                        data-escape="true">
                        <thead>
                            <tr>
                                <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                    {{ __('id') }}</th>
                                <th scope="col" data-field="no" class="text-center" style="width: 50px;">
                                    {{ __('no.') }}</th>
                                <th scope="col" data-field="subject" data-sortable="false">{{ __('subject') }}</th>
                                <th scope="col" data-field="class_section" data-sortable="false">
                                    {{ __('class_section') }}</th>
                                <th scope="col" data-field="day" data-sortable="false">{{ __('day') }}</th>
                                <th scope="col" data-field="period" data-sortable="false">{{ __('period') }}</th>
                                <th scope="col" data-field="start_date" data-sortable="false">{{ __('start_date') }}
                                </th>
                                <th scope="col" data-field="end_date" data-sortable="false">{{ __('until') }}</th>
                                <th scope="col" data-field="repeat" data-formatter="repeatBadgeFormatter"
                                    data-sortable="false">{{ __('repeat') }}</th>
                                <th scope="col" data-field="meeting_link" data-formatter="meetingLinkFormatter"
                                    data-sortable="false">{{ __('meeting_link') }}</th>
                                <th scope="col" data-field="note" data-sortable="false">{{ __('note') }}</th>
                                <th scope="col" data-field="operate" data-events="OnlineClassEvents"
                                    data-escape="false" class="text-center" style="width: 100px;">{{ __('action') }}
                                </th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>

        </section>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">{{ __('edit_online_class') }}</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form class="pt-3 edit-form" action="" novalidate="novalidate">
                        @csrf
                        <div class="modal-body">
                            <input type="hidden" name="edit_id" id="edit_id" value="" />
                            <div class="row g-4">
                                <div class="col-lg-6 col-md-12">
                                    <label class="form-label form-label-custom">{{ __('subject_class') }}</label>
                                    <input type="text" id="edit_subject_display" class="form-control form-control"
                                        readonly />
                                </div>
                                <div class="col-lg-6 col-md-12">
                                    <label class="form-label form-label-custom">{{ __('period') }}</label>
                                    <input type="text" id="edit_period_display" class="form-control form-control"
                                        readonly />
                                </div>

                                <div class="col-12">
                                    <label class="form-label form-label-custom">{{ __('repeat') }} <span
                                            class="text-danger">*</span></label>
                                    {!! Form::select('repeat', array_combine($repeatTypes, $repeatTypes), null, [
                                        'class' => 'form-select form-control',
                                        'id' => 'edit_repeat',
                                        'required',
                                    ]) !!}
                                </div>

                                <div class="col-md-6 col-sm-12" id="edit_start_date_group">
                                    <label class="form-label form-label-custom"
                                        for="edit_start_date">{{ __('start_date') }} <span
                                            class="text-danger">*</span></label>
                                    <input type="text" name="start_date" id="edit_start_date" autocomplete="off"
                                        class="form-control form-control datepicker-popup" required />
                                </div>

                                <div class="col-md-6 col-sm-12" id="edit_end_date_group">
                                    <label class="form-label form-label-custom" for="edit_end_date">{{ __('end_date') }}
                                        <span class="text-danger">*</span></label>
                                    <input type="text" name="end_date" id="edit_end_date" autocomplete="off"
                                        class="form-control form-control datepicker-popup" />
                                </div>

                                <div class="col-12">
                                    <label class="form-label form-label-custom"
                                        for="edit_meeting_link">{{ __('meeting_link') }} <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-video"></i></span>
                                        {!! Form::text('meeting_link', null, [
                                            'required',
                                            'class' => 'form-control student-input',
                                            'placeholder' => __('meeting_link'),
                                            'id' => 'edit_meeting_link',
                                        ]) !!}
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label form-label-custom"
                                        for="edit_note">{{ __('note') }}</label>
                                    <textarea name="note" id="edit_note" rows="2" class="form-control form-control"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary rounded-3"
                                data-bs-dismiss="modal">{{ __('cancel') }}</button>
                            <input class="btn btn-primary rounded-3" type="submit" value="{{ __('update') }}"
                                style="background-color: var(--primary-color); border-color: var(--primary-color);">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        window.onlineClassConfig = {
            periodsUrl: "{{ route('online-class.periods') }}",
            subjectsUrl: "{{ route('online-class.subjects') }}",
            repeatEveryClassPeriod: "{{ \App\Models\Timetable::REPEAT_EVERY_CLASS_PERIOD }}",
            repeatNoRepeat: "{{ \App\Models\Timetable::REPEAT_NO }}",
            translations: {
                configured: "{{ __('configured') }}",
                all_periods_included: "{{ __('all_periods_included_for_every_class_period') }}",
                select_one_or_more: "{{ __('select_one_or_more_periods') }}",
                all: "{{ __('all') }}"
            }
        };
    </script>
    <script src="{{ asset('assets/js/custom/online-classes.js') }}"></script>
@endsection
