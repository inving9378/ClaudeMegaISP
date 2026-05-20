@extends('core-layout::master')

@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <div>
        <range-sale-config></range-sale-config>
    </div>
@endsection