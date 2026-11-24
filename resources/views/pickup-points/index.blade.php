@extends('layouts.master')

@section('title')
    {{ __('pickup_points') }}
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/qr-attendance.css') }}?v={{ config('app.version') }}">
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('manage_pickup_points') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{ __('create_pickup_point') }}
                        </h4>
                        <form class="pt-3 pickup-point-create-form" id="create-form"
                            action="{{ route('pickup-points.store') }}" method="POST" novalidate="novalidate">
                            @csrf
                            <div class="row">
                                <div class="col-sm-12 col-md-6">
                                    <div class="form-group col-md-12">
                                        <label>{{ __('name') }} <span class="text-danger">*</span></label>
                                        <input name="name" type="text" placeholder="{{ __('name') }}"
                                            class="form-control" required />
                                    </div>
                                    <div class="form-group col-md-12">
                                        <label>{{ __('status') }}</label>
                                        <select name="status" class="form-control">
                                            <option value="1">{{ __('Active') }}</option>
                                            <option value="0">{{ __('Inactive') }}</option>
                                        </select>
                                    </div>

                                    <div class="form-group col-md-12">
                                        <label>{{ __('latitude') }}</label>
                                        <input name="latitude" id="create_latitude" type="text"
                                            placeholder="{{ __('latitude') }}" class="form-control" />
                                    </div>
                                    <div class="form-group col-md-12">
                                        <label>{{ __('longitude') }}</label>
                                        <input name="longitude" id="create_longitude" type="text"
                                            placeholder="{{ __('longitude') }}" class="form-control" />
                                    </div>
                                </div>

                                <div class="col-sm-12 col-md-6">
                                    <div class="col-md-12">
                                        <div class="qr-map-wrap" id="create-map-wrap">
                                            <div class="qr-map-canvas" id="create_map"></div>
                                            <div class="qr-map-preview" id="create-map-preview">
                                                <i class="fa fa-map-marker qr-pv-pin"></i>
                                                <span class="qr-pv-flag">
                                                    <i class="fa fa-info-circle mr-1"></i>{{ __('google_maps_api_key') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>



                            </div>
                            <div class="row">

                            </div>
                            <div class="row mb-3">

                            </div>
                            <input class="btn btn-theme float-right" id="create-btn" type="submit"
                                value={{ __('submit') }}>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{ __('list_pickup_points') }}
                        </h4>
                        <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                            data-url="{{ route('pickup-points.show', [1]) }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                            data-search="true" data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                            data-fixed-columns="false" data-fixed-number="2" data-fixed-right-number="1"
                            data-trim-on-search="false" data-mobile-responsive="true" data-sort-name="id"
                            data-sort-order="desc" data-maintain-selected="true" data-query-params="queryParams"
                            data-show-export="true" data-export-options='{"fileName": "pickup-points-list-<?= date('d-m-y')
                            ?>","ignoreColumn":
                            ["operate"]}'
                            data-escape="true">
                            <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                        {{ __('id') }}</th>
                                    <th scope="col" data-field="no">{{ __('no.') }}</th>
                                    <th scope="col" data-field="name" data-formatter="pickupDropFormatter"
                                        data-sortable="true">{{ __('name') }}</th>
                                    <th scope="col" data-field="transportation_fees"
                                        data-formatter="transportationFeesFormatter" data-escape="false"
                                        data-sortable="false">{{ __('transportation_fees') }}</th>
                                    <th scope="col" data-field="status" data-formatter="activeStatusFormatter"
                                        data-sortable="false">{{ __('status') }}</th>
                                    <th scope="col" data-field="operate" data-formatter="actionColumnFormatter"
                                        data-events="pickupPointEvents" data-escape="false">{{ __('action') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Modal -->
            <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-md" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">{{ __('edit_pickup_point') }}</h5>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form class="pt-3 pickup-point-edit-form" id="edit-form" action="{{ url('pickup-points') }}"
                            method="POST" novalidate="novalidate">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="edit_id" id="edit_id" value="" />
                            <div class="modal-body">
                                <div class="row">
                                    <div class="form-group col-md-12">
                                        <label>{{ __('name') }} <span class="text-danger">*</span></label>
                                        <input name="name" id="edit_name" type="text"
                                            placeholder="{{ __('name') }}" class="form-control" required />
                                    </div>
                                    <div class="form-group col-md-12">
                                        <label>{{ __('status') }}</label>
                                        <select name="status" id="edit_status" class="form-control">
                                            <option value="1">{{ __('Active') }}</option>
                                            <option value="0">{{ __('Inactive') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label>{{ __('latitude') }}</label>
                                        <input name="latitude" id="edit_latitude" type="text"
                                            placeholder="{{ __('latitude') }}" class="form-control" />
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>{{ __('longitude') }}</label>
                                        <input name="longitude" id="edit_longitude" type="text"
                                            placeholder="{{ __('longitude') }}" class="form-control" />
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <div class="qr-map-wrap" id="edit-map-wrap">
                                            <div class="qr-map-canvas" id="edit_map"></div>
                                            <div class="qr-map-preview" id="edit-map-preview">
                                                <i class="fa fa-map-marker qr-pv-pin"></i>
                                                <span class="qr-pv-flag">
                                                    <i class="fa fa-info-circle mr-1"></i>{{ __('google_maps_api_key') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary"
                                    data-bs-dismiss="modal">{{ __('close') }}</button>
                                <input class="btn btn-theme" type="submit" value={{ __('submit') }} />
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        window.pickupPointMapsApiKey = @json($googleMapsKey);

        let createMap, editMap;
        let createMarker, editMarker;

        window.initMaps = function() {
            const defaultLat = 0;
            const defaultLng = 0;
            const defaultLocation = {
                lat: defaultLat,
                lng: defaultLng
            };

            // Create Map
            createMap = new google.maps.Map(document.getElementById("create_map"), {
                zoom: 2,
                center: defaultLocation,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
            });

            $('#create-map-wrap').addClass('is-live');

            createMarker = new google.maps.Marker({
                position: defaultLocation,
                map: createMap,
                draggable: true,
            });

            createMap.addListener("click", (mapsMouseEvent) => {
                createMarker.setPosition(mapsMouseEvent.latLng);
                updateCreateInputs(mapsMouseEvent.latLng);
            });

            createMarker.addListener("dragend", () => {
                updateCreateInputs(createMarker.getPosition());
            });

            function updateCreateInputs(latLng) {
                document.getElementById('create_latitude').value = latLng.lat().toFixed(6);
                document.getElementById('create_longitude').value = latLng.lng().toFixed(6);
            }

            // Edit Map
            editMap = new google.maps.Map(document.getElementById("edit_map"), {
                zoom: 2,
                center: defaultLocation,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
            });

            $('#edit-map-wrap').addClass('is-live');

            editMarker = new google.maps.Marker({
                position: defaultLocation,
                map: editMap,
                draggable: true,
            });

            editMap.addListener("click", (mapsMouseEvent) => {
                editMarker.setPosition(mapsMouseEvent.latLng);
                updateEditInputs(mapsMouseEvent.latLng);
            });

            editMarker.addListener("dragend", () => {
                updateEditInputs(editMarker.getPosition());
            });

            function updateEditInputs(latLng) {
                document.getElementById('edit_latitude').value = latLng.lat().toFixed(6);
                document.getElementById('edit_longitude').value = latLng.lng().toFixed(6);
            }

            // Try to get user's location for Create Map if inputs are empty
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const pos = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                        };
                        if (createMap && !document.getElementById('create_latitude').value) {
                            createMap.setCenter(pos);
                            createMap.setZoom(14);
                            createMarker.setPosition(pos);
                            document.getElementById('create_latitude').value = pos.lat.toFixed(6);
                            document.getElementById('create_longitude').value = pos.lng.toFixed(6);
                        }
                    }
                );
            }
        };

        $(document).ready(function() {
            setTimeout(() => {
                var key = $.trim(window.pickupPointMapsApiKey || "");

                if (!key) {
                    return;
                }

                if (window.google && window.google.maps) {
                    window.initMaps();
                    return;
                }

                var script = document.createElement('script');
                script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) +
                    '&callback=initMaps&loading=async';
                script.async = true;
                script.onerror = function() {
                    showErrorToast(window.trans['Error Occurred'] || 'Could not reach Google Maps');
                };
                document.head.appendChild(script);

            }, 500);
        });
    </script>
@endsection
