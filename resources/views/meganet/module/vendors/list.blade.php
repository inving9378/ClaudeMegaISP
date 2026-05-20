@extends('core-layout::master')

@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <div>
        <vendedor-listar></vendedor-listar>
    </div>
@endsection