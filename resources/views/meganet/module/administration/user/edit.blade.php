@extends('core-layout::master')

@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <div>
        <user-edit :user="{{ $user }}" :seller="{{ $seller }}" :sucursals="{{ $sucursals }}" :is-super-admin="{{ auth()->user()->hasRole('super-administrator') ? 'true' : 'false' }}"></user-edit>
    </div>
@endsection
