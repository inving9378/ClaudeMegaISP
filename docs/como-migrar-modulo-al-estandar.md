# Cómo migrar un módulo al estándar de Medussa

> Referencia del contrato: `contrato-modulo-medussa.md` v0.6  
> Módulo de referencia: `app/Modules/Core/Configuracion/`

---

## Qué hace el estándar

Cada módulo declara **qué aporta** en su `module.json`. El registro central (`ModuleRegistry`) lo lee y construye dinámicamente:

| Área | Dónde aparece |
|---|---|
| `menu` | Sidebar (bloque dinámico de addons) |
| `admin_cards` | `/admin/administracion` |
| `config_sections` | `/admin/configuracion-nueva` (agrupado por `type`) |
| `api_endpoints` | Catálogo API |
| `ai` | Chat IA flotante |
| `client_tab` | Ficha del cliente |
| `service_type` | Catálogo de planes |

---

## Checklist de migración de un módulo

### 1. Actualizar `module.json`

El archivo ya tiene la estructura base (la Fase 0 la agregó a todos). Rellenar los campos reales:

```jsonc
{
    "slug": "addon-mi-modulo",   // kebab-case único, prefijo addon- o core-
    "name": "Mi Módulo",
    "version": "1.0.0",
    "description": "Una línea: qué hace el módulo.",
    "type": "addon",              // "core" solo para módulos no desinstalables
    "dependencies": [             // Módulos requeridos con versión mínima
        { "slug": "core-configuracion", "min_version": "0.1.0" }
    ],
    "active": true,

    // ── Permisos que el módulo POSEE (registra al instalar, retira al desinstalar)
    "permissions": [
        { "name": "mi_modulo_view",   "description": "Ver el módulo" },
        { "name": "mi_modulo_manage", "description": "Gestionar el módulo" }
    ],

    // ── Menú lateral (solo pantallas operativas)
    "menu": [
        {
            "label": "Mi Módulo",
            "icon": "box",               // nombre de icono Feather
            "permission": "mi_modulo_view",
            "url": "/mi-modulo",
            "children": [               // Opcional: sub-ítems
                { "label": "Lista", "url": "/mi-modulo/lista", "permission": "mi_modulo_view" }
            ]
        }
    ],

    // ── Tarjeta en el panel de Administración
    "admin_cards": [
        {
            "title": "Mi Módulo",
            "description": "Frase corta que explica qué hace.",
            "icon": "box",              // FA icon (sin el fa-)
            "url": "/mi-modulo",
            "permission": "mi_modulo_view"
        }
    ],

    // ── Secciones en el panel de Configuración (agrupadas por type)
    "config_sections": [
        {
            "key": "mi_modulo_settings",  // único dentro del sistema
            "label": "Mi Módulo — Ajustes",
            "type": "general",    // general | pagos | notificaciones | api_key | scheduling | herramientas | webhook
            "category": "Sistema",// Sistema | Finanzas | Red | Mensajería | API | Scheduling | Herramientas | Ventas
            "icon": "sliders",
            "url": "/mi-modulo/configuracion",
            "permission": "mi_modulo_manage",
            "description": "Descripción corta (tooltip y panel de ayuda).",
            "doc": {
                "terms": [
                    { "term": "Término clave", "definition": "Qué significa en el contexto del módulo." }
                ],
                "steps": [
                    "Paso 1: ir a la sección.",
                    "Paso 2: ajustar el parámetro.",
                    "Paso 3: guardar."
                ],
                "actions": ["Acción disponible 1", "Acción disponible 2"]
            }
        }
    ],

    // ── Endpoints de API públicos del módulo
    "api_endpoints": [
        {
            "method": "GET",
            "path": "/api/mi-modulo/recurso",
            "description": "Lista los recursos del módulo.",
            "scope": "mi_modulo.read",
            "permission": "mi_modulo_view"
        }
    ],

    // ── Capa IA
    "ai": {
        "knowledge": "Párrafo corto que explica al modelo de IA qué hace este módulo.",
        "example_intents": ["frase de ejemplo que el usuario podría escribir"],
        "sensitive_actions": ["nombre_accion_sensible"]  // piden confirmación
    },

    // ── Pestaña en ficha de cliente (null si el módulo no tiene relación con clientes)
    "client_tab": {
        "label": "Mi Módulo",
        "component": "MiModuloClientTab",  // componente Vue registrado globalmente
        "permission": "mi_modulo_view"
    },

    // ── Servicio contratable (null si el módulo no se vende como servicio)
    "service_type": {
        "key": "mi_modulo",
        "label": "Mi Módulo",
        "price_configurable": true,
        "supports_promotions": true,
        "bundleable": true
    }
}
```

