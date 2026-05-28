@extends('core-layout::master')

@section('content')
    <supplier-show :supplier="{{ $supplier }}"></supplier-show>
@endsection
