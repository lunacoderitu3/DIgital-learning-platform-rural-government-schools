@extends('layouts.master')

@section('title')
    {{ __('income') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header d-flex align-items-center">
            <a href="{{ route('income.index') }}" class="btn btn-secondary btn-sm mr-3">
                <i class="fa fa-arrow-left"></i> {{ __('back') }}
            </a>
            <h3 class="page-title mb-0">
                {{ __('income_details') }}
            </h3>
        </div>

        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap border-bottom pb-3 mb-3">
                            <div>
                                <h3 class="mb-1">{{ $income->title }}</h3>
                                <p class="mb-0">
                                    <span class="badge badge-info mr-2">{{ $income->category->name ?? '-' }}</span>
                                    <span class="text-muted">{{ $income->reference_no ?? '-' }}</span>
                                </p>
                            </div>
                            <div class="text-right">
                                <h3 class="text-success mb-1">{{ $currencySymbol }}{{ number_format($income->amount, 2) }}</h3>
                                <p class="text-muted mb-0">{{ $income->date }}</p>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <h6 class="text-muted">{{ __('description') }}</h6>
                                <p>{{ $income->description ?: '-' }}</p>
                            </div>
                            <div class="col-md-6 mb-4">
                                <h6 class="text-muted">{{ __('session_year') }}</h6>
                                <p>{{ $income->sessionYear->name ?? '-' }}</p>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <h6 class="text-muted">{{ __('system_information') }}</h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <tbody>
                                            <tr>
                                                <th style="width: 25%">{{ __('created_by') }}</th>
                                                <td>
                                                    @if ($income->creator)
                                                        {{ trim($income->creator->first_name . ' ' . $income->creator->last_name) }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <th style="width: 25%">{{ __('created_at') }}</th>
                                                <td>{{ $income->created_at }}</td>
                                            </tr>
                                            @php
                                                // Only treat the record as "updated" if it was genuinely modified after creation.
                                                $wasUpdated = $income->getRawOriginal('updated_by')
                                                    || $income->getRawOriginal('updated_at') !== $income->getRawOriginal('created_at');
                                            @endphp
                                            @if ($wasUpdated)
                                                <tr>
                                                    <th>{{ __('updated_by') }}</th>
                                                    <td>
                                                        @if ($income->updater)
                                                            {{ trim($income->updater->first_name . ' ' . $income->updater->last_name) }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <th>{{ __('updated_at') }}</th>
                                                    <td>{{ $income->updated_at }}</td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
