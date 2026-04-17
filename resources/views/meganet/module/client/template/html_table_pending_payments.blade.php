<table class="table">
    <tr>
        <th>Pagos Pendientes</th>
        <th>Firmado en Contrato</th>
        <th>Adeudo</th>
    </tr>
    <tr>
        <td>1. Activación del servicio. (No va ligado a ninguna de nuestras promociones). Si
            cumplió con el periodo del contrato, este costo será condonado.</td>
        <td>{{$data['activationCost']}}</td>
        <td>{{$data['isPaymentActivationCost'] ? 0 : $data['activationCost']}}</td>
    </tr>
    <tr>
        <td>2. Instalación del servicio (pudo haber obtenido instalación gratis, en ese caso no
            aplica)
            </td>
        <td>{{$data['isPaymentActivationCost'] ? 'No Aplica' : 'Aplica'}} </td>
        <td>{{$data['isPaymentActivationCost'] ? 'Aplica' : 'No Aplica'}}</td>
    </tr>
    <tr>
        <td>3. Mensualidades según contrato.</td>
        <td>{{$data['contractMonths']}}</td>
        <td> {{$data['mesesRestantes']}} meses-({{$data['adeudoMeses']}})</td>
    </tr>
    <tr>
        <td>4. Penalizaciones por pago extemporáneo (de acuerdo a la modalidad de su
            contrato)
            </td>
        <td>-</td>
        <td>-</td>
    </tr>
</table>
