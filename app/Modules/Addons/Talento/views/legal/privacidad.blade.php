<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aviso de Privacidad — App Talento Equipo | Meganet Telecomunicaciones</title>
    <style>
        :root { --pcolor: #00897B; --pcolor-dark: #00695C; --ink: #1a1a1a; --muted: #555; --bg: #f5f5f5; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, 'Segoe UI', Roboto, system-ui, sans-serif;
            background: var(--bg);
            color: var(--ink);
            line-height: 1.65;
        }
        header {
            background: linear-gradient(135deg, var(--pcolor) 0%, var(--pcolor-dark) 100%);
            color: #fff;
            padding: 2.5rem 1.5rem;
            text-align: center;
        }
        header h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: .35rem; }
        header p { opacity: .9; font-size: .95rem; }
        main {
            max-width: 720px;
            margin: -1.5rem auto 3rem;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 12px 40px rgba(0,0,0,.08);
            padding: 2.25rem 2rem;
        }
        h2 {
            font-size: 1.05rem;
            color: var(--pcolor-dark);
            margin: 1.75rem 0 .6rem;
            padding-bottom: .4rem;
            border-bottom: 2px solid #eee;
        }
        h2:first-of-type { margin-top: 0; }
        p, li { font-size: .92rem; color: #333; margin-bottom: .6rem; }
        ul { padding-left: 1.3rem; margin-bottom: .6rem; }
        strong { color: var(--ink); }
        table { width: 100%; border-collapse: collapse; margin: .75rem 0 1rem; font-size: .88rem; }
        th, td { text-align: left; padding: .55rem .6rem; border-bottom: 1px solid #eee; vertical-align: top; }
        th { color: var(--pcolor-dark); font-weight: 600; width: 32%; }
        .contacto {
            background: #f0f7f6;
            border-radius: 10px;
            padding: 1.1rem 1.3rem;
            margin-top: .5rem;
            font-size: .9rem;
        }
        .contacto a { color: var(--pcolor-dark); font-weight: 600; text-decoration: none; }
        footer {
            text-align: center;
            padding: 1.5rem;
            font-size: .78rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <header>
        <h1>Aviso de Privacidad</h1>
        <p>App móvil "Talento Equipo" — Meganet Telecomunicaciones</p>
    </header>

    <main>
        <p style="color:#777;font-size:.82rem;margin-bottom:1.25rem;">Última actualización: {{ now()->isoFormat('D [de] MMMM [de] YYYY') }}</p>

        <h2>¿Quién es responsable de tus datos?</h2>
        <p>
            <strong>Meganet Telecomunicaciones</strong> (RFC {{ $rfc ?? 'MTE1709083F3' }}), con domicilio en
            {{ $municipio ?? 'Nextlalpan, Estado de México' }}, C.P. {{ $cp ?? '55796' }}, es responsable del
            tratamiento de los datos personales que se describen en este aviso, recabados a través de la
            aplicación móvil interna <strong>"Talento Equipo"</strong>.
        </p>

        <h2>¿Para qué app aplica este aviso?</h2>
        <p>
            Esta aplicación es de <strong>uso exclusivo para colaboradores de Meganet Telecomunicaciones</strong>
            en campo (técnicos, instaladores e integrantes del equipo de Talento). No está dirigida al público
            en general y no se distribuye fuera de la organización.
        </p>

        <h2>¿Qué datos recaba la app y para qué?</h2>
        <table>
            <tr><th>Dato</th><td>Para qué se usa</td></tr>
            <tr>
                <td><strong>Ubicación (GPS)</strong></td>
                <td>Para registrar la entrada y salida del colaborador a su jornada (check-in/check-out) y
                    confirmar que el trabajo se realizó en el sitio correspondiente. Solo se usa mientras el
                    colaborador tiene una jornada abierta.</td>
            </tr>
            <tr>
                <td><strong>Fotografías (cámara)</strong></td>
                <td>Para documentar evidencia de las órdenes de trabajo (instalaciones, reparaciones,
                    mediciones) y para la firma digital de clientes dentro de una orden de trabajo.</td>
            </tr>
            <tr>
                <td><strong>Identidad del colaborador</strong></td>
                <td>Nombre, usuario y credenciales de acceso, usados para identificar quién realiza cada
                    registro dentro del sistema interno de la empresa.</td>
            </tr>
            <tr>
                <td><strong>Notificaciones push</strong></td>
                <td>Para avisar al colaborador de nuevas órdenes de trabajo asignadas o documentos pendientes
                    de firma.</td>
            </tr>
        </table>

        <h2>¿Con quién se comparten estos datos?</h2>
        <p>
            Los datos recabados por la app <strong>no se venden ni se comparten con terceros ajenos a Meganet
            Telecomunicaciones</strong>. Se almacenan en los servidores propios de la empresa y solo los
            consulta personal autorizado para fines administrativos y de supervisión operativa internos.
        </p>

        <h2>¿Cuánto tiempo se conservan?</h2>
        <p>
            Los registros de ubicación y evidencias se conservan mientras dure la relación laboral del
            colaborador con la empresa y durante el plazo adicional que exija la normativa laboral y fiscal
            aplicable en México.
        </p>

        <h2>Derechos del colaborador</h2>
        <p>
            Como colaborador, puedes solicitar en cualquier momento acceso, corrección o eliminación de tus
            datos personales dentro de este sistema, escribiendo al correo de contacto abajo.
        </p>

        <h2>Contacto</h2>
        <div class="contacto">
            <p><strong>Meganet Telecomunicaciones</strong></p>
            <p>Correo: <a href="mailto:{{ $email ?? 'soporte@meganett.com.mx' }}">{{ $email ?? 'soporte@meganett.com.mx' }}</a></p>
            <p>Teléfono: {{ $telefono ?? '55 4210 6277' }}</p>
        </div>
    </main>

    <footer>
        &copy; {{ now()->year }} Meganet Telecomunicaciones. Documento de uso interno para la publicación de la
        app "Talento Equipo" en Google Play.
    </footer>
</body>
</html>
