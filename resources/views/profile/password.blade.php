@extends('core-layout::master')

@section('title', 'Cambiar mi contraseña')

@section('content')
<div class="container-fluid">
    <div class="page-title-box">
        <h4 class="mb-0 font-size-18">Mi perfil</h4>
    </div>
    <change-password-form></change-password-form>
</div>
@endsection
