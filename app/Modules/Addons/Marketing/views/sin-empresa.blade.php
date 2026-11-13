@extends('core-layout::master')
@section('title') Marketing · Sin empresa @endsection
@section('content')
    <style>
        .mkt-sin-empresa { display: flex; justify-content: center; padding: 48px 16px; }
        .mkt-sin-empresa__tarjeta { max-width: 560px; width: 100%; background: #ffffff; border: 1px solid #e9e9ef;
            border-radius: 8px; padding: 32px 24px; text-align: center; font-family: "IBM Plex Sans", sans-serif; }
        .mkt-sin-empresa__icono { font-size: 40px; color: #74788d; margin-bottom: 16px; }
        .mkt-sin-empresa__titulo { font-size: 20px; font-weight: 600; color: #343a40; margin: 0 0 8px; }
        .mkt-sin-empresa__texto { font-size: 15px; color: #74788d; margin: 0; line-height: 1.5; }
        body[data-layout-mode=dark] .mkt-sin-empresa__tarjeta { background: #313533; border-color: #3b403d; }
        body[data-layout-mode=dark] .mkt-sin-empresa__titulo,
        body[data-layout-mode=dark] .mkt-sin-empresa__texto { color: #ced4da; }
    </style>
    <div class="mkt-sin-empresa">
        <div class="mkt-sin-empresa__tarjeta" role="alert">
            <div class="mkt-sin-empresa__icono"><i class="fas fa-building" aria-hidden="true"></i></div>
            <h1 class="mkt-sin-empresa__titulo">Aún no tienes empresa en Marketing</h1>
            <p class="mkt-sin-empresa__texto">Todavía no perteneces a ninguna empresa de Marketing. Pide a un administrador de Marketing que te agregue en «Empresas y equipo».</p>
        </div>
    </div>
@endsection
