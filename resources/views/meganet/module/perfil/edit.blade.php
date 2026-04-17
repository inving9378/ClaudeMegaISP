@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <Breadcrumb
        :list=[{title:"Perfil"},{title:"Editar",active:"active"}]
    ></Breadcrumb>

    <perfil-edit
        action="update/{{$id}}"
        id="{{ $id }}"
    ></perfil-edit>
@endsection
