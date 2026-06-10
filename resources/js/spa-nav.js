// spa-nav.js — Interceptor de navegación SPA (Paso 3B-2)
//
// ROLLBACK: cambia SPA_ENABLED a false para volver a recarga completa al instante.
// GATE: solo intercepta UNA ruta de prueba; todo lo demás sigue con recarga normal.

const SPA_ENABLED = true;

const SPA_GATE = [
    '/red/router/listar',
];

// ─── helpers ─────────────────────────────────────────────────────────────────

function normPath(url) {
    try {
        return new URL(url, window.location.origin).pathname;
    } catch (_) {
        return null;
    }
}

function isGated(url) {
    const path = normPath(url);
    if (!path) return false;
    return SPA_GATE.includes(path);
}

function showLoader() {
    let el = document.getElementById('__spa-loader');
    if (!el) {
        el = document.createElement('div');
        el.id = '__spa-loader';
        el.style.cssText = [
            'position:fixed', 'top:0', 'left:0',
            'width:0', 'height:3px',
            'background:#0d6efd', 'z-index:99999',
            'transition:width 250ms ease,opacity 400ms ease',
            'pointer-events:none',
        ].join(';');
        document.body.appendChild(el);
    }
    el.style.opacity = '1';
    el.style.width = '0';
    requestAnimationFrame(() => { el.style.width = '70%'; });
    return el;
}

function finishLoader(el) {
    if (!el) return;
    el.style.width = '100%';
    setTimeout(() => {
        el.style.opacity = '0';
        setTimeout(() => { el.style.width = '0'; }, 400);
    }, 200);
}

// ─── core navigation ─────────────────────────────────────────────────────────

async function spaNavigate(url, pushState) {
    const container = document.querySelector('#init-vue');
    if (!container) {
        window.location.href = url;
        return;
    }

    const loader = showLoader();

    try {
        // 1. Desmontar app de contenido (limpia portales Teleport de Quasar)
        if (window.__megaVueApp) {
            window.__megaVueApp.unmount();
            window.__megaVueApp = null;
        }

        // 2. Fetch de la nueva página
        const resp = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        const html = await resp.text();

        // 3. Parsear y extraer contenido
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const newContent = doc.querySelector('#init-vue');
        if (!newContent) throw new Error('No #init-vue en respuesta del servidor');

        // 4. Actualizar título de pestaña
        const newTitle = doc.querySelector('title');
        if (newTitle) document.title = newTitle.textContent;

        // 5. Swap del contenido
        container.innerHTML = newContent.innerHTML;

        // 6. Re-montar app de contenido
        if (typeof window.createMainApp === 'function') {
            window.createMainApp();
        }

        // 7. Actualizar link activo en sidebar
        if (typeof window.__updateSidebarActiveLink === 'function') {
            window.__updateSidebarActiveLink(url);
        }

        // 8. Push history (solo en navegación hacia adelante)
        if (pushState) {
            history.pushState({ spa: true, url }, '', url);
        }

        // 9. Reset de scroll
        window.scrollTo(0, 0);

    } catch (err) {
        console.warn('[spa-nav] fallback a recarga completa:', err.message);
        window.location.href = url;
        return;
    } finally {
        finishLoader(loader);
    }
}

// ─── event listeners ─────────────────────────────────────────────────────────

function handleClick(e) {
    if (!SPA_ENABLED) return;

    const link = e.target.closest('a[href]');
    if (!link) return;

    // Saltar: data-spa-skip, _blank, download, origen externo
    if ('spaSkip' in link.dataset) return;
    if (link.target === '_blank') return;
    if (link.hasAttribute('download')) return;
    try {
        if (new URL(link.href).origin !== window.location.origin) return;
    } catch (_) { return; }

    if (!isGated(link.href)) return;

    e.preventDefault();
    spaNavigate(link.href, true);
}

function handlePopState() {
    if (!SPA_ENABLED) return;
    const url = window.location.href;
    if (!isGated(url)) return;
    spaNavigate(url, false);
}

document.addEventListener('click', handleClick);
window.addEventListener('popstate', handlePopState);

// Expuesto para llamadas manuales / debug en consola
window.__spaNavigate = spaNavigate;
