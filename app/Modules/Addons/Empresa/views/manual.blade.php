<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manual General de la Empresa — Meganet</title>
<style>
@verbatim
:root{
  --bg:#f4f6f9; --panel:#ffffff; --ink:#1c2434; --muted:#6b7688;
  --line:#e2e7ee; --brand:#1f6feb; --brand-soft:#e8f0fe;
  --warn-bg:#fff6e5; --warn-ink:#8a5a00; --warn-line:#f0d9a8;
  --shadow:0 1px 2px rgba(16,24,40,.06),0 4px 12px rgba(16,24,40,.05);
}
html[data-theme="dark"]{
  --bg:#0f1420; --panel:#161d2c; --ink:#e6ebf3; --muted:#95a1b5;
  --line:#243044; --brand:#5b9bff; --brand-soft:#1b2b47;
  --warn-bg:#2c2413; --warn-ink:#e0b567; --warn-line:#4a3b1c;
  --shadow:0 1px 2px rgba(0,0,0,.4),0 4px 14px rgba(0,0,0,.35);
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);
  font:15px/1.65 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;}
a{color:var(--brand)}

/* ---- top bar ---- */
.topbar{position:sticky;top:0;z-index:30;background:var(--panel);
  border-bottom:1px solid var(--line);display:flex;align-items:center;gap:14px;
  padding:0 20px;height:58px;box-shadow:var(--shadow)}
