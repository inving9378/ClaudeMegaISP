@extends('meganet.layout.master')

@section('title')
    Contenidos del Submenú
@endsection

@push('scripts')
    <script src="https://cdn.ckeditor.com/ckeditor5/41.0.0/classic/ckeditor.js"></script>
@endpush

@section('content')

    <Breadcrumb :list="[
        {title: 'Inicio', link: '/home'},
        {title: 'Administración', link: '/administracion'},
        {title: 'Documentación', link: '/adminstracion/documentation/documentation_menu'},
        {title: 'Subtítulos', link: '/administracion/documentation/documentation_submenu'},
        {title: 'Contenido', active: true},
    ]"></Breadcrumb>

    <documentation-content 
        submenu="{{ json_encode($submenu) }}" 
        contents="{{ json_encode($contents) }}">
    </documentation-content>
@endsection