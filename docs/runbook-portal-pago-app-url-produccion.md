# Runbook — Fijar `APP_URL` al dominio público antes de activar Portal de Pago (item roadmap #162)

**Ejecutor: Irving, manualmente en el box de PRODUCCIÓN.** NO se ejecuta desde el circuito ni
desde ningún comando de MegaISP — decisión explícita de Irving al aprobar el item #162 (ver
`comentarios_claude` del item: preguntas q1/q2/q3, las tres resueltas a favor de la opción manual:
"Escalar a Irving y NO ejecutar desde el circuito", "Confirmar con Irving el dominio exacto antes
de tocar nada", "Checklist mínimo" de verificación). El circuito **no tiene acceso** al `.env` de
producción — vive en otra máquina, fuera de alcance de este worktree. Este documento es la guía
paso a paso para que Irving lo aplique.

## 1. El bug (confirmado en el código)

`config('app.url')` se resuelve de `env('APP_URL')` (`config/app.php:68`, default `http://localhost`
si falta). El módulo Portal de Pago (`app/Modules/Addons/PortalPago/`) construye la liga pública de
cada factura con el helper `url()`, que arma la URL absoluta a partir de ese mismo `APP_URL`:

- `Controllers/Admin/LinksController.php:140` y `:159` — liga mostrada/lista en la pantalla admin
  `/pagos/links` (`url('/f/' . $link->token)`).
- `Commands/EnviarRecurrentesCommand.php:87` — liga logueada por la recurrencia asistida (hoy solo
  se escribe a `laravel.log`; el envío real por WhatsApp/SMS lo hace el operador copiando esta URL).

Si `APP_URL` en el `.env` de producción sigue en su default (`http://localhost` o el host interno
del servidor), toda liga que el equipo de cobranza copie y mande al cliente por WhatsApp sale como
`http://localhost/f/{token}` — inalcanzable fuera de la red interna. Rompe el flujo completo de
cobro por Portal de Pago antes de que llegue a producción.

## 2. Paso 0 — Confirmar el dominio público exacto

**No asumir el dominio.** El item trae `https://portal.meganett.com.mx` como ejemplo en su
descripción original, pero la decisión de Irving (pregunta q2 del item) fue confirmarlo
explícitamente antes de tocar nada — puede no ser el dominio final, y un valor equivocado rompe
callbacks/URLs firmadas igual que el bug que se está corrigiendo.

Confirmar con Irving el dominio público real donde resuelve producción (`.198`) antes de seguir.

## 3. Paso 1 — Editar el `.env` de producción

En el box de producción (`.198`, **nunca** desde el circuito ni desde dev):

```bash
# Backup rápido antes de tocar
cp .env .env.bak-app-url-$(date +%Y%m%d%H%M)

# Editar a mano (o sed acotado a esta clave):
# APP_URL=https://<dominio-publico-confirmado>
```

No usar `sed` a ciegas sobre todo el archivo — editar solo la línea `APP_URL=`.

## 4. Paso 2 — Warm-up (sin `config:cache`)

```bash
php artisan config:clear
php artisan route:clear
php artisan queue:restart
```

⚠️ **No correr `config:cache`** a menos que ya se haya auditado que ningún `env()` de runtime
queda fuera de `config/*.php` (ver la regla `config:auditar-env` documentada en `CLAUDE.md` de
dev, misma lógica aplica en prod: cachear sin auditar antes puede enmascarar otras claves).

## 5. Paso 3 — Checklist de verificación (antes de dar por cerrado)

Esto fue lo que Irving pidió explícitamente en la pregunta q3 del item:

1. [ ] Generar una liga de pago de prueba desde `/pagos/links` (admin) y confirmar que la URL
       mostrada empieza con el dominio público correcto, no `localhost` ni la IP interna.
2. [ ] Abrir esa liga (`/f/{token}`) desde **fuera** de la red interna (o al menos confirmar que
       el host resuelve públicamente) y verificar que carga la pantalla de pago.
3. [ ] Si hay callbacks/webhooks que dependen de `APP_URL` en otros módulos que compartan el mismo
       dominio (ej. OpenPay del Portal Cliente, ver `docs/config-critica-produccion.md`), revisar
       que sigan resolviendo — el cambio de `APP_URL` es global al proceso, no solo de Portal de
       Pago.
4. [ ] Confirmar que el warm-up del paso 2 corrió sin dejar `config:cache` puesto por error.

## 6. Rollback

Si algo sale mal:

```bash
cp .env.bak-app-url-<timestamp> .env
php artisan config:clear && php artisan route:clear && php artisan queue:restart
```

## 7. Referencia

- Item roadmap #162 (`modulo=PortalPago`, `nivel_riesgo=C`) — historial completo de la decisión en
  `comentarios_claude` del item.
- `docs/modulos/portalpago.md` — arquitectura del módulo.
- `docs/config-critica-produccion.md` — mismo patrón de doc para otros requisitos de `.env`/config
  de producción que no se pueden perder.

---
_Doc generada por el Circuito CC (worker on-box, item #162). Read-only sobre producción — no
ejecuta ningún cambio, solo documenta el paso a paso para que Irving lo aplique manualmente._
