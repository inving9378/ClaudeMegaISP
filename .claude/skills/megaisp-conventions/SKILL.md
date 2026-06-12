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
- SIEMPRE cerrar con warm-up: `php artisan view:cache && php artisan config:cache`.
- PROHIBIDO `php artisan migrate:fresh` (BD productiva). Solo `migrate` incremental. Migraciones aditivas únicamente.
- No compilar APKs ni builds pesados en el servidor (disco cerca de capacidad).

## Autenticación y permisos
- Passwords con `base64_encode`, NO bcrypt. Campo de login: `login_user`, NO `email`.
- `@can()` en Blade NO funciona. Usar `@if(auth()->user()->can('permiso'))`.
- Permisos nuevos pasan por `PermissionSyncService`. Regla: `super-administrator` y `DESARROLLADOR` reciben TODOS; los demás roles solo `.view` automáticamente. Sync: `php artisan permissions:sync-roles`.
- `keep_data:true` en ciclo de vida de módulos NO debe eliminar permisos Spatie.

## Datos legacy
- `payment_date` y `document_date` son VARCHAR DD/MM/YYYY. Toda query usa `COALESCE(STR_TO_DATE(col, '%d/%m/%Y'), ...)`. Nunca comparar como string.
- Excluir tickets archivados en KPIs financieros.
- Tareas unificadas viven en tabla `tasks`; no asumir que todo ID es de `talento_work_orders`.
- Backups MySQL en `/var/backups/mysql/` (02:00 AM, retención 14 días). No crear esquemas de backup paralelos.
