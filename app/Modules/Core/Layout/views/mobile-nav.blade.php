{{--
    NAVEGACIÓN MÓVIL — riel deslizable (CAPA ADITIVA, solo celular).
    ============================================================================
    Principio: NO INVENTAR NADA. El riel ESPEJA el DOM del sidebar ya renderizado
    (#side-menu): el servidor ya aplicó todos los @can/roles, así que el riel hereda
    módulos, orden, hijos, rutas y permisos IDÉNTICOS (incluye los 2 bloques
    hardcodeados WhatsApp/Portal de Pago y los addons dinámicos), cero duplicación.

    Reglas (decisiones de Irving):
      - Se activa SOLO por breakpoint CSS (max-width:992px), NUNCA por User-Agent.
      - El escritorio queda INTACTO (#mobile-nav-root nace display:none; solo el
        @media lo enciende).
      - Convive con el hamburguesa existente (no se elimina en esta fase).
      - Respeta el tema con body[data-layout-mode="dark"].
      - Degrada con gracia: si #side-menu no existe / cambia de estructura, el riel
        simplemente NO aparece (el hamburguesa sigue funcionando); nunca rompe la página.
      - Se construye UNA vez por carga; solo LEE el sidebar (no toca metisMenu ni
        sessionStorage).
      - Íconos: reusa los feather ya renderizados del sidebar (sin CDNs nuevos).

    ⚠️ Colores: paleta PLACEHOLDER (pendiente el mockup medussa-nav-movil-mockup.html).
       Mapa único abajo (MNAV_COLORS, por label normalizado). Al llegar el MODS del
       mockup, reemplazar SOLO ese objeto.
--}}
<div id="mobile-nav-root" aria-hidden="true">
    <nav class="mnav-rail" id="mnav-rail" aria-label="Navegación de módulos"></nav>

    {{-- Hoja de submenú (2º nivel) para módulos con hijos --}}
    <div class="mnav-sheet-backdrop" id="mnav-sheet-backdrop" hidden></div>
    <div class="mnav-sheet" id="mnav-sheet" role="dialog" aria-modal="true" hidden>
        <div class="mnav-sheet-handle"></div>
        <div class="mnav-sheet-head"><span id="mnav-sheet-title"></span>
            <button type="button" class="mnav-sheet-close" id="mnav-sheet-close" aria-label="Cerrar">&times;</button>
        </div>
        <ul class="mnav-sheet-list" id="mnav-sheet-list"></ul>
    </div>

    {{-- Buscador de respaldo ("lupa"). Lugar final = topbar delgada (pendiente mockup);
         por ahora botón flotante. Busca sobre el MISMO espejo (módulos + hijos). --}}
    <button type="button" class="mnav-search-btn" id="mnav-search-btn" aria-label="Buscar módulo">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
    </button>
    <div class="mnav-search" id="mnav-search" hidden>
        <div class="mnav-search-bar">
            <input type="search" id="mnav-search-input" placeholder="Buscar módulo o pantalla…" autocomplete="off" />
            <button type="button" id="mnav-search-close" aria-label="Cerrar">&times;</button>
        </div>
        <ul class="mnav-search-list" id="mnav-search-list"></ul>
    </div>
</div>

<style>
/* ============================================================================
   Solo-móvil: TODO el árbol nace oculto; el @media lo enciende. Escritorio intacto.
   ============================================================================ */
#mobile-nav-root { display: none; }

@media (max-width: 992px) {
    #mobile-nav-root { display: block; }

    /* Riel inferior fijo, deslizable horizontal con scroll-snap + difuminado derecho */
    .mnav-rail {
        position: fixed; left: 0; right: 0; bottom: 0; z-index: 1030;
        display: flex; gap: 6px; padding: 8px 10px calc(8px + env(safe-area-inset-bottom));
        overflow-x: auto; overflow-y: hidden; scroll-snap-type: x proximity;
        background: var(--mnav-bg, #ffffff);
        border-top: 1px solid var(--mnav-border, #e5e7eb);
        box-shadow: 0 -2px 10px rgba(0,0,0,.06);
        -webkit-overflow-scrolling: touch; scrollbar-width: none;
        /* pista de scroll: se desvanece en el borde derecho */
        -webkit-mask-image: linear-gradient(to right, #000 88%, transparent 100%);
                mask-image: linear-gradient(to right, #000 88%, transparent 100%);
    }
    .mnav-rail::-webkit-scrollbar { display: none; }

    .mnav-item {
        flex: 0 0 auto; scroll-snap-align: start;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 4px; width: 66px; min-height: 58px; padding: 6px 4px;
        border: none; background: transparent; color: var(--mnav-text, #374151);
        font-size: 10px; line-height: 1.1; text-align: center; text-decoration: none;
        cursor: pointer; border-radius: 12px;
    }
    .mnav-item:active { background: var(--mnav-active, rgba(0,0,0,.04)); }
    .mnav-item .mnav-ico {
        width: 40px; height: 40px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; background: var(--mnav-accent, #4f46e5);
    }
    .mnav-item .mnav-ico svg { width: 20px; height: 20px; }
    .mnav-item .mnav-lbl {
        max-width: 64px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .mnav-item .mnav-caret { display: none; } /* marca visual de "tiene hijos" (Fase visual) */

    /* Empuja el contenido para que el riel no tape el final de la página */
    #layout-wrapper { padding-bottom: 84px; }

    /* ---- Hoja de submenú ---- */
    /* [hidden] debe ganar sobre el display:flex de .mnav-sheet (si no, la hoja se ve
       siempre y tapa el riel). El selector clase+atributo es más específico. */
    .mnav-sheet[hidden], .mnav-sheet-backdrop[hidden] { display: none; }
    .mnav-sheet-backdrop {
        position: fixed; inset: 0; z-index: 1039; background: rgba(0,0,0,.45);
    }
    .mnav-sheet {
        position: fixed; left: 0; right: 0; bottom: 0; z-index: 1040;
        max-height: 70vh; display: flex; flex-direction: column;
        background: var(--mnav-bg, #fff);
        border-radius: 16px 16px 0 0; box-shadow: 0 -8px 30px rgba(0,0,0,.2);
        padding: 8px 0 calc(8px + env(safe-area-inset-bottom));
    }
    .mnav-sheet-handle {
        width: 40px; height: 4px; border-radius: 2px; margin: 4px auto 8px;
        background: var(--mnav-border, #d1d5db);
    }
    .mnav-sheet-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 4px 18px 10px; font-weight: 700; font-size: 15px;
        color: var(--mnav-text, #111827); border-bottom: 1px solid var(--mnav-border, #eef0f3);
    }
    .mnav-sheet-close { border: none; background: transparent; font-size: 24px; line-height: 1; color: var(--mnav-text, #6b7280); }
    .mnav-sheet-list { list-style: none; margin: 0; padding: 6px 0; overflow-y: auto; }
    .mnav-sheet-list a {
        display: flex; align-items: center; gap: 10px; padding: 12px 18px;
        color: var(--mnav-text, #374151); text-decoration: none; font-size: 14px;
    }
    .mnav-sheet-list a:active { background: var(--mnav-active, rgba(0,0,0,.04)); }
    .mnav-sheet-list .mnav-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--mnav-accent, #4f46e5); flex: 0 0 auto; }

    /* ---- Buscador ---- */
    .mnav-search-btn {
        position: fixed; right: 12px; bottom: calc(84px + env(safe-area-inset-bottom)); z-index: 1035;
        width: 44px; height: 44px; border-radius: 50%; border: none;
        background: var(--mnav-accent, #4f46e5); color: #fff;
        box-shadow: 0 4px 14px rgba(0,0,0,.25); display: flex; align-items: center; justify-content: center;
    }
    .mnav-search { position: fixed; inset: 0; z-index: 1050; background: var(--mnav-bg, #fff); display: flex; flex-direction: column; }
    .mnav-search[hidden] { display: none; }
    .mnav-search-bar { display: flex; align-items: center; gap: 8px; padding: 12px 14px calc(12px + env(safe-area-inset-top)); border-bottom: 1px solid var(--mnav-border, #eef0f3); }
    .mnav-search-bar input { flex: 1; border: 1px solid var(--mnav-border, #d1d5db); border-radius: 10px; padding: 10px 12px; font-size: 16px; background: transparent; color: var(--mnav-text, #111827); }
    .mnav-search-bar button { border: none; background: transparent; font-size: 26px; line-height: 1; color: var(--mnav-text, #6b7280); }
    .mnav-search-list { list-style: none; margin: 0; padding: 4px 0; overflow-y: auto; flex: 1; }
    .mnav-search-list a { display: flex; flex-direction: column; padding: 11px 16px; text-decoration: none; color: var(--mnav-text, #374151); }
    .mnav-search-list a:active { background: var(--mnav-active, rgba(0,0,0,.04)); }
    .mnav-search-list .s-mod { font-size: 11px; opacity: .6; }
    .mnav-search-list .s-lbl { font-size: 15px; }
    .mnav-search-empty { padding: 20px 16px; opacity: .6; font-size: 14px; }

    /* ---- Tema oscuro ---- */
    body[data-layout-mode="dark"] .mnav-rail,
    body[data-layout-mode="dark"] .mnav-sheet,
    body[data-layout-mode="dark"] .mnav-search {
        --mnav-bg: #2a3042; --mnav-border: #3b4256; --mnav-text: #cbd2e0;
        --mnav-active: rgba(255,255,255,.06);
    }
}
</style>

<script>
/* Motor espejo del sidebar → riel móvil. Defensivo y una-sola-vez. */
(function () {
    "use strict";
    var built = false;
    var searchIndex = []; // {mod, lbl, href} — poblado durante build(), alimenta el buscador

    /* Paleta ÚNICA (placeholder — reemplazar por el MODS del mockup). Clave = label
       normalizado (minúsculas, sin acentos). Fallback determinista por hash si falta. */
    var MNAV_COLORS = {
        "dashboard": "#4f46e5", "planes": "#0891b2", "clientes potenciales": "#db2777",
        "clientes": "#2563eb", "gestion de red": "#7c3aed", "finanzas": "#059669",
        "inventario": "#d97706", "olts": "#0d9488", "mapas": "#16a34a",
        "cobranza": "#dc2626", "megafamilia": "#9333ea", "scheduling": "#ea580c",
        "embajadores": "#ca8a04", "flotas": "#0284c7", "talento": "#6366f1",
        "marketing": "#e11d48", "whatsapp": "#16a34a", "voip": "#7c3aed",
        "portal de pago": "#0891b2", "administracion": "#475569",
        "configuracion": "#64748b", "desarrollador": "#ff8c00"
    };

    function norm(s) {
        return (s || "").toString().trim().toLowerCase()
            .normalize("NFD").replace(/[̀-ͯ]/g, "");
    }
    function colorFor(label) {
        var k = norm(label);
        if (MNAV_COLORS[k]) return MNAV_COLORS[k];
        var h = 0; for (var i = 0; i < k.length; i++) { h = (h * 31 + k.charCodeAt(i)) % 360; }
        return "hsl(" + h + ",55%,45%)"; // fallback saturado
    }
    function isReal(href) { return href && href !== "#" && href.indexOf("javascript:") !== 0; }

    function build() {
        if (built) return;
        var menu = document.getElementById("side-menu");
        var rail = document.getElementById("mnav-rail");
        if (!menu || !rail) return; // degradación con gracia: sin sidebar → sin riel

        var lis = menu.querySelectorAll(":scope > li");
        var count = 0;
        lis.forEach(function (li) {
            if (li.classList.contains("menu-title")) return;       // saltar títulos de sección
            var a = li.querySelector(":scope > a");
            if (!a) return;
            var span = a.querySelector("span");
            var label = span ? span.textContent.trim() : a.textContent.trim();
            if (!label) return;

            var accent = colorFor(label);
            var iconNode = a.querySelector("svg, i");              // feather ya renderizado (svg) o <i>
            var subLinks = [];
            li.querySelectorAll(":scope > ul a").forEach(function (c) {
                var h = c.getAttribute("href");
                if (isReal(h)) {
                    var cs = c.querySelector("span");
                    subLinks.push({ label: (cs ? cs.textContent : c.textContent).trim(), href: h });
                }
            });

            var href = a.getAttribute("href");
            var el;
            if (isReal(href) && subLinks.length === 0) {
                el = document.createElement("a");                  // navega directo
                el.href = href;
            } else {
                el = document.createElement("button");             // abre hoja de submenú
                el.type = "button";
                el.addEventListener("click", function () { openSheet(label, accent, subLinks, href); });
            }
            // Índice de búsqueda (mismo espejo): directo → 1 entrada; contenedor → sus hijos (+ ruta propia).
            if (isReal(href) && subLinks.length === 0) {
                searchIndex.push({ mod: label, lbl: label, href: href });
            } else {
                if (isReal(href)) searchIndex.push({ mod: label, lbl: "Abrir " + label, href: href });
                subLinks.forEach(function (c) { searchIndex.push({ mod: label, lbl: c.label, href: c.href }); });
            }

            el.className = "mnav-item";
            el.style.setProperty("--mnav-accent", accent);

            var ico = document.createElement("span");
            ico.className = "mnav-ico"; ico.style.background = accent;
            if (iconNode) ico.appendChild(iconNode.cloneNode(true));
            var lbl = document.createElement("span");
            lbl.className = "mnav-lbl"; lbl.textContent = label;
            el.appendChild(ico); el.appendChild(lbl);
            rail.appendChild(el);
            count++;
        });

        built = count > 0;
        document.getElementById("mobile-nav-root").setAttribute("aria-hidden", built ? "false" : "true");
    }

    /* ---- Hoja de submenú ---- */
    var sheet = null, backdrop = null;
    function openSheet(title, accent, links, ownHref) {
        sheet = sheet || document.getElementById("mnav-sheet");
        backdrop = backdrop || document.getElementById("mnav-sheet-backdrop");
        document.getElementById("mnav-sheet-title").textContent = title;
        var ul = document.getElementById("mnav-sheet-list");
        ul.innerHTML = "";
        // Si el propio módulo tiene ruta directa además de hijos, se añade como primera opción.
        var items = [];
        if (isReal(ownHref)) items.push({ label: "Abrir " + title, href: ownHref });
        items = items.concat(links);
        items.forEach(function (c) {
            var li = document.createElement("li");
            var a = document.createElement("a"); a.href = c.href;
            var dot = document.createElement("span"); dot.className = "mnav-dot"; dot.style.background = accent;
            var t = document.createElement("span"); t.textContent = c.label;
            a.appendChild(dot); a.appendChild(t); li.appendChild(a); ul.appendChild(li);
        });
        sheet.hidden = false; backdrop.hidden = false;
    }
    function closeSheet() { if (sheet) sheet.hidden = true; if (backdrop) backdrop.hidden = true; }
    document.addEventListener("click", function (e) {
        if (e.target && (e.target.id === "mnav-sheet-backdrop" || e.target.id === "mnav-sheet-close")) closeSheet();
    });

    /* ---- Buscador (sobre searchIndex, mismo espejo) ---- */
    function renderSearch(q) {
        var list = document.getElementById("mnav-search-list");
        if (!list) return;
        list.innerHTML = "";
        var nq = norm(q);
        var res = nq ? searchIndex.filter(function (e) { return norm(e.mod + " " + e.lbl).indexOf(nq) !== -1; }) : searchIndex;
        if (!res.length) {
            var empty = document.createElement("li");
            empty.className = "mnav-search-empty"; empty.textContent = "Sin resultados.";
            list.appendChild(empty); return;
        }
        res.slice(0, 60).forEach(function (e) {
            var li = document.createElement("li");
            var a = document.createElement("a"); a.href = e.href;
            var m = document.createElement("span"); m.className = "s-mod"; m.textContent = e.mod;
            var l = document.createElement("span"); l.className = "s-lbl"; l.textContent = e.lbl;
            a.appendChild(m); a.appendChild(l); li.appendChild(a); list.appendChild(li);
        });
    }
    function openSearch() {
        var ov = document.getElementById("mnav-search"); if (!ov) return;
        ov.hidden = false;
        var inp = document.getElementById("mnav-search-input");
        if (inp) { inp.value = ""; renderSearch(""); setTimeout(function () { inp.focus(); }, 50); }
    }
    function closeSearch() { var ov = document.getElementById("mnav-search"); if (ov) ov.hidden = true; }
    document.addEventListener("click", function (e) {
        if (!e.target || !e.target.closest) return;
        if (e.target.closest("#mnav-search-btn")) openSearch();
        if (e.target.id === "mnav-search-close") closeSearch();
    });
    document.addEventListener("input", function (e) {
        if (e.target && e.target.id === "mnav-search-input") renderSearch(e.target.value);
    });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") { closeSearch(); closeSheet(); } });

    /* Construir una vez, después de que feather haya reemplazado los íconos. */
    function boot() { requestAnimationFrame(function () { setTimeout(build, 300); }); }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else { boot(); }
})();
</script>
