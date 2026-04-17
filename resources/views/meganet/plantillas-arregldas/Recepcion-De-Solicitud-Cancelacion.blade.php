<!DOCTYPE html>
<html>

<head>
    <style type="text/css">
        @page {
            margin: 0px 0px 0px 0px;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
        }

        header {
            position: fixed;
            left: 0;
            right: 0;
            background-color: #3668d3;
            height: 120px;
        }

        .content {
            padding: 10px;
            margin: 140px 20px 10px;
            font-size: 13px;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .invoice-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .invoice-items th {
            background: #f8f8f8;
            font-size: 12px;
        }

        .small-text {
            font-size: 10px;
            text-align: justify;
        }

        .signature {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }

        .signature div {
            margin-top: 20px;
            text-align: center;
        }

        .uppercase {
            text-transform: uppercase;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .table th,
        .table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }

        .table th {
            background-color: #f2f2f2;
        }
    </style>
</head>

<body>
    <header>
        <table border="0" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td style="border: 0">
                    <img src="${data.url_logo}" alt="Logo Meganet"
                        style="max-height: 100px; margin-left:30px; margin-top: 10px">
                </td>
                <td style="border: 0" class="text-right">
                    <div class="lhs">

                    </div>
                </td>
            </tr>
        </table>
        <div
            style=" width: 50%; margin-left: 40%;margin-top: -15px; border: 1px solid black; position: absolute; background-color: white !important; z-index: 999">
            <center>
                <h2 class="">Meganet Telecomunicaciones S.A. de C.V.</h2>
            </center>
        </div>
    </header>
    <div class="content">
        <div class="top-content">
            <div style="width: 100%;">
                <h3>Recepción de Solicitud de Cancelación de Servicio</h3>
            </div>

            <div style="margin-top: -15px">
                <div style=" width: 50%;margin-left: 60%">
                    <table border="0" cellspacing="0" cellpadding="0" width="100%">
                        <tr>
                            <td style="border: 0; margin-top: 20px">
                                <div class="lhs">
                                    <table class="">
                                        <tr>
                                            <td>Tipo de Facturación:</td>
                                            <td>${data.type_of_billing_id}</td>
                                        </tr>
                                        <tr>
                                            <td>Fecha de Corte:</td>
                                            <td>${data.fecha_corte}</td>
                                        </tr>
                                        <tr>
                                            <td>Contrato:</td>
                                            <td>${data.id}</td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

            </div>
        </div>
        <table border="0" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td style="border: 0">
                    <div class="section-title">Datos de la Empresa</div>
                    <table class="">
                        <tr>
                            <td><strong>${data.company_name}</strong></td>
                        </tr>
                        <tr>
                            <td>${data.email} - ${data.atention_client_phone}</td>
                        </tr>
                        <tr>
                            <td>${data.colony_id}, ${data.municipality_id}, ${data.state_id}</td>
                        </tr>
                        <tr>
                            <td>ID: ${data.id}</td>
                        </tr>
                    </table>
                </td>
                <td style="border: 0">
                    <div class="section-title">Datos del Cliente</div>
                    <table class="info-table">
                        <tr>
                            <td><strong>${data.full_name}</strong></td>
                        </tr>
                        <tr>
                            <td>${data.street} ${data.external_number} ${data.internal_number}, ${data.colony}</td>
                        </tr>
                        <tr>
                            <td>${data.municipality} - CP: ${data.zip}</td>
                        </tr>
                        <tr>
                            <td>Teléfono: ${data.phone} | Email: ${data.email}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <p class="">Mediante este documento se ratifica la solicitud de cancelación de los servicios
            contratados en el contrato ${data.id} a nombre de
            ${data.full_name}.</p>

            ${data.table_client_pending_payments}

        <div style="margin-top: 20px">
            <p class="">Para finalizar el/los servicios contratados, será indispensable mantener su cuenta SIN
                ADEUDO, verifique haber realizado los
                siguientes pagos:
            </p>
        </div>
        <div>
            <p><b>Nota:</b> Este es un inicio de proceso, al cumplirse los 20 días el suscriptor deberá presentarse a
                realizar la culminación del proceso
                definitivo, este no se habrá finiquitado hasta realizar la entrega del equipo (modem y eliminador,
                etc.).</p>
            <p>
                En caso de no realizarse la entrega de los equipos, la facturación continuara de forma
                ininterrumpida hasta la entrega del equipo.
            </p>
            <p>Meganet NO REALIZARÁ RECOLECCION DE EQUIPO, el suscriptor deberá realizar la entrega del equipo en
                oficina para recibir
                el documento con el cual se acredita la entrega.</p>
            <p>
                AL FINIQUITAR la Cancelación, deberá asegurarse recibir el documento que acredita que el servicio
                quedo en cero adeudos y la
                recepción de los equipos Verifique que sus datos sean correctos y su número de contacto este
                actualizado.
            </p>
        </div>

        <div class="signature">
            <div>
                <p>_________________________</p>
                <p>Firma del Cliente</p>
            </div>
        </div>
    </div>

</body>

</html>
