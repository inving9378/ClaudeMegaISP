<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Comprobante de Pago</title>

    <style>
        .ticket-container {
            font-family: "Helvetica Neue", Arial, sans-serif;
        }

        .ticket-container .divider {
            border-top: 1px dashed #ccc;
            margin: 10px 0;
        }

        .ticket-container .ticket {
            width: 80mm;
            max-width: 80mm;
            padding: 15px;
            background-color: white;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            border: 1px solid #ddd;
            margin: auto;
        }

        .ticket-container .header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #ccc;
        }

        .ticket-container .company-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .ticket-container .contact-info {
            font-size: 10px;
            line-height: 1.4;
            color: #555;
        }

        .ticket-container .address {
            font-size: 10px;
            margin: 8px 0;
            color: #555;
            text-align: center;
        }

        .ticket-container .data {
            margin: 15px 0;
            font-size: 12px;
        }

        .ticket-container .data-row {
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
        }

        .ticket-container .data-label {
            font-weight: bold;
            display: inline-block;
            min-width: 100px;
        }

        .ticket-container .data-value {
            border-bottom: 1px dotted #000;
            padding-bottom: 2px;
            text-align: right
        }

        .ticket-container .payment-details {
            margin: 15px 0;
            font-size: 12px;
            width: 100%;
        }

        .ticket-container .footer {
            color: #74788d;
            position: inherit !important;
            border: 0 !important;
            text-align: center;
            padding: 0px !important;
        }

        .ticket-container .uppercase {
            text-transform: uppercase;
        }
    </style>
</head>

<body>
    <div class="ticket-container">
        <div class="ticket">
            <div class="header">
                <div class="company-name">MEGANET TELECOMUNICACIONES S.A. DE C.V.</div>
                <div class="contact-info">
                    Atención a clientes: 55-42-10-62-77<br>
                    WhatsApp SOLO PAGOS: 55-25-71-67-18<br>
                    OXXO Depósito a Tarjeta: 5579 0890 0023 7860<br>
                    Banco: Santander
                </div>
            </div>

            <div class="address">
                Av. Hda La Purisima Mz 3 Lt 54 Casa A<br>
                Ex Hda Santa Ines Nextlalpan Edo Mex
            </div>

            <div class="divider"></div>

            <div class="data">
                <div class="data-row">
                    <span class="data-label">ID del Vendedor:</span>
                    <span class="data-value">{{ $seller->id }}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">ID de Pago:</span>
                    <span class="data-value">{{ $discount->id ?? '-' }}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Fecha:</span>
                    <span class="data-value">{{ $discount_date }}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Nombre:</span>
                    <span class="data-value">{{ $seller->user->name }} {{ $seller->user->father_last_name }}
                        {{ $seller->user->mother_last_name }}</span>
                </div>
            </div>

            <div class="divider"></div>

            <div class="data">
                <div class="data-row">
                    <span class="data-label">Tipo de pago:</span>
                    <span class="data-value">Descuento</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Cantidad pagada:</span>
                    <span class="data-value">${{ $total }}</span>
                </div>
            </div>

            <div class="divider"></div>

            <div class="footer">
                <div style="margin: 8px 0;">¡¡Que tenga un EXCELENTE DÍA!!</div>
                <div>Este es un comprobante de pago</div>
            </div>
        </div>
    </div>
</body>

</html>