**Regla de `type` para `config_sections`:**

| `type` | Cuándo usarlo |
|---|---|
| `general` | Catálogos, opciones generales del módulo |
| `pagos` | Configuración de cobros, métodos de pago |
| `notificaciones` | Alertas, emails, recordatorios |
| `api_key` | Credenciales de API, tokens, integraciones externas |
| `scheduling` | Tareas programadas, flujos, equipos |
| `herramientas` | Importación, exportación, utilidades |
| `webhook` | Endpoints webhooks de entrada |

---

### 2. Crear `ModuleDefinition.php` (solo si hay lógica extra)

Si el módulo necesita lógica propia al instalar/desinstalar (seeders, limpieza):

```php
<?php
namespace App\Modules\Addons\MiModulo;

use App\Modules\Contracts\ModuleDefinition as BaseDefinition;

class ModuleDefinition extends BaseDefinition
{
    public function moduleDir(): string { return __DIR__; }

    public function install(): void
    {
        // Seeder inicial, si aplica
    }

    public function upgrade(string $from, string $to): void
    {
        // Lógica extra al actualizar (las migraciones delta las corre el servicio)
    }

    public function uninstall(bool $keepData = false): void
    {
        // Limpieza extra (cache propio, jobs en cola, etc.)
    }
}
```

Si no hay lógica extra, no hace falta el archivo.

---

### 3. Verificar que las migraciones tienen rollback

Cada migración del módulo debe tener `down()` que revierte lo que hace `up()`:

```php
public function down(): void
{
    Schema::dropIfExists('mi_modulo_tabla');
}
```

---

### 4. Probar el ciclo de vida

```bash
# Ver qué se afectaría al desinstalar
php artisan module:lifecycle preview-uninstall addon-mi-modulo

# Instalar
php artisan module:lifecycle install addon-mi-modulo

# Simular upgrade: bumpa la versión en module.json y:
php artisan module:lifecycle upgrade addon-mi-modulo

# Desinstalar limpio
php artisan module:lifecycle uninstall addon-mi-modulo

# Desinstalar conservando datos
php artisan module:lifecycle uninstall addon-mi-modulo --keep-data
```

---

### 5. Registrar en `module_registry` (si es nuevo)

```bash
php artisan db:seed --class=ModuleRegistrySeeder
```

Esto registra todos los módulos descubiertos. Para módulos addon nuevos, se registran automáticamente al hacer `install`.

---

### 6. Verificar que aparece en los paneles

```bash
php artisan tinker
>>> use App\Modules\Core\ModuleManager\Services\ModuleRegistry;
>>> ModuleRegistry::clearCache();
>>> count(ModuleRegistry::instance()->getConfigSectionsFlat()); // debe sumar el nuevo
>>> ModuleRegistry::instance()->getMenu();                       // debe incluir el nuevo
```

En el navegador:
- **Menú lateral**: los items de `menu` aparecen en la sección "Addons" del sidebar.
- **Administración**: `/admin/administracion` — tarjeta del módulo.
- **Configuración nueva**: `/admin/configuracion-nueva` — secciones del módulo agrupadas por tipo.

---

## Lo que NO hace falta hacer manualmente

El `ModuleLifecycleService` se encarga de:
- Correr las migraciones del módulo al instalar/actualizar.
- Registrar los permisos Spatie declarados en `permissions`.
- Retirar permisos y sus asignaciones al desinstalar (con aviso previo).
- Validar dependencias antes de instalar.
- Bloquear si otro módulo activo depende del que se intenta desinstalar.
- Registrar todo en `module_registry` y `module_migrations`.
