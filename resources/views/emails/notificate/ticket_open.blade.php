@component('mail::message')
<div class="container">
<h2>Meganett</h2>
 <h3>Notificación del sistema de Tickets</h3>
 <h3>Hola</h3>
 <br>El Ticket #{{ $ticket->id }} {{ $ticket_thread->ticket_thread_id ? 'ha sido respondido' : 'se le ha sido asignado' }}  </br>
 <br> De: {{ $ticket->prospect_name }} </br>
  <br> Prioridad: {{ $ticket->priority }} </br>
  <br> Con el asunto: {{ $ticket->topic }} </br>
    <br> -  </br>
    <br> {{ $ticket_thread->message }} </br>

</div>

@component('mail::button', ['url' => url('/tickets/ver/'. $ticket->id)])
Ver Ticket
@endcomponent

 Gracias,</br>
    {{ config('app.name') }}
@endcomponent
