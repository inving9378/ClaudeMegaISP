<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MegaFamilia — Descargar app</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <script src="{{ asset('js/qrcode.min.js') }}"></script>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #1f2937;
        }
        .wrap { max-width: 720px; margin: 0 auto; padding: 32px 20px; }
        header {
            background: #fff; border-radius: 16px; padding: 18px 22px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12); margin-bottom: 24px;
            display: flex; align-items: center; gap: 12px;
        }
        header .brand {
            width: 44px; height: 44px; border-radius: 10px;
            background: linear-gradient(135deg, #0d6efd, #6610f2);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: 20px;
        }
        header .brand-text { font-weight: 700; font-size: 16px; color: #111827; }
        header .brand-sub  { font-size: 12px; color: #6b7280; }

        .card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            padding: 32px; margin-bottom: 24px;
            text-align: center;
        }
        h1 { margin: 0 0 8px; font-size: 28px; }
        .lead { color: #6b7280; margin-bottom: 24px; line-height: 1.5; }

        .download-btn {
            display: inline-flex; align-items: center; gap: 10px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff; text-decoration: none;
            padding: 18px 32px; border-radius: 12px;
            font-size: 18px; font-weight: 600;
            box-shadow: 0 8px 24px rgba(16,185,129,0.35);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(16,185,129,0.45);
        }
        .download-btn .icon { font-size: 24px; }

        .meta {
            display: flex; gap: 24px; justify-content: center; flex-wrap: wrap;
            margin-top: 20px; color: #6b7280; font-size: 14px;
        }
        .meta strong { color: #111827; }
        .meta-item { display: flex; flex-direction: column; align-items: center; }

        .qr-section {
            display: flex; gap: 24px; align-items: center;
            margin-top: 24px; padding-top: 24px; border-top: 1px solid #e5e7eb;
            justify-content: center; flex-wrap: wrap;
        }
        .qr-section img { border-radius: 8px; border: 1px solid #e5e7eb; }
        .qr-section .qr-text { text-align: left; }
        .qr-section .qr-text small { color: #6b7280; }

        .install-card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            padding: 28px;
        }
        .install-card h2 { font-size: 18px; margin: 0 0 18px; color: #111827; }
        .step {
            display: flex; gap: 14px; align-items: flex-start;
            padding: 12px 0; border-bottom: 1px solid #f3f4f6;
        }
        .step:last-child { border-bottom: 0; }
        .step-num {
            flex-shrink: 0; width: 32px; height: 32px;
            background: #0d6efd; color: #fff; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700;
        }
        .step-content { font-size: 14px; line-height: 1.5; }
        .step-content strong { color: #111827; }

        footer {
            text-align: center; margin-top: 24px;
            color: rgba(255,255,255,0.85); font-size: 13px;
        }
        footer a { color: rgba(255,255,255,1); text-decoration: none; }

        .no-version {
            color: #ef4444; padding: 24px;
            background: #fef2f2; border-radius: 8px;
        }

        @media (max-width: 540px) {
            .card { padding: 24px 18px; }
            h1 { font-size: 22px; }
            .download-btn { padding: 14px 22px; font-size: 16px; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <div class="brand">M</div>
            <div>
                <div class="brand-text">MegaNet Telecomunicaciones</div>
                <div class="brand-sub">App MegaFamilia · Control parental</div>
            </div>
        </header>

        <div class="card">
            <h1>📱 MegaFamilia</h1>
            <p class="lead">
                App de control familiar para clientes MegaNet.<br>
                Supervisa la actividad de tus hijos, define tiempos de uso, recibe alertas y mantén tu familia segura.
            </p>

            @if($version)
                @php
                    $sizeMb = number_format(($version->file_size ?: 0) / 1024 / 1024, 1);
                @endphp
                <a class="download-btn" href="{{ $downloadUrl }}">
                    <span class="icon">📱</span>
                    Descargar para Android
                </a>

                <div class="meta">
                    <div class="meta-item">
                        <small>Versión</small>
                        <strong>v{{ $version->version_name }}</strong>
                    </div>
                    <div class="meta-item">
                        <small>Tamaño</small>
                        <strong>{{ $sizeMb }} MB</strong>
                    </div>
                    <div class="meta-item">
                        <small>Publicada</small>
                        <strong>{{ \Carbon\Carbon::parse($version->released_at)->translatedFormat('d M Y') }}</strong>
                    </div>
                </div>

                <div class="qr-section">
                    <div id="qrcode" style="display:flex; justify-content:center;"></div>
                    <div class="qr-text">
                        <strong>Escanea el QR</strong>
                        <div><small>desde tu teléfono Android<br>para descargar directamente</small></div>
                    </div>
                </div>
                <script>
                    new QRCode(document.getElementById("qrcode"), {
                        text: @json($downloadUrl),
                        width: 200,
                        height: 200,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });
                </script>
            @else
                <div class="no-version">
                    No hay una versión disponible en este momento. Por favor contacta a soporte.
                </div>
            @endif
        </div>

        @if($version)
        <div class="install-card">
            <h2>¿Cómo instalar?</h2>

            <div class="step">
                <div class="step-num">1</div>
                <div class="step-content">
                    <strong>Descarga el archivo APK</strong>
                    <div>Pulsa el botón verde "Descargar para Android" o escanea el QR.</div>
                </div>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <div class="step-content">
                    <strong>Habilita la instalación desde fuentes desconocidas</strong>
                    <div>En tu Android ve a <em>Ajustes → Seguridad</em> (o <em>Aplicaciones → Acceso especial</em>) y permite la instalación de fuentes desconocidas para tu navegador o gestor de archivos.</div>
                </div>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <div class="step-content">
                    <strong>Abre el archivo descargado</strong>
                    <div>Desde la barra de notificaciones o en la carpeta <em>Descargas</em>.</div>
                </div>
            </div>
            <div class="step">
                <div class="step-num">4</div>
                <div class="step-content">
                    <strong>Sigue las instrucciones</strong>
                    <div>Acepta los permisos solicitados durante la instalación y abre la app cuando termine.</div>
                </div>
            </div>
        </div>
        @endif

        <footer>
            © {{ date('Y') }} MegaNet Telecomunicaciones. Todos los derechos reservados.
        </footer>
    </div>
</body>
</html>
