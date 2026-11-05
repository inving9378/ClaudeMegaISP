<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex">
<title>Firmar documento</title>
<style>
body{font-family:system-ui,sans-serif;background:#f4f6f8;margin:0;color:#222}
main{max-width:720px;margin:0 auto;padding:16px}
.card{background:#fff;border-radius:8px;padding:16px;margin-bottom:12px;box-shadow:0 1px 3px #0002}
iframe{width:100%;height:60vh;border:1px solid #ccc;border-radius:4px}
input[type=text]{width:100%;padding:10px;box-sizing:border-box;margin-top:4px}
canvas{border:1px dashed #888;border-radius:4px;width:100%;height:180px;touch-action:none;background:#fff}
button{padding:10px 16px;border:0;border-radius:4px;background:#1565c0;color:#fff;font-size:15px;cursor:pointer}
button.sec{background:#777}
#msg{margin-top:8px;font-weight:600}
</style>
</head>
<body>
<main>
<div class="card"><h2>Firma de documento{{ $folio ? ' · '.$folio : '' }}</h2>
<p>Hola {{ $participante->nombre }}, revisa el documento completo y fírmalo al final.</p></div>

<div class="card">
@if($tieneDocumento)
<iframe src="{{ route('firma.publica.documento', $token) }}" title="Documento"></iframe>
@else
<p>El documento aún no está disponible para vista previa. Contacta a quien te lo envió.</p>
@endif
</div>

<div class="card" id="form">
<label>Tu nombre completo (identidad)<input type="text" id="identidad" value="{{ $participante->nombre }}" maxlength="255"></label>
<p><button type="button" class="sec" id="btnOtp">Enviar código a {{ $participante->correo }}</button></p>
<label>Código de 6 dígitos<input type="text" id="otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code"></label>
<p>Tu firma:</p>
<canvas id="pad"></canvas>
<p><button type="button" class="sec" id="btnLimpiar">Limpiar</button></p>
<label><input type="checkbox" id="consent"> Acepto firmar este documento por medios electrónicos y que mi firma tiene validez legal.</label>
<p><button type="button" id="btnFirmar">Firmar documento</button></p>
<div id="msg" role="status"></div>
</div>
</main>
<script>
(function(){
  var tok = @json($token), csrf = document.querySelector('meta[name=csrf-token]').content;
  var c = document.getElementById('pad'), ctx = c.getContext('2d'), dibujo = false, hayTrazo = false;
  function ajusta(){ var r = c.getBoundingClientRect(); c.width = r.width; c.height = r.height; ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.strokeStyle = '#111'; hayTrazo = false; }
  function pos(e){ var r = c.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; }
  c.addEventListener('pointerdown', function(e){ dibujo = true; c.setPointerCapture(e.pointerId); var p = pos(e); ctx.beginPath(); ctx.moveTo(p[0], p[1]); });
  c.addEventListener('pointermove', function(e){ if(!dibujo) return; var p = pos(e); ctx.lineTo(p[0], p[1]); ctx.stroke(); hayTrazo = true; });
  ['pointerup','pointercancel'].forEach(function(t){ c.addEventListener(t, function(){ dibujo = false; }); });
  document.getElementById('btnLimpiar').onclick = ajusta; ajusta();
  function msg(t, ok){ var m = document.getElementById('msg'); m.textContent = t; m.style.color = ok ? '#2e7d32' : '#c62828'; }
  function post(url, body){ return fetch(url, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body: JSON.stringify(body||{})}).then(function(r){ return r.json().catch(function(){ return {ok:false, mensaje:'Respuesta inválida.'}; }); }); }
  document.getElementById('btnOtp').onclick = function(){ post('/firma/'+tok+'/otp').then(function(j){ msg(j.mensaje, j.ok); }); };
  document.getElementById('btnFirmar').onclick = function(){
    if(!hayTrazo) return msg('Dibuja tu firma para continuar.', false);
    if(!document.getElementById('consent').checked) return msg('Debes aceptar el consentimiento.', false);
    post('/firma/'+tok+'/firmar', {otp: document.getElementById('otp').value, identidad: document.getElementById('identidad').value, consentimiento: true, firma: c.toDataURL('image/png')})
      .then(function(j){ msg(j.mensaje || 'Revisa los datos.', j.ok); if(j.ok) document.getElementById('form').querySelectorAll('button,input').forEach(function(x){ x.disabled = true; }); });
  };
})();
</script>
</body>
</html>
