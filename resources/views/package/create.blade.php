@extends('layouts.master')

@section('title')
    {{ __('package') }}
@endsection

@section('css')
    @include('package.partials.form-style')
@endsection

@section('content')
    @include('package.partials.form-content', ['isEditMode' => false])
@endsection

@section('script')
    @include('package.partials.form-script', ['isEditMode' => false])
@endsection
