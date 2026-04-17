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
            font-size: 14px;
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
            <img src="${data.url_logo}" alt="Logo de la empresa">
        </div>
        <div class="content">
            <h2>Recordatorio de Pago</h2>
            <p>Hola! <strong>${data.full_name}</strong>,</p>
            <p>Hemos detectado que tu servicio esta proximo a vencer el dia <strong>${data.fecha_corte}</strong>, te invitamos a realizar tu pago para seguir
                disfrutando de la conexion y el contenido de internet que mas te agrada</p>
            <p>
                <strong>${data.full_name}</strong>, recuerda que puedes realizar tu pago de
                diferentes formas:
            </p>

            <ol>
                <li>Directo en sucursal Av. Hda La Purisima Mz 3 Lt 54 Casa A Ex Hda Santa Ines Nextlalpan Edo. Mex la
                    aplicacion del pago y la reactivacion del servicio seran inmediatos </li>
                <li>
                    Pago en OXXO
                    Acercate al oxxo mas cercano y realiza deposito a la tarjeta 5579 0890
                    0023 7860 ESCREIBE CON PLUMA EL NOMBRE DEL SUSCRIPTOR BAJO EL MONTO DE
                    PAGO, toma foto o escanea el tiket y envialo por whatsapp al
                    55-25-71-67-18, te llegara un mensaje inmediato, y deberas esperar la
                    confirmacion en el lapso de 2 Hrs a partir que enviaste tu tiket que
                    ha quedado aplicado tu pago
                </li>
                <li>
                    Transferencia bancaria
                    Registra la cuenta clave interbancaria en tu portal 014180655063756953
                    y cuando este autorizada podras realizar tu transferencia, con el
                    numero de autorizacion, mandanos un Whatsap al 55-25-71-67-18, te
                    llegara un mensaje inmediato, y deberas esperar la confirmacion en el
                    lapso de 2 Hrs a partir que enviaste tu tiket que ha quedado aplicado
                    tu pago
                </li>

                <li>
                    Pay Pal
                    Ingresa al portal de clientes http://usuarios.meganett.com.mx/ con tu
                    usuario (email) y contraseña ( Si no la tienes, da click en Reiniciar
                    Contraseña), coloca tu E-mail da click en Enviar Solicitud, revisa tu
                    email y te habra llegado tu contraseña nueva regresa al portal
                    anterior e ingresa tus datos y desde ahi paga tu factura con tarjeta
                    de credito o debito
                </li>
            </ol>

            <p>
                Recuerda que en Sucursal tambien puedes pagar con tarjeta
                Si ud requiere facturacion del servicio , es importante nos comparta
                sus datos fiscales y tipo de factura que requerira al correo
                facturacion@meganett.com.mx
            </p>
            <p>
                Nuestro Horario de Atencion es de Lunes a Sabado de 9:00 am a 9:00 pm
            </p>

            <p>
                Como vez Meganet tiene todo preparado para que No Te Quedes
                Incomunicado y sigas disfrutando de la conexion que mas te agrada
            </p>

            <p>
                Login de Cliente - 003448
            </p>

            <p>
                Este correo se envia de forma automatica, no es necesario responder al
                mismo
            </p>


        </div>
        <div class="footer">
            <p>${data.company_name} te desea un EXELENTE DIA y Recuerda que:
                ESTE MENSAJE ES AUTOMATICO NO ES NECESARIO RESPONDER
            </p>
            <p>"La vida te pone obstaculos pero los limites los pones Tu"</p>
        </div>
    </div>
</body>

</html>
