<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarketingPublishingManualsSeeder extends Seeder
{
    public function run(): void
    {
        $version   = 1;
        $generated = now();

        $sections = [
            [
                'module_slug' => 'marketing-publishing-tecnico',
                'title'       => 'Marketing Publicador — Manual Técnico',
                'content'     => $this->manualTecnico(),
                'version'     => $version,
                'generated_at'=> $generated,
                'created_at'  => $generated,
                'updated_at'  => $generated,
            ],
            [
                'module_slug' => 'marketing-publishing-usuario',
                'title'       => 'Marketing Publicador — Manual de Usuario',
                'content'     => $this->manualUsuario(),
                'version'     => $version,
                'generated_at'=> $generated,
                'created_at'  => $generated,
                'updated_at'  => $generated,
            ],
            [
                'module_slug' => 'marketing-publishing-setup-meta',
                'title'       => 'Marketing Publicador — Setup Cuentas Meta',
                'content'     => $this->manualSetupMeta(),
                'version'     => $version,
                'generated_at'=> $generated,
                'created_at'  => $generated,
                'updated_at'  => $generated,
            ],
        ];

        foreach ($sections as $section) {
            $exists = DB::table('manual_sections')
                ->where('module_slug', $section['module_slug'])
                ->where('version', $version)
                ->exists();

            if (!$exists) {
                DB::table('manual_sections')->insert($section);
            }
        }
    }

    private function manualTecnico(): string
    {
        return <<<'MD'
# Manual Técnico — Publicador Multicanal (Fase 5)

## 1. Arquitectura General

El Publicador Multicanal sigue este flujo:

```
MultivariantCampaign
       │
       ▼
 PostPublisherService.queueCampaign()
       │
       ├─► SmartRouter.routeContent()        ← filtra por aspect ratio + duración
       │        └─► ChannelManager.getDriver()
       │
       ├─► Publication records creados (status: queued | waiting_credentials)
       │
       └─► PublishPostJob dispatched
                │
                ▼
          AbstractPublishDriver.publish()
                │
          ├── FacebookPageDriver
          ├── InstagramReelsDriver
          ├── InstagramFeedDriver
          ├── InstagramStoriesDriver
          ├── WhatsAppStatusDriver
          └── EmailBlastDriver
                │
                ▼
          Métricas → FetchMetricsJob (1h y 24h después)
```

### Archivos clave

| Archivo | Responsabilidad |
|---|---|
| `Services/Publishing/ChannelManager.php` | Resuelve el driver correcto por plataforma:canal |
| `Services/Publishing/SmartRouter.php` | Filtra canales por aspect ratio y duración |
| `Services/Publishing/PostPublisherService.php` | Coordinador: cola, publica, reintenta, métricas |
| `Services/Publishing/TokenRefresher.php` | Renueva tokens Meta antes de que expiren |
| `Services/Publishing/Drivers/` | Un driver por canal (6 en total) |
| `Jobs/PublishPostJob.php` | Worker que llama a PostPublisherService.publishNow() |
| `Jobs/FetchMetricsJob.php` | Obtiene métricas 1h y 24h después de publicar |
| `Jobs/RefreshMetaTokensJob.php` | Cron diario — renueva tokens que expiran en < 7 días |
| `Controllers/PublishingController.php` | API REST completa |
| `Controllers/MetaOAuthController.php` | Flujo OAuth de Meta (start + callback) |

---

## 2. Cómo Agregar un Nuevo Canal

### Pasos:

1. Crear `app/Modules/Addons/Marketing/Services/Publishing/Drivers/TuNuevoDriver.php`:

```php
class TuNuevoDriver extends AbstractPublishDriver {
    public function getRequiredCredentials(): array { return ['api_key']; }
    public function validateCredentials(): array { /* ... */ }
    public function publish(Publication $pub): array { /* ... */ }
}
```

2. Registrar en `ChannelManager::$driverMap`:
```php
'tuplataforma:tucanal' => TuNuevoDriver::class,
```

3. Crear migración para insertar el nuevo channel en `marketing_publication_channels`:
```php
PublicationChannel::create([
    'platform'     => 'tuplataforma',
    'channel_type' => 'tucanal',
    'slug'         => 'tu-nuevo-canal',
    'supported_aspect_ratios' => ['9:16'],
    // ...
]);
```

4. El `SmartRouter` lo detectará automáticamente al hacer routing.

**Ejemplo — TikTok:**
- La API de TikTok Content Posting usa `client_key` + `client_secret` + OAuth
- Flujo similar a Instagram: crear video → poll hasta listo → publicar
- Agregar permisos: `video.publish`, `video.list`

---

## 3. Flujo OAuth de Meta

```
Usuario click "Conectar Meta"
       │
       ▼
GET /marketing/meta/oauth/start
  → Lee App ID del Hub (integración 'meta')
  → Construye URL de Facebook Login con scopes necesarios
  → Redirect a facebook.com/dialog/oauth
       │
       ▼ (usuario aprueba)
GET /marketing/meta/oauth/callback?code=xxx
  1. code → short-lived token (2h)
  2. short-lived → long-lived token (60 días)
  3. long-lived → lista de Páginas con page_access_tokens
  4. Cada Página → IG Business Account vinculada
  5. Guarda todo en Hub (integración 'meta', campo config)
  6. Activa channels en marketing_publication_channels
  → Redirect a /marketing/publishing/setup
```

### Permisos requeridos para App Review de Meta:
- `pages_show_list` — ver páginas del usuario
- `pages_manage_posts` — publicar en páginas
- `instagram_content_publish` — publicar en Instagram
- `business_management` — acceso a Business Manager

### Cómo renovar tokens manualmente:
```bash
php artisan tinker --execute="
  app(App\Modules\Addons\Marketing\Services\Publishing\TokenRefresher::class)
      ->refreshMetaTokens(1);
"
```

### Errores comunes de OAuth:
- `Invalid OAuth access token` → Token expirado, reconectar en /marketing/publishing/setup
- `Application does not have permission` → App Review de Meta pendiente
- `The access token could not be decrypted` → App Secret incorrecto en Hub

---

## 4. Estructura de Base de Datos

### `marketing_publication_channels`
| Campo | Tipo | Descripción |
|---|---|---|
| platform | varchar(30) | facebook, instagram, whatsapp, email |
| channel_type | varchar(30) | page_feed, reels, stories, feed_square, status, blast |
| supported_aspect_ratios | json | ["9:16","1:1"] |
| credentials_ready | boolean | FALSE hasta conectar cuentas |
| platform_config | json | tokens específicos del canal |

### `marketing_publications` (columnas Fase 5 agregadas)
| Campo | Tipo | Descripción |
|---|---|---|
| pub_channel_id | bigint | FK a marketing_publication_channels |
| caption | text | Texto de la publicación |
| hashtags | json | Array de hashtags |
| scheduled_for | timestamp | null = publicar ahora |
| retry_count | int | Intentos fallidos |
| metrics | json | {likes, views, reach, comments, shares} |
| ab_variant_tag | varchar(50) | "niche:gamer" para tracking |

### `marketing_publication_logs`
Log inmutable de eventos por publicación. Eventos: created, queued, publishing, published, failed, retry, cancelled, metrics_updated.

---

## 5. Sistema de Retry y Graceful Degradation

### Estados de una publicación:
```
draft → queued → publishing → published
                           ↘ failed (reintenta 3 veces con backoff: 1min, 5min, 15min)
waiting_credentials (cuando el canal no tiene credenciales)
scheduled (queued + scheduled_for en el futuro)
cancelled
```

### Graceful degradation:
- Si un canal no tiene credenciales → status `waiting_credentials` (NO falla)
- Cuando se conecten las cuentas → reintentar manualmente desde la cola
- El sistema publica en los canales que SÍ están listos y guarda el resto

### Cron `marketing:publish-due` (cada minuto):
```php
// Toma publicaciones queued + scheduled_for <= now y dispatcha PublishPostJob
Publication::where('status', 'queued')
    ->where(fn($q) => $q->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now()))
    ->get()
    ->each(fn($pub) => PublishPostJob::dispatch($pub));
```

---

## 6. Webhooks y Métricas

### Estrategia: polling (no webhooks activos todavía)
- **1 hora** después de publicar → `FetchMetricsJob` (primeras métricas)
- **24 horas** después → segunda fetch (métricas consolidadas)
- **Cada 4 horas** → `FetchAllMetricsJob` para publicaciones recientes sin métricas

### Fuente de métricas por canal:
- **Facebook**: `GET /{post_id}?fields=likes.summary(true),comments,shares`
- **Instagram Reels**: `GET /{media_id}/insights?metric=plays,reach,likes,comments`
- **WhatsApp**: Evolution no devuelve métricas — campo `metrics` queda vacío
- **Email**: se registra `sent` y `failed` al momento de envío

---

## 7. Testing y Debug

### Simular publicación sin publicar realmente:
```bash
php artisan tinker --execute="
  \$pub = App\Models\Marketing\Publication::where('status','queued')
      ->whereNotNull('pub_channel_id')
      ->first();

  \$ch = App\Models\Marketing\PublicationChannel::find(\$pub->pub_channel_id);
  \$driver = app(App\Modules\Addons\Marketing\Services\Publishing\ChannelManager::class)
      ->getDriver(\$ch);

  // Solo validar credenciales sin publicar
  print_r(\$driver->validateCredentials());
"
```

### Logs útiles:
```bash
tail -f storage/logs/laravel.log | grep -E "\[FB\]|\[IG\]|\[WA\]|\[Email\]|\[Publish\]|\[Token\]"
```

### Verificar cola de jobs:
```bash
php artisan queue:work --queue=default --once --verbose
```

> **Screenshot pendiente**: subir diagrama de arquitectura del publicador aquí.
MD;
    }

    private function manualUsuario(): string
    {
        return <<<'MD'
# Manual de Usuario — Publicador Multicanal

## ¿Qué es el Publicador Multicanal?

El Publicador Multicanal toma los videos que genera tu campaña multivariante (un video por nicho de cliente) y los publica automáticamente en hasta 6 canales diferentes:

| Canal | Para qué sirve |
|---|---|
| **Facebook Page Feed** | Videos en tu página de Facebook visible para todos |
| **Instagram Reels** | Videos cortos virales (máx 90 segundos, formato vertical) |
| **Instagram Feed** | Videos cuadrados en tu perfil de Instagram |
| **Instagram Stories** | Videos de 24 horas (formato vertical) |
| **WhatsApp Status** | Video de 30 segundos en tu estado de WhatsApp Business |
| **Email Blast** | Email con el video embebido para tus clientes registrados |

El sistema elige automáticamente qué video va a qué canal basándose en el formato del video (vertical 9:16, cuadrado 1:1).

---

## Mi Primera Publicación — Tutorial Paso a Paso

### Paso 1: Genera una campaña multivariante
Ir a `/marketing/campaigns/generate` y crear una campaña. El sistema genera 1 video por nicho (Gamer, Familia, Emprendedor, etc.)

> **Screenshot pendiente**: captura de la pantalla de generación de campaña.

### Paso 2: Espera a que terminen los videos
Los videos tardan 5-15 minutos. Los puedes ver en `/marketing/video-queue`.

### Paso 3: Ir a "Publicar Campaña"
Menú Marketing → **Publicar Campaña** o ir a `/marketing/publishing/campaign`.

### Paso 4: Selecciona tu campaña
En el dropdown aparecen las campañas completadas. Selecciona la que quieres publicar.

### Paso 5: Revisa el routing automático
El sistema muestra para cada nicho qué canales son compatibles:
- ✅ **Listo para publicar** — credenciales configuradas y formato correcto
- ⏸️ **Formato incompatible** — ese video no aplica para ese canal (normal)
- ⚠️ **Pendiente credenciales** — necesitas conectar esa cuenta primero

### Paso 6: Edita caption y hashtags (opcional)
El sistema sugiere un caption automático. Puedes editarlo. Los hashtags se agregan solos al final.

### Paso 7: Decide cuándo publicar
- **Publicar ahora** → los videos van directo a la cola de publicación
- **Programar** → elige fecha y hora. Los mejores horarios son sábado/domingo 9-11 PM.

### Paso 8: Confirma y publica
Revisa el resumen (cuántos videos, cuántos canales) y click en **Publicar Campaña**.

### Paso 9: Sigue el progreso
Ir a `/marketing/publishing/queue` para ver el estado de cada publicación en tiempo real.

### Paso 10: Ver métricas después
Las métricas (likes, views, reach) aparecen en `/marketing/publishing` unas horas después de publicar.

---

## ¿Qué hago si un canal está pendiente?

Cuando ves "⚠️ Esperando credenciales" en un canal, significa que no está conectado todavía.

**Para conectar Facebook e Instagram:**
Ir a `/marketing/publishing/setup` → click "Conectar Meta". El sistema te lleva a Facebook para autorizar. Ver el [Manual de Setup Cuentas Meta] para el proceso completo.

**Para WhatsApp Status:**
WhatsApp Status funciona a través de Evolution API. Si tu WhatsApp está conectado en el bot (puedes verificar en `/whatsapp`), el canal de Status debería funcionar automáticamente.

**Para Email:**
El administrador del servidor debe configurar SMTP. Consultar con el técnico.

---

## Errores Comunes y Soluciones

| Error | Causa | Solución |
|---|---|---|
| "Token expirado" | El token de Meta venció (60 días) | Ir a Setup → Conectar Meta de nuevo |
| "Publicación rechazada por Instagram" | Video muy largo o formato incorrecto | Verificar duración máx 90s (Reels), 60s (Feed) |
| "Video muy largo" | Supera el límite del canal | El SmartRouter lo filtra automáticamente — normal |
| "Email no llegó" | SMTP mal configurado | El técnico debe revisar MAIL_HOST en servidor |
| "WhatsApp desconectado" | Evolution perdió sesión | Reconectar WhatsApp desde el módulo WhatsApp |

---

## Programar Publicaciones — Mejores Prácticas

### Mejores horarios por tipo de audiencia:
- **Gamer**: Viernes/Sábado 9 PM - 11 PM
- **Familia**: Sábado/Domingo 3 PM - 6 PM
- **Emprendedor**: Martes/Jueves 7 AM - 9 AM
- **Jubilado**: Mañanas de lunes a viernes 9-11 AM
- **Estudiante**: Domingos 7 PM - 9 PM

### Frequencia recomendada:
- **No publicar la misma campaña dos veces el mismo día** en el mismo canal
- Instagram Reels → 1 vez por día máximo para no saturar
- WhatsApp Status → máximo 3 por semana (más puede verse como spam)

---

## Métricas — Cómo Leer el Dashboard

### ¿Qué significa cada número?
- **Likes**: personas que reaccionaron positivamente
- **Views/Plays**: cuántas veces se reprodujo el video
- **Reach**: cuántas personas únicas lo vieron
- **Comments**: comentarios recibidos
- **Shares**: veces que lo compartieron

### ¿Cómo comparar nichos?
En el dashboard verás las publicaciones con el tag `niche:gamer`, `niche:familia`, etc. Puedes filtrar por ese tag para comparar qué nicho obtuvo mejor respuesta.

---

## FAQ

**¿Cuánto cuesta publicar?**
Publicar en Facebook, Instagram, WhatsApp y Email es gratuito. Solo pagas si usas Meta Ads (publicidad pagada), que es un proceso diferente.

**¿Puedo borrar una publicación ya publicada?**
Desde el sistema puedes cancelar publicaciones en cola. Para borrar algo ya publicado, debes ir directamente a Facebook/Instagram y borrarlo ahí.

**¿Por qué algunos videos no se pueden publicar en Stories?**
Stories solo acepta formato vertical (9:16). Si tu video es cuadrado (1:1), el sistema automáticamente lo excluye de Stories.

**¿Cómo agrego SMS más adelante?**
SMS es la Fase 6 del módulo. Cuando esté lista, aparecerá como un canal adicional automáticamente.
MD;
    }

    private function manualSetupMeta(): string
    {
        return <<<'MD'
# Guía de Setup de Cuentas Meta para MegaISP

## Visión General — Qué Cuentas Necesitas

Para que el Publicador Multicanal funcione con Facebook e Instagram, necesitas:

| Cuenta | Para qué | Costo | Tiempo |
|---|---|---|---|
| **Cuenta personal Facebook** | Base de todo | Gratis | Ya tienes |
| **Página de Negocio Facebook** | Publicar contenido de Meganet | Gratis | Ya tienes |
| **Meta Business Manager** | Administrar todas las cuentas desde un solo lugar | Gratis | 15 min + verificación 1-3 días |
| **Cuenta Instagram Business** | Publicar Reels, Feed, Stories | Gratis | 10 min |
| **App en Meta for Developers** | Permite que el sistema publique por ti | Gratis | 30 min |

**Tiempo total estimado**: 60-90 minutos activos + hasta 72 horas de revisión por Meta.

---

## Paso 1: Verifica tu Página de Facebook

1. Entra a tu Página de Meganet en Facebook
2. Ve a **Configuración de la Página → Roles de la página**
3. Confirma que tu cuenta aparece como **Administrador**
4. Completa la información de negocio: descripción, horario, dirección, sitio web

> **Screenshot pendiente**: captura de la sección Roles de la Página.

---

## Paso 2: Crear Meta Business Manager

**URL**: https://business.facebook.com

1. Click en **"Crear cuenta"**
2. Nombre del negocio: **Meganet Telecomunicaciones** (o como esté registrado)
3. Tu nombre completo y email de empresa
4. **Agregar tu Página al Business**: Business Settings → Páginas → Agregar → "Agregar una Página"
5. Selecciona la Página de Meganet

### Verificar el negocio (IMPORTANTE):
- Business Settings → Verificación del negocio
- Subir documentación: constancia fiscal, acta constitutiva, o estado de cuenta con nombre del negocio
- Meta tarda 1-3 días hábiles en aprobar
- Sin verificación, algunos permisos están limitados a 25 personas

> **Screenshot pendiente**: captura del panel de Meta Business Manager.

---

## Paso 3: Convertir Instagram a Business

**Esto se hace desde la APP MÓVIL de Instagram** (no se puede desde computadora).

1. Abrir Instagram en tu teléfono
2. Ir a tu perfil → menú hamburguesa (☰) → **Configuración**
3. **Para profesionales** → Cuenta profesional
4. Seleccionar **Empresa** (no Creador)
5. Categoría: **Servicios de Internet** o **Telecomunicaciones**
6. **CRÍTICO**: Vincular a tu Página de Facebook de Meganet (NO a tu perfil personal)
7. Confirmar que en tu perfil aparece "Empresa" abajo de tu nombre

### Verificar que quedó bien:
- En la app: Estadísticas → debe mostrar alcance, impresiones, etc.
- Si no aparecen estadísticas, la cuenta no se convirtió correctamente

> **Screenshot pendiente**: captura del menú "Para profesionales" en Instagram.

---

## Paso 4: Crear App en Meta for Developers

**URL**: https://developers.facebook.com/apps

1. Click en **"Crear app"**
2. Tipo: **Empresa** (Business)
3. Nombre: `MegaISP Marketing` (o similar)
4. Email de contacto: tu email
5. **Business Manager**: seleccionar Meganet Telecomunicaciones (el que creaste en Paso 2)

### Agregar productos a la App:
6. En el panel de la app → "Agregar productos"
7. Agregar: **Facebook Login for Business**
8. Agregar: **Instagram Graph API**

### Configurar dominios y redirect URI:
9. En Facebook Login → Configuración → Dominios de OAuth válidos de sitio web:
   ```
   http://192.168.105.11
   http://192.168.105.11/marketing/meta/oauth/callback
   ```
   > Nota: cuando tengas dominio real, agregar también `https://tudominio.com`

10. En Facebook Login → Configuración → URIs de redireccionamiento de OAuth válidos:
    ```
    http://192.168.105.11/marketing/meta/oauth/callback
    ```

### Obtener credenciales:
11. En el panel principal de la app → **Configuración → Básica**
12. Copiar **App ID** y **App Secret**

> **Screenshot pendiente**: captura de la sección "Configuración Básica" de tu app.

---

## Paso 5: Configurar la App en MegaISP

1. Ir a `/integraciones` en el sistema
2. Buscar o crear integración **Meta**
3. En el campo de configuración (JSON), ingresar:
   ```json
   {
     "app_id": "TU_APP_ID_AQUI",
     "app_secret": "TU_APP_SECRET_AQUI"
   }
   ```
4. Guardar la integración
5. El sistema mostrará "Meta: configurado" (pero todavía no conectado)

> ⚠️ **Importante**: nunca copies el App Secret visible en pantalla. Usa la función de mostrar/ocultar en Meta for Developers y cópialo directamente al sistema.

---

## Paso 6: Conectar tu cuenta de Facebook al sistema

1. Ir a `/marketing/publishing/setup`
2. Click en **"Conectar Meta"** (o el botón de Facebook)
3. Se abre una ventana de Facebook pidiendo permisos:
   - Pages: ver tus páginas ✓
   - Pages: administrar publicaciones ✓
   - Instagram: publicar contenido ✓
   - Business: acceso básico ✓
4. Click en **Continuar** → **Continuar** → **Guardar**
5. El sistema detecta automáticamente:
   - Tu Página de Meganet
   - Tu cuenta de Instagram Business vinculada
6. Verifica que cada canal aparezca con **✅ Listo**

> **Screenshot pendiente**: captura de la pantalla de configuración de canales con todos en verde.

---

## Paso 7: Solicitar Permisos Avanzados a Meta (App Review)

Tu app comienza en "Modo de desarrollo". En este modo, solo puede publicar en cuentas que hayas autorizado como desarrolladores. Para publicar en producción, necesitas el **App Review**.

### Permisos a solicitar:
- `pages_manage_posts` — **Obligatorio** para publicar en Facebook
- `instagram_content_publish` — **Obligatorio** para publicar en Instagram

### Proceso:
1. En Meta for Developers → tu app → **App Review → Solicitudes**
2. Click en **"Agregar a solicitud"** para cada permiso
3. Para cada permiso, necesitas:
   - **Descripción en español**: "El sistema MegaISP necesita este permiso para publicar videos de marketing automáticamente en la Página de Facebook de Meganet Telecomunicaciones"
   - **Video de demostración** (~3 minutos): grabas la pantalla mostrando el flujo completo (conectar → seleccionar campaña → publicar)
   - **Capturas de pantalla**: del flujo en el sistema

4. Enviar la solicitud

### Tiempos esperados:
- **Primera revisión**: 24-72 horas
- **Si rechaza**: Meta envía email con razones, puedes apelar o corregir y reenviar
- **Causas comunes de rechazo**: video de demo incompleto, descripción poco clara

### Mientras esperas:
El sistema funciona en modo desarrollo — puedes publicar añadiendo tu cuenta como probador:
- App → Roles → Probadores → Agregar Facebook User ID del probador

> **Screenshot pendiente**: captura de la sección App Review con los permisos solicitados.

---

## Configuración de WhatsApp Status via Evolution

WhatsApp Status usa la misma Evolution API que ya está configurada para el bot de ventas.

1. Verificar que Evolution está conectado: `/whatsapp` debe mostrar "Conectado"
2. El canal WhatsApp Status se activa automáticamente cuando Evolution está online
3. **Limitación**: Evolution publica al Status de la cuenta conectada. No puedes elegir qué contactos ven el status — lo ven todos tus contactos normalmente

Si Evolution está desconectado → reconectar desde `/whatsapp` escaneando QR.

---

## Configuración de Email Blast

### Con SMTP propio (recomendado):
Agregar al archivo `.env` del servidor:
```
MAIL_MAILER=smtp
MAIL_HOST=mail.tudominio.com
MAIL_PORT=587
MAIL_USERNAME=marketing@meganet.com
MAIL_PASSWORD=TuPassword
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=marketing@meganet.com
MAIL_FROM_NAME="Meganet"
```

### Cumplimiento legal (LFPDPPP + CAN-SPAM):
- Solo enviar a clientes que dieron consentimiento
- Incluir opción de baja en cada email (el template ya la incluye)
- No comprar listas de correo
- El sistema usa la tabla `marketing_leads` — solo contactos que entraron por formularios de Meganet

---

## Troubleshooting General

| Problema | Solución |
|---|---|
| "Mi token expiró" | Ir a Setup → Conectar Meta de nuevo. El cron lo renueva automáticamente si el token tiene > 7 días. |
| "Mi App fue desaprobada" | Ver email de Meta con razones. Corregir y reenviar. Común: video demo incompleto. |
| "Cambié de admin de la Página" | El nuevo admin debe reconectar en Setup → Conectar Meta. |
| "Error 10: Application does not have permission" | App Review de Meta pendiente. En modo dev, agregar cuenta como Probador. |
| "Error 200: Requires extended permission" | El permiso específico no fue aprobado en App Review. |
| "The access token could not be decrypted" | App Secret incorrecto en /integraciones. Volver a copiar de Meta for Developers. |

> **Screenshot pendiente**: capturas de los mensajes de error más comunes y cómo lucen en el sistema.
MD;
    }
}
