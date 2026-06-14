@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Ticket #' . str_pad($ticket->id, 6, '0', STR_PAD_LEFT))

@section('content')
<div class="page-header">
    <div>
        <h1>🎫 Ticket T-{{ str_pad($ticket->id, 6, '0', STR_PAD_LEFT) }}</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">{{ $ticket->topic }}</p>
    </div>
    <a href="{{ route('portal.tickets') }}" class="btn btn-outline btn-sm">← Volver</a>
</div>

@php
    $badgeClass = match($ticket->estado) {
        'Nuevo' => 'badge-info',
        'Trabajo en curso' => 'badge-warning',
        'Resuelto','Cerrado' => 'badge-success',
        'Esperando al cliente' => 'badge-warning',
        default => 'badge-secondary',
    };
    $cerrado = in_array($ticket->estado, ['Cerrado','Resuelto','Reciclado']);
@endphp

<div style="display:flex; gap:.75rem; margin-bottom:1.5rem; flex-wrap:wrap; align-items:center">
    <span class="badge {{ $badgeClass }}" style="font-size:.9rem; padding:.35rem .9rem">{{ $ticket->estado }}</span>
    <span style="color:var(--text-muted); font-size:.82rem">Tipo: {{ $ticket->type ?? '—' }}</span>
    <span style="color:var(--text-muted); font-size:.82rem">Creado: {{ $ticket->created_at ? \Carbon\Carbon::parse($ticket->created_at)->format('d/m/Y H:i') : '—' }}</span>
</div>

{{-- Hilo de conversación --}}
<div class="card">
    <div class="card-title">💬 Conversación</div>

    @if($hilos->isEmpty())
        <div style="color:var(--text-muted); text-align:center; padding:1.5rem; font-size:.875rem">
            Sin mensajes en este ticket aún.
        </div>
    @else
        <div style="display:flex; flex-direction:column; gap:1rem">
            @foreach($hilos as $hilo)
            @php
                $esCliente = is_null($hilo->edited_by);
                $autor = $esCliente ? 'Tú' : $hilo->autor;
            @endphp
            <div style="display:flex; flex-direction:column; align-items:{{ $esCliente ? 'flex-end' : 'flex-start' }}">
                <div style="
                    background: {{ $esCliente ? 'var(--pcolor)' : 'var(--surface)' }};
                    color: {{ $esCliente ? '#fff' : 'var(--text)' }};
                    border-radius: {{ $esCliente ? '14px 14px 4px 14px' : '14px 14px 14px 4px' }};
                    padding: .75rem 1rem;
                    max-width: 80%;
                    font-size: .875rem;
                    line-height: 1.5;
                    border: 1px solid {{ $esCliente ? 'transparent' : 'var(--border)' }};
                ">{{ $hilo->message }}</div>
                <div style="font-size:.75rem; color:var(--text-muted); margin-top:.25rem">
                    {{ $autor }} · {{ $hilo->created_at ? \Carbon\Carbon::parse($hilo->created_at)->diffForHumans() : '' }}
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Responder --}}
@if(!$cerrado)
<div class="card">
    <div class="card-title">✍️ Agregar respuesta</div>
    <form method="POST" action="{{ route('portal.tickets.reply', $ticket->id) }}">
        @csrf
        <div class="form-group">
            <textarea name="respuesta" class="form-control" rows="4"
                placeholder="Escribe tu mensaje aquí..." required maxlength="3000"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Enviar respuesta</button>
    </form>
</div>
@else
<div class="alert alert-info">
    Este ticket está {{ strtolower($ticket->estado) }}. Si necesitas ayuda adicional, crea un nuevo ticket.
</div>
@endif
@endsection
