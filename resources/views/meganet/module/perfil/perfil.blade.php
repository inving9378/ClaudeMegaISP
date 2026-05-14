@extends('core-layout::master')

@section('content')
    <Breadcrumb
        :list=[{title:"Perfil",active:"active"}]
    ></Breadcrumb>

    <perfil
    id="{{ $id }}"
    ></perfil>
@endsection
