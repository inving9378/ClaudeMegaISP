<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
        }

        .content {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }

        .details {
            background-color: #eee;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class='container'>
        <div class='header'>
            <h2>Error al enviar factura proforma</h2>
        </div>
        <div class='content'>
            <p>Se ha producido un error al intentar enviar una factura proforma.</p>
            <div class='details'>
                <p><strong>Fecha del error:</strong> {{ $fecha }}</p>
                <p><strong>ID de factura:</strong>{{ $id }}</p>
                <p><strong>Usuario:</strong> {{ $user }}</p>
                <p><strong>Mensaje de error:</strong>{{ $message }} </p>
                <p><strong>Archivo:</strong> {{ $file }}</p>
                <p><strong>Línea:</strong> {{ $line }}</p>
            </div>
            <p>Por favor, revise el error y tome las acciones necesarias.</p>
        </div>
    </div>
</body>

</html>
