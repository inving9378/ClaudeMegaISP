@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Setting"},{title:"EmailSetting",active:"active"}]></Breadcrumb>
    <Email-Setting-Edit>

    </Email-Setting-Edit>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="EmailSetting"></Message>
    @endif
@endsection
