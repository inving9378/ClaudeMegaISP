@extends('core-layout::master')

@section('content')
    @if(config('updates.enabled') && auth()->user()->can('updates.apply'))
        <update-banner></update-banner>
    @endif
    <dashboard></dashboard>
@endsection
