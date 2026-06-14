<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — Portal Meganet</title>
    <style>
        :root { --pcolor: #0057a8; --pcolor-dark: #003d78; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, #0057a8 0%, #003d78 60%, #001f4d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .auth-card {
            background: #fff;
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 24px 64px rgba(0,0,0,.25);
        }
        .auth-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .auth-logo h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--pcolor);
            margin-top: .5rem;
        }
        .auth-logo p { font-size: .85rem; color: #6c757d; margin-top: .25rem; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: .85rem; font-weight: 500; color: #333; margin-bottom: .4rem; }
        input {
            width: 100%; padding: .65rem .9rem;
            border: 1.5px solid #dee2e6;
            border-radius: 8px;
            font-size: .9rem;
            transition: border .15s;
        }
        input:focus { outline: none; border-color: var(--pcolor); box-shadow: 0 0 0 3px rgba(0,87,168,.12); }
        .btn-submit {
            width: 100%;
            padding: .75rem;
            background: var(--pcolor);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: .5rem;
        }
        .btn-submit:hover { background: var(--pcolor-dark); }
        .form-error { color: #dc3545; font-size: .8rem; margin-top: .3rem; }
        .alert-danger { background: #f8d7da; color: #721c24; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .85rem; border-left: 4px solid #dc3545; }
        .alert-success { background: #d4edda; color: #155724; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .85rem; border-left: 4px solid #28a745; }
        .auth-links { text-align: center; margin-top: 1.5rem; font-size: .85rem; color: #6c757d; }
        .auth-links a { color: var(--pcolor); }
        hr { border: none; border-top: 1px solid #dee2e6; margin: 1.25rem 0; }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="auth-logo">
        <div style="font-size:3rem">🌐</div>
        <h1>Portal Cliente</h1>
        <p>Meganet Telecomunicaciones</p>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert-danger">
            @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('portal.login.post') }}">
        @csrf
        <div class="form-group">
            <label>Número de cliente o Email</label>
            <input type="text" name="identificador" value="{{ old('identificador') }}"
                placeholder="Ej: 1234 o correo@ejemplo.com" required autofocus>
        </div>
        <div class="form-group">
            <label>Contraseña</label>
            <input type="password" name="contrasena" placeholder="Tu contraseña del portal" required>
        </div>
        <button type="submit" class="btn-submit">Iniciar Sesión</button>
    </form>

    <hr>
    <div class="auth-links">
        <a href="{{ route('portal.registro') }}">¿Primera vez? Crear cuenta</a>
        &nbsp;·&nbsp;
        <a href="{{ route('portal.recuperar') }}">Olvidé mi contraseña</a>
    </div>
</div>
</body>
</html>
