@extends('layouts.master')

@section('title')
    {{ __('exam_reports') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('exam_reports') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            {{-- exam list --}}
                            <div class="col-12 col-sm-12 col-md-4">
                                <label for="filter_exam_id" class="filter-menu">{{ __('Exam') }}</label>
                                <select name="filter_exam_id" id="filter_subject_wise_exam_id"
                                    class="form-control custom-search-select">
                                    <option value="0">{{ __('all') }}</option>
                                    @foreach ($exams as $exam)
                                        <option value="{{ $exam->id }}" data-class-id="{{ $exam->class_id }}">
                                            {{ $exam->name }}
                                            {{ $exam->semester_name ? ' - ' . $exam->semester_name : '' }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- class section list --}}
                            <div class="col-12 col-sm-12 col-md-4">
                                <label for="filter_class_section_id" class="filter-menu">{{ __('Class Section') }}</label>
                                <select name="filter_class_section_id" id="filter_subject_wise_class_section_id"
                                    class="form-control custom-search-select">
                                    <option value="0">{{ __('all') }}</option>
                                    <option value="data-not-found" style="display: none;">-- {{ __('no_data_found') }} --
                                    </option>
                                    @foreach ($classSections as $classSection)
                                        <option value="{{ $classSection->id }}"
                                            data-class-id="{{ $classSection->class_id }}">{{ $classSection->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                        </div>

                        <div class="row">
                            <div class="col-12">
                                <table aria-describedby="mydesc" class='table' id='subject_wise_table_list'
                                    data-toggle="table"
                                    data-url="{{ route('reports.exam.subject-wise-result-show', [1]) }}"
                                    data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                                    data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                                    data-show-columns="true" data-show-refresh="true" data-trim-on-search="false"
                                    data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc"
                                    data-maintain-selected="true" data-export-data-type='all'
                                    data-export-options='{ "fileName": "exam-list-<?= date(' d-m-y') ?>" ,"ignoreColumn":
                                    ["operate"]}'
                                    data-show-export="true" data-detail-formatter="examListFormatter"
                                    data-query-params="getSubjectWiseExamResult" data-escape="true">
                                    <thead>
                                        <tr>
                                            <th rowspan="2" data-field="id" data-sortable="true" data-visible="false">
                                                {{ __('id') }}</th>
                                            <th rowspan="2" data-field="no">{{ __('no.') }}</th>
                                            <th rowspan="2" data-field="user.full_name">{{ __('students_name') }}</th>
                                            <th rowspan="2" data-field="total_marks">{{ __('total_marks') }}</th>
                                            <th rowspan="2" data-field="obtained_marks">{{ __('obtained_marks') }}</th>
                                            <th rowspan="2" data-field="percentage">{{ __('percentage') }}</th>
                                            <th rowspan="2" data-field="grade">{{ __('grade') }}</th>

                                            <!-- 👇 Colspan for Rank -->
                                            <th colspan="2" class="text-center">{{ __('rank') }}</th>

                                            @can('exam-result-edit')
                                                <th rowspan="2" data-field="operate" data-events="examResultEvents"
                                                    data-escape="false">
                                                    {{ __('action') }}
                                                </th>
                                            @endcan
                                        </tr>

                                        <!-- 👇 Second Header Row -->
                                        <tr>
                                            <th data-field="class_rank" class="text-center">{{ __('class') }}</th>
                                            <th data-field="section_rank" class="text-center">{{ __('class_section') }}
                                            </th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            $(document).on('change', '#filter_subject_wise_exam_id', function() {
                let selectedExam = $(this).find('option:selected');
                let classId = selectedExam.data('class-id');
                let classSectionDropdown = $('#filter_subject_wise_class_section_id');

                // Clear current selection
                classSectionDropdown.val('');
                let hasVisibleOptions = false;

                classSectionDropdown.find('option').each(function() {
                    let optionClassId = $(this).data('class-id');

                    // Skip default placeholders
                    if (!$(this).val() || $(this).val() === 'data-not-found') {
                        return;
                    }

                    if (classId) {
                        if (optionClassId == classId) {
                            $(this).show().prop('disabled', false);
                            hasVisibleOptions = true;
                        } else {
                            $(this).hide().prop('disabled', true);
                        }
                    } else {
                        $(this).show().prop('disabled', false);
                        hasVisibleOptions = true;
                    }
                });

                if (!classId || hasVisibleOptions) {
                    classSectionDropdown.find('option[value="data-not-found"]').hide().prop('disabled',
                        true);
                } else {
                    classSectionDropdown.find('option[value="data-not-found"]').show().prop('disabled',
                        false);
                }

                $('#subject_wise_table_list').bootstrapTable('refresh');
            });

            $(document).on('change', '#filter_subject_wise_class_section_id', function() {
                $('#subject_wise_table_list').bootstrapTable('refresh');
            });

            // Trigger on load if there's a pre-selected value
            if ($('#filter_subject_wise_exam_id').val()) {
                $('#filter_subject_wise_exam_id').trigger('change');
            }
        });
    </script>
@endsection
