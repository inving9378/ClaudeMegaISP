@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Setting"},{title:"CompanyInformation",active:"active"}]></Breadcrumb>
    <Company-Information-Edit>

    </Company-Information-Edit>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="CompanyInformation"></Message>
    @endif
@endsection
