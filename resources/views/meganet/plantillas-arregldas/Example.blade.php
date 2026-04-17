<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordatorio de Pago</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
        }
        .header img {
            max-width: 150px;
        }
        .content {
            margin-top: 20px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="LOGO_URL_AQUI" alt="Logo de la empresa">
        </div>
        <div class="content">
            <h2>Recordatorio de Pago</h2>
            <p>Estimado/a <strong>{NOMBRE_CLIENTE}</strong>,</p>
            <p>Le recordamos que el pago de su servicio <strong>{NOMBRE_DEL_SERVICIO}</strong> por un monto de <strong>{MONTO}</strong> vencerá el <strong>{FECHA_VENCIMIENTO}</strong>.</p>
            <p>Para realizar su pago de manera rápida y segura, puede hacerlo a través del siguiente enlace:</p>
            <p><a href="{ENLACE_PAGO}" class="button">Realizar Pago</a></p>
            <p>Si prefiere, también puede realizar una transferencia bancaria a la siguiente cuenta:</p>
            <ul>
                <li><strong>Banco:</strong> {BANCO}</li>
                <li><strong>Número de Cuenta:</strong> {NUMERO_CUENTA}</li>
                <li><strong>Titular:</strong> {TITULAR_CUENTA}</li>
            </ul>
            <p>Por favor, tenga en cuenta que si el pago no se realiza antes de la fecha indicada, su servicio podría ser suspendido temporalmente.</p>
            <p>Si tiene alguna pregunta o necesita asistencia, no dude en contactarnos. Estamos aquí para ayudarle.</p>
            <p>Gracias por su preferencia.</p>
        </div>
        <div class="footer">
            <p>Atentamente,<br><strong>{NOMBRE_EMPRESA}</strong></p>
            <p>Contacto: {EMAIL_EMPRESA} | Teléfono: {TELEFONO_EMPRESA}</p>
        </div>
    </div>
</body>
</html>