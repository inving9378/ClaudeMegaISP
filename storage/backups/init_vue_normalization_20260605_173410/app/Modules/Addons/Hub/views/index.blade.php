@extends('core-layout::master')

@section('title', 'Integration Hub')

@section('styles')
<link rel="stylesheet" href="{{ asset('plugins/quasar/extras/bootstrap-icons/bootstrap-icons.css') }}">
@endsection

@section('content')
<div id="init-vue">
    <integrations-hub-view></integrations-hub-view>
</div>
@endsection
