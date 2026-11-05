@extends('core-layout::master')
@section('content')
<div class="container-fluid p-3" id="csid-zonas">
  <h4>Zonas PPPoE (Calling-Station-Id)</h4>
  <table class="table table-sm"><thead><tr><th>NAS</th><th>Modo</th><th>Aprendiendo desde</th><th>Aplicando desde</th><th></th></tr></thead><tbody id="zonas"></tbody></table>
  <h5 class="mt-4">Re-aprender MAC de un usuario (cambio de equipo)</h5>
  <div class="input-group mb-2" style="max-width:420px"><input id="usr" class="form-control" placeholder="Usuario PPPoE"><button class="btn btn-warning" id="btn-re">Re-aprender</button></div>
  <div id="msg" class="text-muted"></div>
  <h5 class="mt-4">Pendientes (falló al cerrar OT)</h5>
  <table class="table table-sm"><tbody id="pend"></tbody></table>
</div>
<script>
(function(){
  const H={'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]')?.content,'Content-Type':'application/json','Accept':'application/json'};
  const esc=s=>String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const post=(u,b)=>fetch(u,{method:'POST',headers:H,body:JSON.stringify(b)}).then(r=>r.json());
  function load(){fetch('/configuracion/csid-zonas/listar',{headers:H}).then(r=>r.json()).then(d=>{
    document.getElementById('zonas').innerHTML=d.zonas.map(z=>`<tr><td>${esc(z.nas_ip)}</td><td>${esc(z.modo)}</td><td>${esc(z.aprendiendo_desde)}</td><td>${esc(z.aplicando_desde)}</td><td>${['apagado','aprender','aplicar'].filter(m=>m!==z.modo).map(m=>`<button class="btn btn-sm btn-outline-secondary me-1" data-nas="${esc(z.nas_ip)}" data-modo="${m}">${m}</button>`).join('')}</td></tr>`).join('')||'<tr><td colspan=5>Sin zonas</td></tr>';
    document.getElementById('pend').innerHTML=d.pendientes.map(p=>`<tr><td>${esc(p.username||'(sin usuario)')}</td><td>OT ${esc(p.task_id)}</td><td>${esc(p.error)}</td></tr>`).join('')||'<tr><td>Ninguno</td></tr>';
  });}
  document.getElementById('zonas').addEventListener('click',e=>{const b=e.target.closest('button[data-nas]');if(!b)return;
    post('/configuracion/csid-zonas/modo',{nas_ip:b.dataset.nas,modo:b.dataset.modo}).then(r=>{document.getElementById('msg').textContent=r.mensaje||r.message;load();});});
  document.getElementById('btn-re').onclick=()=>{const u=document.getElementById('usr').value.trim();if(!u||!confirm('¿Re-aprender la MAC de '+u+'?'))return;
    post('/configuracion/csid-zonas/reaprender',{username:u}).then(r=>{document.getElementById('msg').textContent=r.mensaje||r.message;load();});};
  load();
})();
</script>
@endsection
