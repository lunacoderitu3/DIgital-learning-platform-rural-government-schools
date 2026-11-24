    @extends('layouts.master')

    @section('title')
        {{ __('database_backup') }}
    @endsection

    @section('content')
        <div class="content-wrapper">
            <div class="page-header">
                <h3 class="page-title">
                    {{ __('manage_backup') }}
                </h3>
            </div>
            <div class="row">
                <!-- Left Column: Generate Backup -->
                <div class="col-md-6 grid-margin">
                    <div class="card" style="width: 100%; height: 300px;"> <!-- Fixed height and width -->
                        <div class="custom-card-body">
                            <div class="row">

                                <div class="col-md-12 text-left">
                                    <h4>{{ __('generate_backup') }}</h4>

                                    <button class="btn create-backup btn-theme btn-sm">{{ __('generate_backup') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Restore Backup -->
                <div class="col-md-6 grid-margin stretch-card" id="restore-container">
                    <div class="card" style="width: 100%; height: 300px;"> <!-- Fixed height and width -->
                        <div class="card-body">
                            <h4>{{ __('select_zip') }}</h4>

                            <form class="pt-3 restore-form" id="create-form"
                                action="{{ route('database-backup.restore', $schoolId) }}" method="POST"
                                enctype="multipart/form-data">
                                <div class="col-12">
                                    <input type="hidden" name="school_id" value="{{ $schoolId }}">
                                    <div class="form-group">
                                        <label>{{ __('zip_file') }} <span class="text-danger">*</span></label>
                                        <input type="file" name="zip_file" id="zip_file" class="file-upload-default"
                                            accept=".zip,application/zip,application/x-zip-compressed" />
                                        <div class="input-group col-xs-12">
                                            <input type="text" class="form-control file-upload-info" disabled=""
                                                placeholder="{{ __('zip_file') }}" aria-label="" />
                                            <span class="input-group-append">
                                                <button class="file-upload-browse btn btn-theme"
                                                    type="button">{{ __('upload') }}</button>
                                            </span>
                                        </div>
                                    </div>
                                    <input class="btn btn-theme float-right ml-3" id="create-btn" type="submit"
                                        value="{{ __('submit') }}">
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">{{ __('backup_list') }}</h4>
                            <table aria-describedby="mydesc" class='table table-striped' id='table_list' data-toggle="table"
                                data-url="{{ url('database-backup/show') }}" data-click-to-select="true"
                                data-side-pagination="server" data-pagination="true"
                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-show-columns="true"
                                data-show-refresh="true" data-trim-on-search="false" data-mobile-responsive="true"
                                data-sort-name="id" data-sort-order="desc" data-maintain-selected="true"
                                data-export-types='["txt","excel"]'
                                data-export-options='{ "fileName": "backup-list-<?= date('d-m-y') ?>" ,"ignoreColumn":
                                ["operate"]}'
                                data-show-export="true">
                                <thead>
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                            {{ __('id') }}</th>
                                        <th scope="col" data-field="no" data-sortable="false">{{ __('no.') }}</th>
                                        <th scope="col" data-field="name" data-sortable="true">{{ __('name') }}</th>
                                        <th scope="col" data-field="status" data-sortable="false"
                                            data-formatter="statusFormatter">{{ __('status') }}</th>
                                        <th scope="col" data-field="created_at" data-sortable="true">
                                            {{ __('created_at') }}</th>
                                        <th scope="col" data-field="operate" data-sortable="false"
                                            data-events="backupEvents">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection

    @section('script')
        <script>
            function statusFormatter(value, row, index) {
                return value;
            }
        </script>
    @endsection
