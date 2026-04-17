@component('mail::message')
    <div class="container">
        <h2>Meganett</h2>
        <h3>Notificación del Recordatorio</h3>
        <h3>Estimado(a)
            {{ $client_reminder->client_main_information->name . ' ' . $client_reminder->client_main_information->father_last_name . ' ' . $client_reminder->client_main_information->mother_last_name }}
        </h3>
        <br>Le recordamos que el día {{ $client_reminder->billing_configuration->billing_date }} debe pagar un monto de
        ${{ $client_reminder->reminder_configuration->reminder_payment_amount }}
        <br> para continuar con los servicios de MEGANET.
        <br>
        <br> {{ $client_reminder->reminder_configuration->reminder_payment_comment }}
        <br>
        <br> </br>

    </div>

    @component('mail::button', ['url' => url('/')])
        Página de pagos
    @endcomponent

    Gracias,</br>
    {{ config('app.name') }}
@endcomponent
