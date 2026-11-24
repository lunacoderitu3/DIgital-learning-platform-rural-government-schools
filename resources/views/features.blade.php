@extends('layouts.master')

@section('title')
    {{ __('features') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('features') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('list_features') }}</h4>
                        <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                            data-url="{{ route('features.show') }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                            data-search="true" data-show-columns="true" data-show-refresh="true" data-fixed-columns="false"
                            data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false"
                            data-mobile-responsive="true" data-sort-name="id" data-sort-order="asc"
                            data-maintain-selected="true" data-export-data-type='all' data-query-params="queryParams"
                            data-toolbar="#toolbar" data-export-options='{ "fileName": "features-list-<?= date('d-m-y') ?>"
                            ,"ignoreColumn":["operate"]}' data-show-export="true" data-escape="true">
                            <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                        {{ __('id') }}</th>
                                    <th scope="col" data-field="no">{{ __('no.') }}</th>
                                    <th scope="col" data-field="name" data-sortable="true">{{ __('name') }}</th>
                                    <th scope="col" data-field="permission" data-formatter="featurePermissionFormatter"
                                        data-sortable="false" data-escape="false">{{ __('permission') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Global WhatsApp Messaging feature settings (Super Admin) ===== --}}
        @role('Super Admin')
            <div class="row">
                <div class="col-md-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">
                                <i class="fa fa-whatsapp text-success"></i> {{ __('whatsapp_settings') }}
                            </h4>
                            <p class="text-muted small">{{ __('whatsapp_feature_settings_note') }}</p>

                            <form class="create-form-without-reset" action="{{ route('features.whatsapp-settings') }}" novalidate="novalidate">
                                @csrf
                                <div class="row">
                                    @php
                                        $whatsappToggles = [
                                            'whatsapp_allow_bulk' => 'allow_bulk_messaging',
                                            'whatsapp_allow_templates' => 'allow_template_management',
                                            'whatsapp_allow_media' => 'allow_media_messages',
                                            'whatsapp_allow_documents' => 'allow_pdf_documents',
                                            'whatsapp_allow_logs' => 'allow_message_logs',
                                            'whatsapp_allow_test' => 'allow_test_message',
                                        ];
                                    @endphp
                                    @foreach ($whatsappToggles as $toggleName => $toggleLabel)
                                        <div class="form-group col-md-4">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="{{ $toggleName }}" name="{{ $toggleName }}" value="1"
                                                    {{ $whatsappSettings[$toggleName] == '1' ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="{{ $toggleName }}">{{ __($toggleLabel) }}</label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <input class="btn btn-theme float-right" type="submit" value="{{ __('save') }}">
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endrole
    </div>
@endsection

@section('script')
@endsection
