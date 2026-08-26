---
name: megaisp-conventions
description: Reglas no negociables del proyecto MegaISP (Laravel 10 / PHP 8.2 / Vue 3 / Quasar UMD). Usar SIEMPRE en cualquier tarea de código, migración, commit, permisos, Blade o frontend en este repositorio, aunque no se pida explícitamente. Cubre git, caché, autenticación legacy, permisos Spatie, fechas legacy y nomenclatura.
---

# Convenciones MegaISP

Violarlas ha causado incidentes reales (exposición de credenciales, pérdida de permisos, bugs de fechas).

## Nomenclatura
- **MegaISP** = la plataforma completa. **Medussa** = SOLO el módulo de facturación/documentos. **Meganet** = la empresa ISP.
- PROHIBIDO usar la palabra "piramidal" en el módulo Embajadores. Usar "cascada" o "multinivel de activaciones".

## Git
- NUNCA `git add -A` ni `git add .` (incidente previo de exposición de credenciales). Siempre `git add` selectivo, archivo por archivo.
- Un commit por sub-paso. Mensajes descriptivos en español.
- Nunca commitear `.env`, credenciales ni archivos con secretos.

## Caché y deploy
- Tras cambios en Blade/config: `php artisan view:clear && php artisan config:clear && php artisan route:clear`.
- `php artisan config:cache` (y `php artisan optimize`, que lo incluye) SOLO detrás del candado `php artisan config:auditar-env && php artisan config:cache` — nunca suelto. El auditor (item #790) escanea llamadas reales a `env()` fuera de `config/*.php` y devuelve exit 1 si queda alguna; si falla, hay que migrar esa llamada a `config/*.php` antes de cachear, no forzar el cache. Warm-up de cierre completo: `php artisan config:clear && php artisan route:clear && php artisan queue:restart && php artisan config:auditar-env && php artisan config:cache`. `env()` sigue siendo correcto dentro de `config/*.php` y en migraciones/seeders manuales.
- PROHIBIDO `php artisan migrate:fresh` (BD productiva). Solo `migrate` incremental. Migraciones aditivas únicamente.
- En dev, `php artisan migrate` (item #534) exige que cada migración pendiente esté commiteada y en una rama con ruta a `main` — si no, aborta. Escape hatch solo para rollback/debug legítimo: `--force-uncommitted` (queda auditado en `storage/logs/migration-guard.log`).
- Backups automáticos (`backup_db:process`, dailyAt 02:00 en `Kernel.php`) dependen de un cron real de `php artisan schedule:run`. En dev ESE cron NO existe (el único cron activo es `circuito:scheduler`, que no es lo mismo) — el backup diario no ocurre solo; hay que dispararlo a mano (`php artisan backup_db:process`) o con `schedule:run`. En prod, verificar que el cron de `schedule:run` esté instalado antes de asumir que corre.
- No compilar APKs ni builds pesados en el servidor (disco cerca de capacidad).

## Autenticación y permisos
- Passwords en transición `base64_encode` → bcrypt vía `App\Services\Security\PasswordService` (híbrido): `check()` acepta ambos formatos, `make()` siempre escribe bcrypt. Usar SIEMPRE `PasswordService`, nunca comparar/escribir base64 a mano. Campo de login: `login_user`, NO `email`.
- `@can()` en Blade SÍ funciona y se usa ampliamente (sidebar, tablas de acciones, ~40 usos). No asumir que está roto; si un caso puntual falla, verificar primero que el permiso exista/esté sincronizado antes de descartar la directiva.
- Permisos nuevos pasan por `PermissionSyncService`. Regla: `super-administrator` y `DESARROLLADOR` reciben TODOS; los demás roles solo `.view` automáticamente. Sync: `php artisan permissions:sync-roles`.
- `keep_data:true` en ciclo de vida de módulos NO debe eliminar permisos Spatie.

## Aislamiento multi-tenant (módulos de cliente)
- TODO módulo-producto nuevo o migrado al portal del cliente DEBE aislar sus datos con el trait `App\Traits\BelongsToClientTenant` (`scopeForClient(?int $clientId)`) y resolver el cliente actual con `App\Services\Tenant\CurrentClientResolver`. NO escribir filtrado manual por `client_id` en cada controlador.
- El trait es **fail-closed**: si el resolver no resuelve un cliente (clientId null) el scope devuelve CERO resultados. Las filas con la columna tenant en NULL (huérfanas) jamás son visibles para un cliente.
- `$allowNullTenant = true` (NULL = registros internos Meganet visibles para admin) es EXCLUSIVO de módulos internos (Flotas). En módulos de cliente (MegaFamilia, Embajadores) NUNCA activarlo.
- Si la columna tenant no es `client_id`, declararla en el modelo: `protected string $tenantColumn = 'embajador_id';`. El resolver web admin replica exactamente la regla de roles internos (`super-administrator`/`DESARROLLADOR` → null); portal y API resuelven al MISMO `client_id`.

## Datos legacy
- `client_invoices.payment_date` y `client_invoices.document_date` son VARCHAR DD/MM/YYYY (igual `payments.date`). Toda query usa `COALESCE(STR_TO_DATE(col, '%d/%m/%Y'), ...)`. Nunca comparar como string.
- Excluir tickets archivados en KPIs financieros.
- Tareas unificadas viven en tabla `tasks`; no asumir que todo ID es de `talento_work_orders`.
- Backups MySQL en `/var/backups/mysql/` (retención 14 días, `backup_db:process`; ver caveat de cron en "Caché y deploy"). No crear esquemas de backup paralelos.
