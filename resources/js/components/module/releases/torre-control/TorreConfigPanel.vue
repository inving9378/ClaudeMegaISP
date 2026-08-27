<template>
  <!--
    ENGRANE de la barra de pestañas de la Torre. Va al final, empujado con `margin-left:auto`.

    #648 — DEJÓ DE ABRIR UN TABLERO PROPIO. Antes este engrane desplegaba su propio modal con la
    configuración, y había otros dos sitios mostrando lo mismo (el engrane de la cabecera del
    sistema y el tablero de compuertas). Tres pantallas con la misma información envejecen por
    separado y acaban contradiciéndose, que es la forma más segura de que nadie le crea a ninguna.
    Ahora el engrane es un ATAJO: trae a la pestaña «Configuración», la única pantalla que existe.
    El contenido del modal viejo no se perdió — vive completo en esa pestaña.
  -->
  <button class="tcfg-gear" :class="{ 'tcfg-dark': dark }" type="button"
          title="Configuración de la Torre" @click="$emit('abrir-configuracion')">
    <i class="bi bi-gear-fill"></i>
  </button>
</template>

<script>
import { darkMode } from '../../../../hook/appConfig.js';

const { computed } = Vue;

export default {
  name: 'TorreConfigPanel',
  emits: ['abrir-configuracion'],
  // El tema se lee del hook del proyecto (body[data-layout-mode]) en vez de recibirlo por prop:
  // así el engrane funciona desde cualquier padre y respeta el toggle claro/oscuro existente.
  setup() {
    return { dark: computed(() => darkMode.value) };
  },
};
</script>

