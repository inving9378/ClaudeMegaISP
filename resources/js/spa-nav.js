// spa-nav.js — Interceptor de navegación SPA (Paso 3B-3)
//
// ROLLBACK: cambia SPA_ENABLED a false para volver a recarga completa al instante.

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

// ─── overlay mientras carga ───────────────────────────────────────────────────

function dimContainer(container) {
    container.style.transition = 'opacity 100ms';
    container.style.opacity = '0.55';
    container.style.pointerEvents = 'none';
}

function undimContainer(container) {
    container.style.transition = '';
    container.style.opacity = '';
    container.style.pointerEvents = '';
}

// ─── core navigation ─────────────────────────────────────────────────────────

async function spaNavigate(url, pushState) {
    const container = document.querySelector('#init-vue');
    if (!container) {
        window.location.href = url;
        return;
    }

    const loader = showLoader();
    dimContainer(container);

    try {
        // 1. Fetch PRIMERO — el contenido viejo permanece visible mientras carga
        const resp = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        const html = await resp.text();

        // 2. Parsear y extraer contenido
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const newContent = doc.querySelector('#init-vue');
        if (!newContent) throw new Error('No #init-vue en respuesta del servidor');

        // 3. Actualizar título de pestaña
        const newTitle = doc.querySelector('title');
        if (newTitle) document.title = newTitle.textContent;

        // 4. Desmontar (solo si el fetch fue exitoso — limpia portales Teleport Quasar)
        if (window.__megaVueApp) {
            window.__megaVueApp.unmount();
            window.__megaVueApp = null;
        }

        // 5. Flash a opacidad 0 → swap → fade in
        container.style.transition = 'opacity 60ms';
        container.style.opacity = '0';
        container.innerHTML = newContent.innerHTML;

        // 6. Re-montar app de contenido
        if (typeof window.createMainApp === 'function') {
            window.createMainApp();
        }

        // 7. Fade in del contenido nuevo
        requestAnimationFrame(() => {
            container.style.transition = 'opacity 120ms';
            container.style.opacity = '1';
            container.style.pointerEvents = '';
        });

        // 8. Actualizar link activo en sidebar
        if (typeof window.__updateSidebarActiveLink === 'function') {
            window.__updateSidebarActiveLink(url);
        }

        // 9. Push history (solo en navegación hacia adelante)
        if (pushState) {
            history.pushState({ spa: true, url }, '', url);
        }

        // 10. Reset de scroll
        window.scrollTo(0, 0);

    } catch (err) {
        undimContainer(container);
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
