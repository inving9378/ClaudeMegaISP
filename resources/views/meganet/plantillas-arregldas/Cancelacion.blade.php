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

        .table-container {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .table-container th,
        .table-container td {
            border: 1px solid #ddd;
            padding: 5px;
            text-align: left;
        }

        .table-container th {
            background: #f0f0f0;
        }

        .totals {
            margin-top: 10px;
            width: 100%;
            text-align: right;
        }

        .totals td {
            padding: 5px;
        }

        .signature {
            margin-top: 85px;
            text-align: center;
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
                <table border="0" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td>
                            <p style="font-size: 30px"><strong>CANCELACION <br>
                                    DE SERVICIO </strong> </p>
                        </td>
                        <td style="border: 0; margin-top: 20px">
                            <div class="lhs">
                                <table class="">
                                    <tr>
                                        <td>Numero:</td>
                                        <td>${data.type_of_billing_id}</td>
                                    </tr>
                                    <tr>
                                        <td>Fecha:</td>
                                        <td>${data.now}</td>
                                    </tr>
                                    <tr>
                                        <td>Contrato ID:</td>
                                        <td>${data.id}</td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                </table>

            </div>
        </div>
        <hr>
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
        <hr>
        <p>
            Mediante este documento se ratifica la cancelación del/los servicios proporcionados en el contrato:
            ${data.id}
        </p>
        <p>
            Se ratifica la recepción del equipo proporcionado al cliente para recibir el servicio con número de serie:
            485476522132548
        </p>

        <div>
            ${data.table_client_services}
        </div>

        <div class="signature">
            <p>_________________________</p>
            <p>Firma del Cliente</p>
        </div>


    </div>
</body>

</html>
