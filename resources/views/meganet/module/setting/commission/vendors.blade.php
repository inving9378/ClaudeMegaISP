@extends('meganet.layout.master')

@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <div>
        <list-sellers :id_rule="{{ $id_rule }}"></list-sellers>
    </div>
@endsection