<style scoped>
.tcfg-gear{margin-left:auto;width:34px;height:34px;border-radius:9px;border:1px solid var(--tc-border,#d7dee7);background:var(--tc-card,#fff);color:var(--tc-fg,#334155);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;}
.tcfg-gear:hover{background:var(--tc-accent,#0d9488);color:#fff;border-color:transparent;}
/* backdrop DEBAJO del modal — invertirlo deja el modal inerte */
.tcfg-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1050;}
.tcfg-backdrop-alto{z-index:1070;}
.tcfg-modal{position:fixed;z-index:1060;top:50%;left:50%;transform:translate(-50%,-50%);width:min(880px,94vw);max-height:88vh;display:flex;flex-direction:column;background:var(--tc-card,#fff);color:var(--tc-fg,#0f172a);border-radius:14px;box-shadow:0 24px 60px rgba(15,23,42,.3);}
.tcfg-confirm{position:fixed;z-index:1080;top:50%;left:50%;transform:translate(-50%,-50%);width:min(520px,92vw);padding:22px;background:var(--tc-card,#fff);color:var(--tc-fg,#0f172a);border-radius:14px;box-shadow:0 24px 60px rgba(15,23,42,.35);border-top:4px solid #dc2626;}
.tcfg-head{display:flex;justify-content:space-between;align-items:flex-start;padding:18px 22px 10px;border-bottom:1px solid var(--tc-border,#e2e8f0);}
.tcfg-h2{font-size:17px;font-weight:800;margin:0;}
.tcfg-h3{font-size:14px;font-weight:800;margin:0 0 6px;}
.tcfg-h4{font-size:12.5px;font-weight:800;margin:16px 0 4px;}
.tcfg-sub,.tcfg-note{font-size:12px;opacity:.75;margin:2px 0 0;line-height:1.5;}
.tcfg-tag{font-size:10.5px;font-weight:600;opacity:.6;margin-left:6px;}
.tcfg-x{background:none;border:none;font-size:18px;cursor:pointer;color:inherit;opacity:.6;}
.tcfg-body{overflow-y:auto;padding:16px 22px;}
.tcfg-sec{padding:14px 0;border-bottom:1px solid var(--tc-border,#eef2f7);}
.tcfg-ro{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;border-radius:9px;padding:9px 12px;font-size:12px;margin-bottom:12px;line-height:1.5;}
.tcfg-niveles{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px;margin:10px 0;}
.tcfg-nivel{border:1px solid var(--tc-border,#d7dee7);border-radius:9px;padding:9px 11px;cursor:pointer;display:flex;flex-direction:column;gap:2px;font-size:12px;}
.tcfg-nivel b{font-size:13px;}
.tcfg-nivel span{opacity:.7;}
.tcfg-on{border-color:var(--tc-accent,#0d9488);box-shadow:inset 0 0 0 1px var(--tc-accent,#0d9488);}
.tcfg-dis{opacity:.6;cursor:not-allowed;}
.tcfg-matriz,.tcfg-actores,.tcfg-motores{width:100%;border-collapse:collapse;font-size:12px;margin-top:8px;}
.tcfg-matriz th,.tcfg-matriz td,.tcfg-actores td,.tcfg-motores th,.tcfg-motores td{border:1px solid var(--tc-border,#e2e8f0);padding:5px 8px;text-align:left;}
.tcfg-auto{color:#15803d;font-weight:700;}
.tcfg-irving{color:#b45309;}
.tcfg-bloq td{background:rgba(220,38,38,.07);color:#b91c1c;font-weight:700;font-size:11px;}
.tcfg-campo{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:6px 0;font-size:12.5px;}
.tcfg-campo em{opacity:.6;font-style:normal;font-size:11px;}
.tcfg-campo input[type=number]{width:96px;padding:4px 8px;border:1px solid var(--tc-border,#d7dee7);border-radius:7px;background:transparent;color:inherit;}
.tcfg-rojo td{background:rgba(220,38,38,.08);}
.tcfg-fallo td{background:rgba(220,38,38,.05);font-size:11px;color:#b91c1c;}
.tcfg-fallo code{font-size:10.5px;}
.tcfg-cron td{background:rgba(217,119,6,.07);font-size:11px;}
.tcfg-nunca{color:#b91c1c;font-weight:700;}
.tcfg-pierde{font-size:11.5px;color:#b91c1c;margin:6px 0 0;}
.tcfg-guard{font-size:12px;padding:4px 0;opacity:.85;}
.tcfg-guard em{opacity:.6;font-style:normal;}
/* Panel por actor (#943) */
.tcfg-tabbar{display:flex;flex-wrap:wrap;gap:6px;margin:10px 0 4px;}
.tcfg-tab{font-size:11.5px;padding:5px 10px;border-radius:999px;border:1px solid var(--tc-border,#d7dee7);background:transparent;color:inherit;cursor:pointer;display:inline-flex;align-items:center;gap:5px;}
.tcfg-tab-on{background:var(--tc-accent,#0d9488);color:#fff;border-color:transparent;font-weight:700;}
.tcfg-tab-n{font-size:10px;opacity:.75;background:rgba(127,127,127,.18);border-radius:999px;padding:1px 6px;}
.tcfg-tab-on .tcfg-tab-n{background:rgba(255,255,255,.25);opacity:1;}
.tcfg-tab-body{padding-top:8px;}
.tcfg-bucket-sec{margin-top:14px;}
.tcfg-bucket-sec:first-child{margin-top:2px;}
.tcfg-ctrl{border:1px solid var(--tc-border,#eef2f7);border-radius:8px;padding:7px 10px;margin-top:6px;cursor:pointer;font-size:12px;}
.tcfg-ctrl-head{display:flex;align-items:center;gap:8px;}
.tcfg-ctrl-head code{font-size:11px;flex:1;}
.tcfg-ctrl-val{font-size:11.5px;opacity:.85;}
.tcfg-ctrl-head i{opacity:.5;font-size:11px;}
.tcfg-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.tcfg-dot-verde{background:#16a34a;}
.tcfg-dot-azul{background:#2563eb;}
.tcfg-dot-roja{background:#dc2626;}
.tcfg-ctrl-det{margin-top:7px;padding-top:7px;border-top:1px dashed var(--tc-border,#e2e8f0);font-size:11.5px;line-height:1.6;}
.tcfg-ctrl-det p{margin:2px 0;}
.tcfg-ctrl-pend{color:#b45309;}
.tcfg-over{margin-top:10px;background:rgba(217,119,6,.1);border:1px solid rgba(217,119,6,.35);border-radius:8px;padding:8px 11px;font-size:12px;}
.tcfg-id{display:inline-block;margin-left:5px;font-weight:700;}
.tcfg-foot{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 22px;border-top:1px solid var(--tc-border,#e2e8f0);}
.tcfg-btn{padding:6px 14px;border-radius:8px;border:1px solid var(--tc-border,#d7dee7);background:transparent;color:inherit;cursor:pointer;font-size:12.5px;margin-left:8px;}
.tcfg-primary{background:var(--tc-accent,#0d9488);color:#fff;border-color:transparent;font-weight:700;}
.tcfg-primary:disabled{opacity:.45;cursor:not-allowed;}
.tcfg-danger{background:#dc2626;color:#fff;border-color:transparent;font-weight:700;}
.tcfg-confirm-btns{display:flex;justify-content:flex-end;margin-top:14px;}
.tcfg-load,.tcfg-alert{padding:24px 22px;font-size:13px;}
.tcfg-alert{color:#b91c1c;}
/* dark: sólo se redefinen tokens, nunca colores sueltos */
.tcfg-dark{--tc-card:#0f172a;--tc-fg:#e2e8f0;--tc-border:#1e293b;}
.tcfg-dark .tcfg-ro{background:rgba(59,130,246,.12);color:#93c5fd;border-color:#1e3a5f;}
</style>
