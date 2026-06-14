<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta — Portal Meganet</title>
    <style>
        :root { --pcolor: #0057a8; --pcolor-dark: #003d78; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, #0057a8 0%, #003d78 60%, #001f4d 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem;
        }
        .auth-card { background: #fff; border-radius: 16px; padding: 2.5rem; width: 100%; max-width: 440px; box-shadow: 0 24px 64px rgba(0,0,0,.25); }
        .auth-logo { text-align: center; margin-bottom: 1.75rem; }
        .auth-logo h1 { font-size: 1.4rem; font-weight: 700; color: var(--pcolor); margin-top: .5rem; }
        .auth-logo p { font-size: .85rem; color: #6c757d; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: .85rem; font-weight: 500; color: #333; margin-bottom: .4rem; }
        input { width: 100%; padding: .65rem .9rem; border: 1.5px solid #dee2e6; border-radius: 8px; font-size: .9rem; transition: border .15s; }
        input:focus { outline: none; border-color: var(--pcolor); box-shadow: 0 0 0 3px rgba(0,87,168,.12); }
        .help-text { font-size: .78rem; color: #6c757d; margin-top: .3rem; }
        .btn-submit { width: 100%; padding: .75rem; background: var(--pcolor); color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; margin-top: .5rem; }
        .btn-submit:hover { background: var(--pcolor-dark); }
        .alert-danger { background: #f8d7da; color: #721c24; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .85rem; border-left: 4px solid #dc3545; }
        .info-box { background: #e7f3ff; border-left: 4px solid var(--pcolor); padding: .75rem 1rem; border-radius: 8px; font-size: .82rem; color: #004085; margin-bottom: 1.25rem; }
        hr { border: none; border-top: 1px solid #dee2e6; margin: 1.25rem 0; }
        .auth-links { text-align: center; font-size: .85rem; color: #6c757d; }
        .auth-links a { color: var(--pcolor); }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="auth-logo">
        <div style="font-size:2.5rem">🔐</div>
        <h1>Crear Cuenta</h1>
        <p>Portal Cliente Meganet</p>
    </div>

    <div class="info-box">
        Necesitas tu <strong>número de cliente</strong> y el <strong>teléfono registrado</strong> en tu contrato para verificar tu identidad.
    </div>

    @if($errors->any())
        <div class="alert-danger">
            @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('portal.registro.post') }}">
        @csrf
        <div class="form-group">
            <label>Número de cliente</label>
            <input type="number" name="numero_cliente" value="{{ old('numero_cliente') }}"
                placeholder="Ej: 1234" min="1" required autofocus>
            <div class="help-text">Encuéntralo en tu última factura o comprobante.</div>
        </div>
        <div class="form-group">
            <label>Teléfono registrado</label>
            <input type="text" name="telefono" value="{{ old('telefono') }}"
                placeholder="Ej: 5512345678">
            <div class="help-text">El teléfono que registraste en tu contrato.</div>
        </div>
        <div class="form-group">
            <label>Nueva contraseña</label>
            <input type="password" name="contrasena" placeholder="Mínimo 8 caracteres" required>
        </div>
        <div class="form-group">
            <label>Confirmar contraseña</label>
            <input type="password" name="contrasena_confirmation" placeholder="Repite tu contraseña" required>
        </div>
        <button type="submit" class="btn-submit">Crear cuenta</button>
    </form>

    <hr>
    <div class="auth-links">
        <a href="{{ route('portal.login') }}">← Volver al inicio de sesión</a>
    </div>
</div>
</body>
</html>
