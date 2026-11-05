{{-- Flotas Portal=Panel F2c (#659): las pantallas Vue de Flotas traen colores claros fijos; aquí se adaptan al
     modo oscuro del portal. Todo prefijado con #portal-flotas-app (no hay reglas globales ni .modal-backdrop). --}}
<style>
#portal-flotas-app { color: var(--text); }
#portal-flotas-app a:not(.btn) { text-decoration: none; }
html.dark #portal-flotas-app .flt-card,
html.dark #portal-flotas-app .flt-header,
html.dark #portal-flotas-app .card,
html.dark #portal-flotas-app .modal-content,
html.dark #portal-flotas-app .list-group-item,
html.dark #portal-flotas-app .dropdown-menu { background: var(--card); color: var(--text); border-color: var(--border); }
html.dark #portal-flotas-app .flt-section-title,
html.dark #portal-flotas-app .flt-header-title,
html.dark #portal-flotas-app h1, html.dark #portal-flotas-app h2, html.dark #portal-flotas-app h3,
html.dark #portal-flotas-app h4, html.dark #portal-flotas-app h5, html.dark #portal-flotas-app h6 { color: var(--text); }
html.dark #portal-flotas-app .text-muted,
html.dark #portal-flotas-app .flt-header-quick,
html.dark #portal-flotas-app .small { color: var(--text-muted) !important; }
html.dark #portal-flotas-app .form-control,
html.dark #portal-flotas-app .form-select { background: var(--surface); color: var(--text); border-color: var(--border); }
html.dark #portal-flotas-app .table { color: var(--text); --bs-table-bg: transparent; --bs-table-color: var(--text); border-color: var(--border); }
html.dark #portal-flotas-app .nav-tabs { border-color: var(--border); }
html.dark #portal-flotas-app .nav-tabs .nav-link { color: var(--text-muted); }
html.dark #portal-flotas-app .nav-tabs .nav-link.active { background: var(--card); color: var(--text); border-color: var(--border); }
</style>