.logo{display:flex;align-items:center;gap:10px;font-weight:700;letter-spacing:.2px}
.logo .dot{width:26px;height:26px;border-radius:8px;background:var(--brand);
  display:grid;place-items:center;color:#fff;font-size:13px;font-weight:800}
.crumb{color:var(--muted);font-size:13px}
.spacer{flex:1}
.btn{border:1px solid var(--line);background:transparent;color:var(--ink);
  border-radius:8px;padding:7px 12px;font-size:13px;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-block}
.btn:hover{background:var(--brand-soft);border-color:var(--brand)}
.btn.primary{background:var(--brand);border-color:var(--brand);color:#fff}
.mock-note{background:var(--warn-bg);color:var(--warn-ink);border-bottom:1px solid var(--warn-line);
  padding:8px 20px;font-size:13px}

/* ---- layout ---- */
.wrap{display:grid;grid-template-columns:300px 1fr;gap:22px;
  max-width:1280px;margin:22px auto;padding:0 20px;align-items:start}
.side{position:sticky;top:80px;background:var(--panel);border:1px solid var(--line);
  border-radius:12px;padding:14px;box-shadow:var(--shadow);max-height:calc(100vh - 110px);
  overflow:auto}
.search{width:100%;padding:9px 11px;border:1px solid var(--line);border-radius:9px;
  background:transparent;color:var(--ink);font-family:inherit;font-size:14px;margin-bottom:10px}
.search:focus{outline:none;border-color:var(--brand)}
.toc{list-style:none;margin:0;padding:0;font-size:14px}
.toc li{margin:1px 0}
.toc a{display:block;padding:6px 10px;border-radius:8px;color:var(--ink);
  text-decoration:none;border-left:3px solid transparent}
.toc a:hover{background:var(--brand-soft)}
.toc a.active{background:var(--brand-soft);border-left-color:var(--brand);
  color:var(--brand);font-weight:600}
.toc .sub{padding-left:14px;font-size:13.5px;color:var(--muted)}
.toc .num{color:var(--muted);margin-right:7px;font-variant-numeric:tabular-nums}
.hidden{display:none !important}

.doc{background:var(--panel);border:1px solid var(--line);border-radius:12px;
  padding:34px 40px;box-shadow:var(--shadow)}
.cover{border-bottom:1px solid var(--line);padding-bottom:20px;margin-bottom:26px}
.cover h1{margin:0 0 6px;font-size:26px;letter-spacing:-.3px}
.cover .meta{color:var(--muted);font-size:13px;display:flex;gap:14px;flex-wrap:wrap}
.chip{display:inline-block;font-size:11.5px;padding:2px 9px;border-radius:999px;
  background:var(--brand-soft);color:var(--brand);font-weight:600}
h2{font-size:20px;margin:34px 0 10px;scroll-margin-top:80px;padding-top:6px}
h3{font-size:16px;margin:22px 0 6px;color:var(--ink);scroll-margin-top:80px}
p{margin:0 0 12px}
.pend{background:var(--warn-bg);border:1px dashed var(--warn-line);color:var(--warn-ink);
  border-radius:9px;padding:10px 13px;font-size:13.5px}
.docref{display:inline-flex;align-items:center;gap:8px;margin-top:10px;font-size:13px;
  border:1px solid var(--line);border-radius:9px;padding:7px 11px;color:var(--muted);text-decoration:none}
.docref:hover{border-color:var(--brand);color:var(--brand)}
.docref b{color:var(--ink)}
.sect{border-top:1px solid transparent}
mark{background:#ffe58a;color:#3a2c00;border-radius:3px}
html[data-theme="dark"] mark{background:#6b5410;color:#ffe9ad}
.foot{color:var(--muted);font-size:12.5px;text-align:center;margin:24px 0 40px}

@media (max-width:900px){
  .wrap{grid-template-columns:1fr}
  .side{position:static;max-height:none}
  .doc{padding:24px 20px}
}
@media print{
  .topbar,.side,.mock-note,.foot{display:none !important}
  .wrap{display:block;max-width:none;margin:0;padding:0}
  .doc{border:0;box-shadow:none;padding:0}
  h2{page-break-after:avoid}
}
@endverbatim
</style>
</head>
<body>

<div class="mock-note">
  Documento vivo — el contenido marcado como <em>pendiente</em> es de muestra hasta que
  Dirección General cargue la redacción oficial.
</div>

<div class="topbar">
  <div class="logo"><span class="dot">M</span> MegaISP</div>
  <span class="crumb">Empresa › Manual General</span>
  <span class="spacer"></span>
  <button class="btn" onclick="expandAll()">Expandir todo</button>
  <button class="btn" onclick="window.print()">Imprimir / PDF</button>
  <button class="btn" onclick="toggleTheme()" id="themeBtn">Modo oscuro</button>
  <a class="btn" href="{{ url('/dashboard') }}">Volver al sistema</a>
  <button class="btn primary">Editar</button>
</div>

<div class="wrap">
  <aside class="side">
    <input class="search" id="q" placeholder="Buscar en el manual…" oninput="filtrar(this.value)">
    <ul class="toc" id="toc"></ul>
  </aside>

  <main class="doc" id="doc">
    <div class="cover">
      <span class="chip">Documento vivo</span>
      <h1>Manual General de la Empresa</h1>
      <div class="meta">
        <span>Meganet Telecomunicaciones</span>
        <span>Versión 1.0</span>
        <span>Actualizado {{ now()->format('d/m/Y') }}</span>
        <span>Autoriza: Dirección General</span>
      </div>
    </div>

    <section class="sect">
      <h2 id="identidad">1. Identidad</h2>
      <h3 id="quienes-somos">1.1 Quiénes somos</h3>
      <p>Meganet Telecomunicaciones es un proveedor de servicios de Internet que
      opera red propia de fibra y enlaces inalámbricos, con atención directa a
      clientes residenciales y empresariales en su zona de cobertura.</p>
      <h3 id="historia">1.2 Historia</h3>
      <div class="pend">Pendiente de redacción — dirección aportará el texto.</div>
      <h3 id="datos-empresa">1.3 Datos de la empresa</h3>
      <div class="pend">Pendiente de redacción — razón social, RFC, domicilio fiscal y datos de contacto oficiales.</div>
    </section>

    <section class="sect">
      <h2 id="mision">2. Misión</h2>
      <p>Conectar a nuestra comunidad con un servicio de Internet estable, honesto
      y bien atendido, sostenido por una red propia y un equipo que responde.</p>
      <div class="pend">Texto de muestra — sustituir por la misión oficial aprobada.</div>
    </section>

    <section class="sect">
      <h2 id="vision">3. Visión</h2>
      <p>Ser el operador de referencia en nuestra región por calidad de red y por
      trato al cliente, con cobertura ampliada y operación medible.</p>
      <div class="pend">Texto de muestra — sustituir por la visión oficial aprobada.</div>
    </section>

    <section class="sect">
      <h2 id="valores">4. Valores</h2>
      <p>Cumplimiento de lo prometido · Trato directo y claro · Cuidado de la red ·
      Responsabilidad sobre el trabajo propio · Mejora continua.</p>
    </section>

    <section class="sect">
      <h2 id="objetivos">5. Objetivos estratégicos</h2>
      <h3 id="objetivos-generales">5.1 Objetivos generales</h3>
      <div class="pend">Pendiente de redacción.</div>
      <h3 id="metas-ejercicio">5.2 Metas del ejercicio</h3>
      <div class="pend">Pendiente de redacción.</div>
    </section>

    <section class="sect">
      <h2 id="organizacion">6. Estructura organizacional</h2>
      <h3 id="organigrama">6.1 Organigrama</h3>
      <p>Dirección General · Operaciones y Red · Soporte Técnico · Instalaciones ·
      Administración y Cobranza · Comercial.</p>
      <h3 id="areas">6.2 Áreas y responsabilidades</h3>
      <div class="pend">Pendiente de redacción.</div>
      <a class="docref" href="#"><b>MN-GOB-002</b> · Organigrama oficial (PDF)</a>
    </section>

    <section class="sect">
      <h2 id="politicas">7. Políticas</h2>
      <h3 id="politica-calidad">7.1 Calidad de servicio</h3>
      <p>Parámetros de disponibilidad, tiempos de restablecimiento y criterios de
      escalamiento aplicables a toda la operación.</p>
      <h3 id="politica-seguridad">7.2 Seguridad de la información</h3>
      <p>Manejo de credenciales, accesos a equipos de red, respaldos y
      confidencialidad de datos de clientes.</p>
      <a class="docref" href="#"><b>MN-POL-002</b> · Política de seguridad de la información</a>
      <h3 id="politica-privacidad">7.3 Privacidad y datos personales</h3>
      <p>Aviso de privacidad, finalidades del tratamiento y ejercicio de derechos ARCO.</p>
      <h3 id="politica-recursos">7.4 Uso de recursos y equipo</h3>
      <div class="pend">Pendiente de redacción.</div>
      <h3 id="politica-atencion">7.5 Atención al cliente</h3>
      <div class="pend">Pendiente de redacción.</div>
    </section>

    <section class="sect">
      <h2 id="reglamentos">8. Reglamentos</h2>
      <h3 id="reglamento-interior">8.1 Reglamento interior de trabajo</h3>
      <p>Jornada, asistencia, permisos, obligaciones y sanciones.</p>
      <a class="docref" href="#"><b>MN-POL-001</b> · Reglamento interior de trabajo (PDF firmado)</a>
      <h3 id="codigo-conducta">8.2 Código de conducta</h3>
      <div class="pend">Pendiente de redacción.</div>
      <h3 id="seguridad-higiene">8.3 Seguridad e higiene</h3>
      <p>Uso de equipo de protección en trabajos en altura, manejo de escaleras y
      herramienta, y protocolo ante incidentes.</p>
    </section>

    <section class="sect">
      <h2 id="procedimientos">9. Procedimientos clave</h2>
      <h3 id="proc-instalacion">9.1 Instalación</h3>
      <p>Resumen del flujo: orden de trabajo → verificación de factibilidad →
      tendido y configuración → acta de instalación firmada.</p>
      <a class="docref" href="#"><b>MN-OPE-001</b> · Manual de instalación</a>
      <h3 id="proc-soporte">9.2 Soporte y fallas</h3>
      <p>Recepción del reporte, diagnóstico remoto, visita en sitio y cierre con
      confirmación del cliente.</p>
      <h3 id="proc-cobranza">9.3 Cobranza</h3>
      <div class="pend">Pendiente de redacción.</div>
      <h3 id="proc-almacen">9.4 Almacén</h3>
      <div class="pend">Pendiente de redacción.</div>
    </section>

    <section class="sect">
      <h2 id="compromiso">10. Compromiso con el cliente</h2>
      <h3 id="sla">10.1 Niveles de servicio</h3>
      <p>Tiempos objetivo de respuesta y restablecimiento por tipo de incidencia.</p>
      <h3 id="canales">10.2 Canales de atención</h3>
      <p>Teléfono, WhatsApp, portal de cliente y atención en oficina.</p>
    </section>

    <section class="sect">
      <h2 id="directorio">11. Directorio</h2>
      <p>Contactos internos por área, extensiones y responsables de guardia.</p>
      <div class="pend">Pendiente de redacción.</div>
    </section>

    <div class="foot">Meganet Telecomunicaciones · Manual General · documento interno</div>
  </main>
</div>

<script>
@verbatim
/* índice generado a partir de los encabezados del documento */
const toc = document.getElementById('toc');
document.querySelectorAll('#doc h2, #doc h3').forEach(h=>{
  const li=document.createElement('li');
  const a=document.createElement('a');
  a.href='#'+h.id;
  a.textContent=h.textContent;
  if(h.tagName==='H3') a.className='sub';
  a.dataset.target=h.id;
  li.appendChild(a); toc.appendChild(li);
});

/* scroll-spy */
const links=[...toc.querySelectorAll('a')];
const heads=[...document.querySelectorAll('#doc h2, #doc h3')];
function spy(){
  let cur=heads[0];
  for(const h of heads){ if(h.getBoundingClientRect().top<=110) cur=h; }
  links.forEach(a=>a.classList.toggle('active', a.dataset.target===cur.id));
}
document.addEventListener('scroll',spy,{passive:true}); spy();

/* buscador: filtra el índice y resalta en el texto */
function limpiar(){
  document.querySelectorAll('#doc mark').forEach(m=>{
    m.replaceWith(document.createTextNode(m.textContent));
  });
  document.getElementById('doc').normalize();
}
function filtrar(q){
  q=q.trim().toLowerCase(); limpiar();
  links.forEach(a=>a.parentElement.classList.toggle('hidden', q && !a.textContent.toLowerCase().includes(q)));
  if(q.length<3) return;
  const walker=document.createTreeWalker(document.getElementById('doc'),NodeFilter.SHOW_TEXT);
  const hits=[]; let n;
  while(n=walker.nextNode()){ if(n.nodeValue.toLowerCase().includes(q)) hits.push(n); }
  hits.forEach(node=>{
    const i=node.nodeValue.toLowerCase().indexOf(q);
    const after=node.splitText(i); after.splitText(q.length);
    const m=document.createElement('mark'); m.textContent=after.nodeValue;
    after.replaceWith(m);
  });
}

function expandAll(){ links.forEach(a=>a.parentElement.classList.remove('hidden')); document.getElementById('q').value=''; limpiar(); }

function toggleTheme(){
  const r=document.documentElement;
  const dark=r.getAttribute('data-theme')==='dark';
  r.setAttribute('data-theme', dark?'light':'dark');
  document.getElementById('themeBtn').textContent = dark?'Modo oscuro':'Modo claro';
}
@endverbatim
</script>
</body>
</html>
