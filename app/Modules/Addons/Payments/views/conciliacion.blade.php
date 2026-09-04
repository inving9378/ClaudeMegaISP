@extends('core-layout::master')
@section('title') Cola de Conciliación @endsection

@section('content')
    @if(auth()->user() && auth()->user()->can('reconciliation_view'))
        <reconciliation-queue
            :can-resolve="{{ auth()->user()->can('reconciliation_resolve') ? 'true' : 'false' }}">
        </reconciliation-queue>
    @endif
@endsection
