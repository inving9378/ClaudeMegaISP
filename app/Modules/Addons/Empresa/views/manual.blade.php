<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
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
.btn.danger{border-color:#e14b4b;color:#e14b4b}
.btn.danger:hover{background:#fdeaea}
.btn.small{padding:4px 9px;font-size:12px;border-radius:6px}
.btn[disabled]{opacity:.45;cursor:default;pointer-events:none}
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
.hidden{display:none !important}

.doc{background:var(--panel);border:1px solid var(--line);border-radius:12px;
  padding:34px 40px;box-shadow:var(--shadow)}
.cover{border-bottom:1px solid var(--line);padding-bottom:20px;margin-bottom:26px}
.cover h1{margin:0 0 6px;font-size:26px;letter-spacing:-.3px}
.cover .meta{color:var(--muted);font-size:13px;display:flex;gap:14px;flex-wrap:wrap}
.chip{display:inline-block;font-size:11.5px;padding:2px 9px;border-radius:999px;
  background:var(--brand-soft);color:var(--brand);font-weight:600}
.chip.warn{background:var(--warn-bg);color:var(--warn-ink)}
h2{font-size:20px;margin:34px 0 10px;scroll-margin-top:80px;padding-top:6px;display:flex;align-items:center;gap:10px}
h3{font-size:16px;margin:22px 0 6px;color:var(--ink);scroll-margin-top:80px;display:flex;align-items:center;gap:8px}
p{margin:0 0 12px}
.pend{background:var(--warn-bg);border:1px dashed var(--warn-line);color:var(--warn-ink);
  border-radius:9px;padding:10px 13px;font-size:13.5px}
.sect{border-top:1px solid transparent}
.sect-body{outline:none}
.sect-body.editing{border:1px dashed var(--brand);border-radius:8px;padding:10px 12px;background:var(--brand-soft)}
.sect-tools{display:flex;gap:8px;align-items:center;margin:8px 0 4px;flex-wrap:wrap}
.chapter-tools{display:flex;gap:8px;align-items:center;margin:6px 0 16px}
mark{background:#ffe58a;color:#3a2c00;border-radius:3px}
html[data-theme="dark"] mark{background:#6b5410;color:#ffe9ad}
.foot{color:var(--muted);font-size:12.5px;text-align:center;margin:24px 0 40px}
.empty{color:var(--muted);font-size:14px;padding:30px 0;text-align:center}

@media (max-width:900px){
  .wrap{grid-template-columns:1fr}
  .side{position:static;max-height:none}
  .doc{padding:24px 20px}
}
@media print{
  .topbar,.side,.mock-note,.foot,.sect-tools,.chapter-tools{display:none !important}
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
  Dirección General cargue la redacción oficial. Los cambios guardados quedan como
  borrador hasta que se publican.
</div>

<div class="topbar">
  <div class="logo"><span class="dot">M</span> MegaISP</div>
  <span class="crumb">Empresa › Manual General</span>
  <span class="spacer"></span>
  <button class="btn" onclick="expandAll()">Expandir todo</button>
  <a class="btn" href="{{ url('/empresa/manual/pdf') }}" target="_blank">Exportar PDF</a>
  <button class="btn" onclick="window.print()">Imprimir</button>
  <button class="btn" onclick="toggleTheme()" id="themeBtn">Modo oscuro</button>
  <a class="btn" href="{{ url('/dashboard') }}">Volver al sistema</a>
  @if($canEdit || $canCreate || $canDelete || $canPublish)
    <button class="btn primary" id="btnEditToggle" onclick="toggleEditMode()">Editar</button>
  @endif
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
        <span>Editable desde este panel</span>
        <span id="lastLoad"></span>
      </div>
    </div>

    <div id="chapters"></div>

    @if($canCreate)
      <div class="chapter-tools">
        <button class="btn small primary" onclick="crearCapitulo()">+ Nuevo capítulo</button>
      </div>
    @endif

    <div class="foot">Meganet Telecomunicaciones · Manual General · documento interno</div>
  </main>
</div>

<script>
@verbatim
const CSRF = document.querySelector('meta[name=csrf-token]').content;
const API = '/empresa/manual/api';
const PERMS = {
  edit: @endverbatim{{ $canEdit ? 'true' : 'false' }}@verbatim,
  create: @endverbatim{{ $canCreate ? 'true' : 'false' }}@verbatim,
  del: @endverbatim{{ $canDelete ? 'true' : 'false' }}@verbatim,
  publish: @endverbatim{{ $canPublish ? 'true' : 'false' }}@verbatim
};

let DATA = null;
let editMode = false;

async function api(method, path, body) {
  const res = await fetch(API + path, {
    method,
    headers: {
      'X-CSRF-TOKEN': CSRF,
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: body ? JSON.stringify(body) : undefined
  });
  if (!res.ok) {
    const data = await res.json().catch(() => ({}));
    throw new Error(data.message || ('Error ' + res.status));
  }
  return res.status === 204 ? null : res.json();
}

async function load() {
  DATA = await api('GET', '/data');
  render();
  document.getElementById('lastLoad').textContent = 'Cargado ' + new Date().toLocaleString('es-MX');
}

function chapterEl(chapter) {
  const wrap = document.createElement('section');
  wrap.className = 'sect';

  const h2 = document.createElement('h2');
  h2.id = chapter.slug || ('cap-' + chapter.id);
  const titleSpan = document.createElement('span');
  titleSpan.textContent = chapter.title;
  h2.appendChild(titleSpan);
  if (editMode && PERMS.edit) {
    h2.appendChild(iconBtn('✎', 'Renombrar capítulo', () => renombrarCapitulo(chapter)));
  }
  if (editMode && PERMS.del) {
    h2.appendChild(iconBtn('🗑', 'Eliminar capítulo', () => eliminarCapitulo(chapter)));
  }
  wrap.appendChild(h2);

  (chapter.sections || []).forEach(section => wrap.appendChild(sectionEl(chapter, section)));

  if (editMode && PERMS.create) {
    const tools = document.createElement('div');
    tools.className = 'chapter-tools';
    const btn = document.createElement('button');
    btn.className = 'btn small';
    btn.textContent = '+ Nueva sección en "' + chapter.title + '"';
    btn.onclick = () => crearSeccion(chapter);
    tools.appendChild(btn);
    wrap.appendChild(tools);
  }

  return wrap;
}

function sectionEl(chapter, section) {
  const box = document.createElement('div');
  box.dataset.sectionId = section.id;

  const h3 = document.createElement('h3');
  h3.id = section.id + '-' + (section.title || '').toLowerCase().replace(/[^a-z0-9]+/g, '-');
  const titleSpan = document.createElement('span');
  titleSpan.textContent = section.title;
  h3.appendChild(titleSpan);
  if (section.has_unpublished_changes) {
    const badge = document.createElement('span');
    badge.className = 'chip warn';
    badge.textContent = 'sin publicar';
    h3.appendChild(badge);
  }
  if (editMode && PERMS.edit) {
    h3.appendChild(iconBtn('✎', 'Renombrar sección', () => renombrarSeccion(section)));
  }
  if (editMode && PERMS.del) {
    h3.appendChild(iconBtn('🗑', 'Eliminar sección', () => eliminarSeccion(chapter, section)));
  }
  box.appendChild(h3);

  const body = document.createElement('div');
  body.className = 'sect-body';
  const shown = (section.published_content ?? section.content) || '<p class="pend">Sin contenido todavía.</p>';
  body.innerHTML = shown;

  if (editMode && PERMS.edit) {
    body.classList.add('editing');
    body.contentEditable = 'true';
    body.innerHTML = section.content || '';
  }
  box.appendChild(body);

  if (editMode && (PERMS.edit || PERMS.publish)) {
    const tools = document.createElement('div');
    tools.className = 'sect-tools';
    if (PERMS.edit) {
      const save = document.createElement('button');
      save.className = 'btn small';
      save.textContent = 'Guardar borrador';
      save.onclick = () => guardarSeccion(section, body);
      tools.appendChild(save);
    }
    if (PERMS.publish) {
      const pub = document.createElement('button');
      pub.className = 'btn small primary';
      pub.textContent = 'Publicar';
      if (!section.has_unpublished_changes) pub.setAttribute('disabled', 'disabled');
      pub.onclick = () => publicarSeccion(section);
      tools.appendChild(pub);
    }
    box.appendChild(tools);
  }

  return box;
}

function iconBtn(symbol, title, onClick) {
  const b = document.createElement('button');
  b.className = 'btn small';
  b.style.padding = '2px 7px';
  b.title = title;
  b.textContent = symbol;
  b.onclick = onClick;
  return b;
}

function render() {
  const container = document.getElementById('chapters');
  container.innerHTML = '';
  if (!DATA.chapters.length) {
    container.innerHTML = '<div class="empty">Todavía no hay capítulos. ' +
      (PERMS.create ? 'Usa "+ Nuevo capítulo" para empezar.' : '') + '</div>';
  } else {
    DATA.chapters.forEach(ch => container.appendChild(chapterEl(ch)));
  }
  buildToc();
}

function buildToc() {
  const toc = document.getElementById('toc');
  toc.innerHTML = '';
  document.querySelectorAll('#doc h2, #doc h3').forEach(h => {
    const li = document.createElement('li');
    const a = document.createElement('a');
    a.href = '#' + h.id;
    a.textContent = h.querySelector('span') ? h.querySelector('span').textContent : h.textContent;
    if (h.tagName === 'H3') a.className = 'sub';
    a.dataset.target = h.id;
    li.appendChild(a);
    toc.appendChild(li);
  });
  spy();
}

async function guardarSeccion(section, bodyEl) {
  try {
    const { section: updated } = await api('PUT', '/sections/' + section.id, { content: bodyEl.innerHTML });
    Object.assign(section, updated);
    section.has_unpublished_changes = true;
    render();
  } catch (e) {
    alert('No se pudo guardar: ' + e.message);
  }
}

async function publicarSeccion(section) {
  try {
    await api('POST', '/sections/' + section.id + '/publicar');
    await load();
  } catch (e) {
    alert('No se pudo publicar: ' + e.message);
  }
}

async function crearCapitulo() {
  const title = prompt('Título del nuevo capítulo:');
  if (!title || !title.trim()) return;
  try {
    await api('POST', '/chapters', { title: title.trim() });
    await load();
  } catch (e) {
    alert('No se pudo crear el capítulo: ' + e.message);
  }
}

async function renombrarCapitulo(chapter) {
  const title = prompt('Nuevo título del capítulo:', chapter.title);
  if (!title || !title.trim() || title.trim() === chapter.title) return;
  try {
    await api('PUT', '/chapters/' + chapter.id, { title: title.trim() });
    await load();
  } catch (e) {
    alert('No se pudo renombrar: ' + e.message);
  }
}

async function eliminarCapitulo(chapter) {
  if (!confirm('¿Eliminar el capítulo "' + chapter.title + '" y todas sus secciones?')) return;
  try {
    await api('POST', '/chapters/' + chapter.id + '/eliminar');
    await load();
  } catch (e) {
    alert('No se pudo eliminar: ' + e.message);
  }
}

async function crearSeccion(chapter) {
  const title = prompt('Título de la nueva sección:');
  if (!title || !title.trim()) return;
  try {
    await api('POST', '/sections', { chapter_id: chapter.id, title: title.trim() });
    await load();
  } catch (e) {
    alert('No se pudo crear la sección: ' + e.message);
  }
}

async function renombrarSeccion(section) {
  const title = prompt('Nuevo título de la sección:', section.title);
  if (!title || !title.trim() || title.trim() === section.title) return;
  try {
    await api('PUT', '/sections/' + section.id, { title: title.trim() });
    await load();
  } catch (e) {
    alert('No se pudo renombrar: ' + e.message);
  }
}

async function eliminarSeccion(chapter, section) {
  if (!confirm('¿Eliminar la sección "' + section.title + '"?')) return;
  try {
    await api('POST', '/sections/' + section.id + '/eliminar');
    await load();
  } catch (e) {
    alert('No se pudo eliminar: ' + e.message);
  }
}

function toggleEditMode() {
  editMode = !editMode;
  document.getElementById('btnEditToggle').textContent = editMode ? 'Salir de edición' : 'Editar';
  document.getElementById('btnEditToggle').classList.toggle('primary', !editMode);
  render();
}

/* scroll-spy */
function spy() {
  const links = [...document.querySelectorAll('#toc a')];
  const heads = [...document.querySelectorAll('#doc h2, #doc h3')];
  if (!heads.length) return;
  let cur = heads[0];
  for (const h of heads) { if (h.getBoundingClientRect().top <= 110) cur = h; }
  links.forEach(a => a.classList.toggle('active', a.dataset.target === cur.id));
}
document.addEventListener('scroll', spy, { passive: true });

/* buscador: filtra el índice y resalta en el texto */
function limpiar() {
  document.querySelectorAll('#doc mark').forEach(m => {
    m.replaceWith(document.createTextNode(m.textContent));
  });
  document.getElementById('doc').normalize();
}
function filtrar(q) {
  q = q.trim().toLowerCase(); limpiar();
  document.querySelectorAll('#toc a').forEach(a => a.parentElement.classList.toggle('hidden', q && !a.textContent.toLowerCase().includes(q)));
  if (q.length < 3) return;
  const walker = document.createTreeWalker(document.getElementById('doc'), NodeFilter.SHOW_TEXT);
  const hits = []; let n;
  while (n = walker.nextNode()) { if (n.nodeValue.toLowerCase().includes(q)) hits.push(n); }
  hits.forEach(node => {
    const i = node.nodeValue.toLowerCase().indexOf(q);
    const after = node.splitText(i); after.splitText(q.length);
    const m = document.createElement('mark'); m.textContent = after.nodeValue;
    after.replaceWith(m);
  });
}

function expandAll() {
  document.querySelectorAll('#toc a').forEach(a => a.parentElement.classList.remove('hidden'));
  document.getElementById('q').value = '';
  limpiar();
}

function toggleTheme() {
  const r = document.documentElement;
  const dark = r.getAttribute('data-theme') === 'dark';
  r.setAttribute('data-theme', dark ? 'light' : 'dark');
  document.getElementById('themeBtn').textContent = dark ? 'Modo oscuro' : 'Modo claro';
}

load();
@endverbatim
</script>
</body>
</html>
