# Item #859 — `permissions:sync-roles --manifests` en dev tras el fix de #858 (verificación)

**Fecha:** 2026-09-01 · **Worker:** wt-2

## Contexto

Sub-item de seguimiento de #852. Su dependencia directa (#858 — fix de la columna
`permissions.description` + backfill + extensión del glob de `PermissionSyncService::syncFromModuleManifests()`
a `Core/*/module.json`) ya estaba **mergeada a main** (`3ad8dfdf`) antes de arrancar esta vuelta. Al
crear la rama de #859 desde `main` (`circuito:rama`), el fix llegó automáticamente:

```
$manifests = array_merge(
    glob(base_path('app/Modules/Addons/*/module.json')) ?: [],
    glob(base_path('app/Modules/Core/*/module.json')) ?: []
);
```

y la tabla `permissions` ya trae la columna `description`.

## Snapshot ANTES de correr el comando

| Métrica | Valor |
|---|---|
| `permissions` (total) | 740 |
| `role_has_permissions` (pares rol↔permiso) | 3765 |
| `model_has_permissions` (permisos directos a usuarios) | 4035 |

## Comando ejecutado

```
php artisan permissions:sync-roles --manifests
```

Salida:

```
Leyendo module.json de todos los addons…
  Permisos nuevos creados: 0
  Permisos sincronizados: 446

Sincronizando permisos faltantes a roles base…
+---------------------------+--------------------+
| Rol / Categoría           | Permisos asignados |
+---------------------------+--------------------+
| super-administrator       | 2                  |
| DESARROLLADOR             | 2                  |
| Otros roles (solo .view)  | 0                  |
| TOTAL nuevas asignaciones | 4                  |
+---------------------------+--------------------+
✓ Sincronización completada.

Re-aplicando matriz de revocación de permisos por rol…
  (12 roles evaluados, 0 revocados en todos — sin cambios)
Caché de permisos Spatie limpiada.
```

## Snapshot DESPUÉS + diff verificado

| Métrica | Antes | Después | Δ |
|---|---|---|---|
| `permissions` (total) | 740 | 740 | 0 (coincide con "Permisos nuevos creados: 0" — con el glob de Core ya extendido por #858, no quedaban permisos declarados en manifest sin crear) |
| `role_has_permissions` | 3765 | 3769 | **+4** (coincide con "TOTAL nuevas asignaciones: 4") |
| `model_has_permissions` | 4035 | 4035 | 0 (el comando no toca asignaciones directas a usuario) |

**Diff exacto de `role_has_permissions`** (comparación por conjunto de pares `role_id:permission_id`,
antes vs después):

- **Eliminados:** ninguno (0 filas presentes antes que hayan desaparecido después).
- **Agregados:** 4 filas —

  | role_id | rol | permission_id | permiso |
  |---|---|---|---|
  | 1 | super-administrator | 792 | `test_item842_probe_b` |
  | 1 | super-administrator | 793 | `test_item842_probe_c` |
  | 10 | DESARROLLADOR | 792 | `test_item842_probe_b` |
  | 10 | DESARROLLADOR | 793 | `test_item842_probe_c` |

Son permisos de prueba dejados por el item #842 (verificación anterior del propio mecanismo de sync)
que aún no estaban asignados a los dos roles de acceso total; el comando cerró ese hueco correctamente
— es exactamente su función (`syncAllPermissionsToBaseRoles`: super-administrator/DESARROLLADOR deben
tener TODOS los permisos existentes).

## Conclusión

El diff es **puramente aditivo**: 0 permisos borrados, 0 asignaciones de rol revocadas, 0 filas de
`model_has_permissions` tocadas. No aparecieron cambios de permisos "reales" fuera de lo esperado
(las 4 adiciones son consistentes con la regla de negocio del propio comando). Corresponde a la
**Opción 1** ya recomendada/aprobada en el item ("si el diff es solo textual/aditivo, integrar") —
no hay nada que escalar.

Sin cambio de código de negocio (el comando y el servicio ya estaban completos desde #858); esta
vuelta solo ejecuta la verificación operativa que pedía el item y deja el resultado documentado.
