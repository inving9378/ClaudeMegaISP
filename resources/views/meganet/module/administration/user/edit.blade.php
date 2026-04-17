@extends('meganet.layout.master')

@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <div>
        <user-edit :user="{{ $user }}" :seller="{{ $seller }}" :sucursals="{{ $sucursals }}"></user-edit>
    </div>
@endsection
