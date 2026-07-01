<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

/**
 * Portal Técnico Web — shell PWA del módulo Talento.
 *
 * Versión web (PWA) de la app de campo. Reutiliza la MISMA capa de servicios
 * que /talento/api (sesión Medussa en vez de token Sanctum). Este controlador
 * solo sirve el shell + assets PWA + preferencia de tema; las pantallas
 * (Mi día, OT+evidencia, Proyectos) se cablean en los sub-pasos 2–4 delegando
 * en los servicios existentes.
 */
class PortalTecnicoController extends Controller
{
    /** Cache-busting de los assets estáticos del portal (subir al cambiar app.js/portal.css). */
    private const ASSET_VER = '1';

    /** Shell del portal (SPA Quasar de una sola página con nav inferior). */
    public function index(Request $request)
    {
        $colaborador = $this->resolveColaborador($request);
        $theme       = $this->currentTheme($request);

        return view('addon-talento::portal.shell', [
            'assetVer'    => self::ASSET_VER,
            'theme'       => $theme,
            'colaborador' => $colaborador ? [
                'id'     => $colaborador->id,
                'nombre' => trim(($colaborador->nombre ?? '') . ' ' . ($colaborador->apellido ?? '')),
                'tipo'   => $colaborador->tipo ?? $colaborador->role_type ?? 'technician',
                'email'  => $colaborador->user?->email,
            ] : null,
        ]);
    }

    /** Manifest PWA (servido dinámico para fijar scope/start_url del portal). */
    public function manifest()
    {
        $manifest = [
            'name'             => 'Talento Meganet — Campo',
            'short_name'       => 'Talento Campo',
            'description'      => 'Portal técnico de campo: check-in, órdenes de trabajo, evidencia y proyectos.',
            'start_url'        => '/talento/portal',
            'scope'            => '/talento/portal/',
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => '#0d9488',
            'theme_color'      => '#0d9488',
            'lang'             => 'es',
            'icons'            => [
                [
                    'src'   => '/talento-portal/icons/icon-192.png',
                    'sizes' => '192x192',
                    'type'  => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'   => '/talento-portal/icons/icon-512.png',
                    'sizes' => '512x512',
                    'type'  => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'   => '/talento-portal/icons/icon-maskable-512.png',
                    'sizes' => '512x512',
                    'type'  => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ];

        return Response::make(json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
        ]);
    }

    /**
     * Service worker del app-shell. Servido en /talento/portal/sw.js para que
     * el scope efectivo sea /talento/portal/. El outbox offline (IndexedDB)
     * llega en el Sub-paso 5; por ahora solo precachea el shell y sirve
     * network-first con fallback a caché para navegaciones sin señal.
     */
    public function serviceWorker()
    {
        $ver = self::ASSET_VER;
        $js = <<<JS
// Talento Portal Técnico — Service Worker (app-shell). Generado por PortalTecnicoController.
const CACHE = 'talento-portal-shell-v{$ver}';
const SHELL = [
  '/talento/portal',
  '/talento-portal/portal.css?v={$ver}',
  '/talento-portal/app.js?v={$ver}',
  '/plugins/quasar/js/vue.global.prod.js',
  '/plugins/quasar/js/quasar.umd.prod.js',
  '/plugins/quasar/css/quasar.prod.css',
  '/plugins/quasar/css/material-icons.css',
  '/talento-portal/icons/icon-192.png',
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return; // nunca cachear POST/PUT (check-in, evidencia)

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return; // solo mismo origen

  // No cachear llamadas de datos del portal ni el manifest/sw
  if (url.pathname.startsWith('/talento/portal/api')) return;

  // Navegaciones: network-first, fallback al shell cacheado (offline).
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req).catch(() => caches.match('/talento/portal').then((r) => r || caches.match(req)))
    );
    return;
  }

  // Assets estáticos del shell: cache-first con revalidación de red.
  e.respondWith(
    caches.match(req).then((cached) => {
      const network = fetch(req).then((res) => {
        if (res && res.status === 200 && res.type === 'basic') {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy));
        }
        return res;
      }).catch(() => cached);
      return cached || network;
    })
  );
});
JS;

        return Response::make($js, 200, [
            'Content-Type'            => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed'  => '/talento/portal/',
            'Cache-Control'           => 'no-cache',
        ]);
    }

    /** Persiste el tema (light/dark) por usuario. */
    public function saveTheme(Request $request)
    {
        $data = $request->validate([
            'theme' => 'required|in:light,dark',
        ]);

        DB::table('talento_portal_preferences')->updateOrInsert(
            ['user_id' => $request->user()->id],
            ['theme' => $data['theme'], 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['ok' => true, 'theme' => $data['theme']]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function resolveColaborador(Request $request): ?TalentoColaborador
    {
        return TalentoColaborador::with('user')
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();
    }

    private function currentTheme(Request $request): string
    {
        $theme = DB::table('talento_portal_preferences')
            ->where('user_id', $request->user()->id)
            ->value('theme');

        return in_array($theme, ['light', 'dark'], true) ? $theme : 'light';
    }
}
