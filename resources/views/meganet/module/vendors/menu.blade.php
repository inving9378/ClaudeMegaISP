@extends('core-layout::master')

@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <div>
        <menu-seller :seller_id="{{ $seller_id }}" :user_id="{{ $user_id }}" :sucursal_id="{{ $sucursal_id }}"
            :is_counter="{{ $is_counter }}" :mediums_of_sales="{{ $mediums_of_sales }}"
            seller_nombre="{{ $seller_nombre ?? '' }}" seller_estado="{{ $seller_estado ?? '' }}"
            :seller_activo="{{ ($seller_activo ?? true) ? 'true' : 'false' }}"></menu-seller>
    </div>
@endsection
