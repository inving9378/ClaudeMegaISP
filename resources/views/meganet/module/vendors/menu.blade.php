@extends('meganet.layout.master')

@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <div>
        <menu-seller :seller_id="{{ $seller_id }}" :user_id="{{ $user_id }}" :sucursal_id="{{ $sucursal_id }}"
            :is_counter="{{ $is_counter }}" :mediums_of_sales="{{ $mediums_of_sales }}"></menu-seller>
    </div>
@endsection
