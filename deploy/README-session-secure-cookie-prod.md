# `SESSION_SECURE_COOKIE=true` en producción (.198) — item roadmap #157

## Qué es y por qué está parqueado

Endurece la cookie de sesión para que el navegador **solo la mande por HTTPS**
(`config/session.php:171` → `'secure' => env('SESSION_SECURE_COOKIE')`). Es
higiene de sesión, **no** el fix de ningún bug activo (el 419 reportado es un
artefacto de cookie vieja en el navegador, no está causado por este flag).

Toca el `.env` de la máquina de **producción** (.198) → frontera dura del
circuito: el circuito de dev **nunca** toca `.198` (ver CLAUDE.md, sección
"CANDADOS DUROS" y la entrada de este mismo item en la tabla "Portal Cliente
— estado"). Por eso el propio brief de decisión del item (opción elegida,
2026-08-28) es la **Opción 1: Irving lo aplica manualmente** en el box de
prod — este documento es justo esa receta, para que sea copiar/pegar cuando
él decida ejecutarla, siguiendo el mismo patrón que `README-portal-dev.md`
(item #144).

## Pre-requisito — confirmar HTTPS universal en prod

Antes de activar el flag, confirmar que **TODO** el tráfico de `.198` entra
por HTTPS — sin rutas/redirects HTTP internos que dependan de la cookie
(crons, webhooks locales, health-checks, IPs directas). Si algo legítimo
sigue entrando por HTTP, esa sesión se rompe en cuanto `secure=true` esté
activo (la cookie deja de viajar).

Esto normalmente coincide con el corte en que se publique `portal.meganet.mx`
con certbot (ver `deploy/README-portal-dev.md`, sección "Camino a
producción") — pero **no depende de ese subdominio en particular**: aplica
en cuanto el dominio/IP principal de prod sirva 100% por HTTPS.

## Paso 1 — activar el flag (en el box de prod, `.198`)

```bash
# En el .env de PRODUCCIÓN — nunca en el .env de dev
SESSION_SECURE_COOKIE=true
```

## Paso 2 — warm-up (NUNCA `config:cache` a secas)

```bash
cd /var/www/megaisp   # ruta real en el box de prod
php artisan config:clear
php artisan route:clear
php artisan queue:restart
```

Si en algún momento se usa `config:cache` en prod, seguir el candado del
propio CLAUDE.md (`config:auditar-env && php artisan config:cache`) — el
`&&` es lo que evita cachear un `.env` a medio aplicar.

## Paso 3 — probar login

Iniciar sesión en prod (admin o portal cliente) y confirmar que la cookie de
sesión persiste entre requests (no hay logout inesperado / 419 nuevo).
Revisar DevTools → Application → Cookies → el atributo `Secure` debe
aparecer en la cookie de sesión.

## Rollback

```bash
# En el .env de producción
SESSION_SECURE_COOKIE=false
```

Seguido del mismo warm-up del Paso 2. Reversible de inmediato — no hay
migración ni dato que revertir, solo el flag.

## Alcance de este documento

Esto es **solo la receta**; ningún paso de este archivo se ejecuta desde el
circuito de dev. Lo aplica Irving a mano en `.198` cuando decida que el
pre-requisito de HTTPS universal ya se cumple.
