@extends('meganet.layout.master')

@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    @if (isset($error))
        <div class="alert alert-danger">
            {{ $error }}
        </div>
    @else
        <div>
            <panel :seller_id="{{ $seller_id }}" :user_id="{{ $user_id }}" :sucursal_id="{{ $sucursal_id }}"
                :is_counter="{{ $is_counter }}">
            </panel>
        </div>
    @endif
@endsection
