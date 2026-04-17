@extends('meganet.layout.master')

@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <div>
        <edit-rule :id_rule="{{ $id_rule }}"></edit-rule>
    </div>
@endsection