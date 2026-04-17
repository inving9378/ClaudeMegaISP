 @extends('meganet.layout.master')

 @section('title')
     @lang('translation.Dashboard')
 @endsection
 
 @section('content')
    <div>
        <user-listar :create-user="{{ json_encode(route('user.create')) }}"></user-listar>
    </div>
 @endsection
 