 @extends('core-layout::master')

 @section('title')
     @lang('translation.Dashboard')
 @endsection
 
 @section('content')
    <div>
        <user-listar :create-user="{{ json_encode(route('user.create')) }}"
            roles-filtro="{{ json_encode($rolesFiltro ?? []) }}"></user-listar>
    </div>
 @endsection
 