<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Comprobante de Pago</title>
    <style>
        @page {
            size: auto;
            margin: 0;
        }

        body {
            font-family: "Helvetica Neue", Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            min-height: 100vh;
            background-color: #f5f5f5;
        }

        .ticket-container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
        }

        .ticket {
            width: 80mm;
            max-width: 80mm;
            padding: 15px;
            background-color: white;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
            border: 1px solid #ddd;
            margin: auto; /* Centrado adicional para impresión */
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #ccc;
        }

        .company-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .contact-info {
            font-size: 10px;
            line-height: 1.4;
            color: #555;
        }

        .address {
            font-size: 10px;
            margin: 8px 0;
            color: #555;
            text-align: center;
        }

        .client-data {
            margin: 15px 0;
            font-size: 12px;
        }

        .data-row {
            margin-bottom: 5px;
        }

        .data-label {
            font-weight: bold;
            display: inline-block;
            min-width: 100px;
        }

        .data-value {
            border-bottom: 1px dotted #000;
            padding-bottom: 2px;
        }

        .payment-details {
            margin: 15px 0;
            font-size: 12px;
            width: 100%;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 3px;
            border-bottom: 1px solid #eee;
        }

        .payment-label {
            font-weight: bold;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            font-weight: bold;
            color: #333;
        }

        .uppercase {
            text-transform: uppercase;
        }

        .divider {
            border-top: 1px dashed #ccc;
            margin: 10px 0;
        }
    </style>
</head>

<body>
    <div class="ticket-container">
        <div class="ticket">
            <!-- Encabezado -->
            <div class="header">
                <div class="company-name">MEGANET TELECOMUNICACIONES S.A. DE C.V.</div>
                <div class="contact-info">
                    Atención a clientes: 55-42-10-62-77<br>
                    WhatsApp SOLO PAGOS: 55-25-71-67-18<br>
                    OXXO Depósito a Tarjeta: 5579 0890 0023 7860<br>
                    Banco: Santander
                </div>
            </div>

            <!-- Dirección -->
            <div class="address">
                Av. Hda La Purisima Mz 3 Lt 54 Casa A<br>
                Ex Hda Santa Ines Nextlalpan Edo Mex
            </div>

            <div class="divider"></div>

            <!-- Datos del cliente -->
            <div class="client-data">
                <div class="data-row">
                    <span class="data-label">ID del Cliente:</span>
                    <span class="data-value">${data.id}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">ID de Pago:</span>
                    <span class="data-value">${data_dinamic.payment_id}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Ticket:</span>
                    <span class="data-value">${data_dinamic.payment_receipt}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Fecha:</span>
                    <span class="data-value">${data_dinamic.payment_date}</span>
                </div>
                <div class="data-row uppercase" style="margin-top: 10px;">
                    <span class="data-label">Nombre:</span>
                    <span class="data-value">${data.full_name}</span>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Detalles del pago -->
            <div class="payment-details">
                <div class="payment-row">
                    <span class="payment-label">ABONO:</span>
                    <span>${data_dinamic.payment_amount}</span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">SERVICIO:</span>
                    <span>${data.client_bundle_service_name}, ${data.client_internet_service_name}, ${data.client_voz_service_name}, ${data.client_custom_service_name}</span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">VÁLIDO:</span>
                    <span>${data_dinamic.payment_period}</span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">FECHA DE CORTE:</span>
                    <span>${data.fecha_pago}</span>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Pie de página -->
            <div class="footer">
                <div>Gracias por SU VISITA</div>
                <div style="margin: 8px 0;">¡¡Que tenga un EXCELENTE DÍA!!</div>
                <div>Este es un comprobante de pago</div>
            </div>
        </div>
    </div>
</body>

</html>
