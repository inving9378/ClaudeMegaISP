<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Descargar app Meganet</title>
<style>
  *{margin:0;box-sizing:border-box}
  :root{
    --bg:#f5f7f8; --surface:#fff; --ink:#1a2230; --muted:#5b6672;
    --accent:#00897B; --accent-dark:#00695C; --hair:#e5e8ec; --amber:#b45309; --amber-bg:#fef3e2;
  }
  @media (prefers-color-scheme: dark){
    :root{--bg:#0f141a; --surface:#161d26; --ink:#e7ecf2; --muted:#95a1b0; --accent:#26A69A; --accent-dark:#00897B; --hair:#263039; --amber:#e0a55a; --amber-bg:#34291a;}
  }
  body{background:var(--bg);color:var(--ink);font-family:-apple-system,"Segoe UI",Roboto,system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;line-height:1.5}
  .card{background:var(--surface);border-radius:16px;padding:32px 28px;max-width:360px;width:100%;text-align:center;box-shadow:0 2px 24px rgba(0,0,0,.08)}
  .icon{width:64px;height:64px;border-radius:16px;background:var(--accent);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:28px;color:#fff;font-weight:700}
  h1{font-size:1.3rem;margin-bottom:6px}
  p{color:var(--muted);font-size:.92rem;margin-bottom:22px}
  .btn{display:block;background:var(--accent);color:#fff;text-decoration:none;font-weight:700;font-size:1rem;padding:14px;border-radius:10px;margin-bottom:10px}
  .btn:active{background:var(--accent-dark)}
  .hint{font-size:.78rem;color:var(--muted);margin-top:4px}
  .notice{background:var(--amber-bg);border:1px solid var(--hair);border-radius:10px;padding:14px;font-size:.86rem;color:var(--ink);text-align:left;margin-top:8px}
  .notice b{color:var(--amber)}
</style>
</head>
<body>
  <div class="card">
    <div class="icon">M</div>

    @if($plataforma === 'android')
      <h1>Descargando para Android…</h1>
      <p>Si no empieza solo, usa el botón de abajo.</p>
      <a class="btn" href="{{ $apkUrl }}">Descargar ahora</a>
      <p class="hint">Requiere Android 6.0 o superior · v{{ $versionName }}</p>

    @elseif($plataforma === 'ios')
      <h1>Próximamente para iPhone</h1>
      <div class="notice">
        <b>Todavía no disponible para iOS.</b><br>
        Por ahora la app de Meganet solo existe para Android. Si tu equipo de trabajo es
        iPhone, contacta a tu administrador mientras se habilita la versión para iOS.
      </div>

    @else
      <h1>Descarga la app Meganet</h1>
      <p>Abre este enlace desde el teléfono que vas a usar, o escanea el código QR con tu celular.</p>
      <a class="btn" href="{{ $apkUrl }}">Descargar para Android</a>
      <p class="hint">Requiere Android 6.0 o superior · v{{ $versionName }}</p>
      <div class="notice" style="margin-top:14px"><b>iPhone:</b> todavía no disponible.</div>
    @endif
  </div>
</body>
</html>
