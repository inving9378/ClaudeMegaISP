@extends('core-layout::master')

@section('title')
    Manual Operativo de Meganet
@endsection

@section('content')
    <manual-index
        :is-developer="{{ auth()->user()?->can('manual_generate') ? 'true' : 'false' }}">
    </manual-index>
@endsection
