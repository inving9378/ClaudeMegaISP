@extends('core-layout::master')

@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <div>
        <dashboard-sellers></dashboard-sellers>
    </div>
@endsection