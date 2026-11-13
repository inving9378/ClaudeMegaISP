---
name: pruebas-antes-de-avanzar
description: Puerta de calidad OBLIGATORIA antes de cerrar una fase, pasar a otro módulo o pedir el OK de commit a Henry en el trabajo de Marketing IA (o cualquier módulo). Corre la suite completa contra la línea base, migraciones en limpio y rollback, pruebas nuevas, aislamiento por empresa y rol, revisión de seguridad, reglas del sistema (IA/WhatsApp/mapas centrales, dinero en MXN), regresión y reporte a Henry. Si algo falla, NO se avanza.
---

# Pruebas antes de avanzar

Esta skill es una **puerta**, no una sugerencia. Se corre completa —los 8 pasos, en orden— antes de:

- declarar cerrada una fase,
- pasar a otro módulo o a la fase siguiente,
- pedirle a Henry el OK para hacer commit.

**Regla de oro: si un paso falla, se detiene todo.** No se "deja para después", no se marca como
conocido para seguir, no se baja la exigencia de una prueba para que pase. Se corrige y se vuelve a
correr la skill **desde el paso 1**. La única forma de seguir con una falla abierta es que Henry lo
decida por escrito en la conversación (y eso se anota en el reporte).

Nunca se debilita una prueba para ponerla en verde: nada de `markTestSkipped`, borrar asserts,
comentar pruebas o subir tolerancias sin decirlo en el reporte.

---

## 0. Antes de empezar (condiciones)

```bash
WT=/var/www/megaisp-henry-marketing          # o el worktree de la fase; NUNCA /var/www/megaisp
cd "$WT" && git branch --show-current          # debe ser la rama de la fase, no main
git status --short                             # saber exactamente qué cambió
```

- Se trabaja **siempre en un worktree propio**, nunca en `/var/www/megaisp` (ahí integra el circuito).
- Bases de datos permitidas — **ninguna otra**:
  - `megaisp_test` → pruebas con `DatabaseTransactions` (rollback). Para que existan las tablas nuevas,
    se aplican ahí **solo** las migraciones de la fase, con candado y `--path`:
    `flock /tmp/megaisp-suite.lock env APP_ENV=testing DB_DATABASE=megaisp_test php artisan migrate --path=app/Modules/Addons/Marketing/migrations --force`
    (si Henry dice NO a la fase, se revierten con `migrate:rollback` en esa misma base).
  - `megaisp_fresh_test` → pruebas con `RefreshDatabase` (las redirige solo `tests/CreatesApplication.php`).
  - `megaisp_dryrun` → migraciones en limpio y rollback (paso 2).
  - `megaisp` (dev compartida) → **prohibido migrar o escribir** desde esta skill. Solo lectura.
- La cuenta es compartida y esas bases también: **todo lo que migra o corre la suite va con candado**
  `flock /tmp/megaisp-suite.lock …` para no pisar a otra sesión a media corrida.
- Nunca `timeout` corto sobre algo que migra (cortarlo deja la base a medias, incidente #529).

## 1. Suite completa del proyecto (contra la línea base)

La suite del repo **ya trae fallas que no son nuestras** (p. ej. las de `RefreshDatabase` truenan si
falta `database/sql/sql_test_unit.sql`, que salió del repo en V1.64). Por eso el criterio no es
"todo verde", es **"ninguna falla nueva"**:

```bash
# (a) Línea base — UNA vez al arrancar la fase, desde el commit de main en que se partió:
BASE=$(git merge-base HEAD main)
# Worktree de la base con vendor y .env listos (idempotente; se reutiliza en toda la fase):
php /var/www/megaisp/artisan circuito:provision-worktree --path=/var/www/megaisp-base-marketing --base=$BASE
cd /var/www/megaisp-base-marketing && flock /tmp/megaisp-suite.lock \
  php vendor/bin/phpunit --log-junit /tmp/suite-base-$BASE.xml > /tmp/suite-base-$BASE.txt 2>&1

# (b) Suite con los cambios:
cd "$WT" && flock /tmp/megaisp-suite.lock \
  php vendor/bin/phpunit --log-junit /tmp/suite-fase.xml > /tmp/suite-fase.txt 2>&1

# (c) Comparar: listar pruebas que fallan ahora y NO fallaban en la base.
php -r '
$f=function($x){$o=[];foreach((new SimpleXMLElement(file_get_contents($x)))->xpath("//testcase[failure or error]") as $t){$o[]=(string)$t["class"]."::".(string)$t["name"];}return $o;};
$n=array_diff($f($argv[2]),$f($argv[1])); echo count($n)," fallas nuevas\n",implode("\n",$n),"\n"; exit($n?1:0);
' /tmp/suite-base-$BASE.xml /tmp/suite-fase.xml
```

- `phpunit.xml` fuerza `DB_DATABASE=megaisp_test` y `tests/GuardBaseDePruebas.php` bloquea cualquier
  base que no termine en `_test`. **No tocar ninguno de los dos.**
- Si el shell exporta `DB_DATABASE=megaisp` (pasa en terminales del circuito), igual lo pisa el XML con
  `force="true"`; si el candado detiene la suite, es correcto: no se "arregla" quitando el candado.
- **Pasa** si hay 0 fallas nuevas. Cualquier falla nueva = **alto**.
- Durante el desarrollo (no en esta puerta) las pruebas se corren **por archivo**, nunca por directorio.

## 2. Migraciones en limpio y rollback

```bash
cd "$WT"
# (a) En limpio: las migraciones pendientes contra una copia desechable del esquema real.
flock /tmp/megaisp-suite.lock php artisan deploy:dry-run-migrations --keep
#     Si alguna migración toca datos existentes (unique, achicar columna, FK): --with-data=tabla1,tabla2

# (b) Rollback: deshacer exactamente las migraciones de la fase y volver a aplicarlas.
N=<número de migraciones nuevas de la fase>
flock /tmp/megaisp-suite.lock env APP_ENV=testing DB_DATABASE=megaisp_dryrun php artisan migrate:rollback --step=$N --force
flock /tmp/megaisp-suite.lock env APP_ENV=testing DB_DATABASE=megaisp_dryrun php artisan migrate --force
```

Revisar a mano cada migración nueva:

- Solo **aditiva**: `create table`, `add column` con guard `Schema::hasColumn`, `firstOrCreate`.
  Nada destructivo en `up()`. Los `drop` solo en `down()`.
- **Agrega columnas a tablas existentes en vez de crear tablas duplicadas** (`marketing_campaigns`,
  `marketing_leads`, `marketing_publications`, `marketing_generated_content`, etc. ya existen).
- `down()` deja el esquema como estaba (lo prueba el paso 2b).
- Nunca `migrate:fresh`, nunca contra `megaisp`. Si `deploy:dry-run-migrations` avisa que no pudo
  armar el sandbox, eso **no es un pase**: es alto hasta resolverlo.

## 3. Pruebas nuevas de lo agregado

Cada pieza nueva (servicio, controlador, job, componente) trae sus pruebas, y cada prueba cubre:

| Caso | Ejemplo |
|---|---|
| Normal | crear campaña 360 con objetivo, público, oferta, canales, presupuesto y fechas → estrategia guardada |
| Errores | presupuesto negativo, fecha fin antes de inicio, canal inexistente, IA que responde basura o se cae |
| Datos vacíos | empresa sin campañas, lead sin campaña, Meta sin gasto, calendario sin contenido |
| Bordes | presupuesto 0, 80 % exacto del tope, montos con centavos, textos con acentos/emojis |

- Base de prueba: `Tests\Feature\Marketing\MarketingTestCase` (extiende la TestCase de Illuminate +
  `CreatesApplication` + `DatabaseTransactions`). **No** extender `Tests\TestCase` (trae
  `RefreshDatabase` + `migrate:fresh`).
- La IA y Meta se **simulan** en las pruebas (`Http::fake`, dobles del servicio); ninguna
  prueba llama a un servicio real ni gasta dinero.
- Correr cada archivo nuevo por nombre: `php vendor/bin/phpunit tests/Feature/Marketing/<Archivo>Test.php`.

## 4. Aislamiento por empresa y por rol

Para cada endpoint y cada consulta nueva, una prueba que demuestre:

- **Empresa:** un usuario de la empresa A **no ve, no edita, no borra** nada de la empresa B — ni
  pidiéndolo por id directo en la URL (debe dar 404, no 403 que confirme que existe). El `company_id`
  **nunca** se toma del request: sale del usuario autenticado.
- **Rol:** `admin` de marketing, `marketing` y `ventas` ven/hacen solo lo suyo (matriz del plan);
  un usuario sin permiso recibe 403 en la ruta y no ve el botón/menú.
- Listados, conteos, KPIs, gráficas y exportaciones también filtran por empresa (no solo el "ver uno").

## 5. Revisión de seguridad

Revisar el diff completo (`git diff $(git merge-base HEAD main)`) buscando:

- **Validación:** todo input pasa por FormRequest o `validate()` con tipos, rangos y listas cerradas.
- **Consultas seguras:** nada de SQL armado con strings del usuario; `whereRaw`/`DB::raw` solo con
  bindings; `orderBy` de columnas solo desde lista permitida.
- **Asignación masiva:** `$fillable` explícito o `$request->only()`; nunca `$guarded = []` con `->all()`.
- **Ninguna llave expuesta:**
  ```bash
  git diff $(git merge-base HEAD main) | grep -nEi "sk-[a-z0-9]|api[_-]?key\s*[=:]\s*['\"][^'\"<]|token\s*[=:]\s*['\"][A-Za-z0-9]{16,}|EAA[A-Za-z0-9]{20,}|password\s*=\s*['\"][^'\"<]"
  ```
  Debe salir vacío. Tampoco se devuelven llaves al frontend, ni en logs, ni en errores.
- XSS: en Vue nada de `v-html` con texto que venga de IA o de usuarios sin sanitizar.
- Archivos subidos: tipo real (finfo), tamaño máximo, disco privado.

## 6. Reglas del sistema

```bash
D=$(git merge-base HEAD main)
# IA solo por el módulo IA:
git diff $D -- app resources | grep -nE "api\.anthropic\.com|api\.openai\.com|new ClaudeApiClient|OpenAI::|CLAUDE_API_KEY|OPENAI_API_KEY"
# WhatsApp: el código NUEVO de campañas no envía ni se conecta a ninguna línea (Sofía incluida):
git diff $D -- app resources | grep -nEi "WhatsAppGateway|WhatsAppLineResolver|EvolutionApiService|MetaCloudApiService|sendText|whatsapp_instances|sofia|sofía"
# Mapas solo por el módulo de mapas:
git diff $D -- app resources | grep -nE "maps\.googleapis\.com|GOOGLEMAPS_KEY|L\.tileLayer"
# Meta Ads: SOLO lectura del gasto y del tope:
git diff $D -- app | grep -nEi "spend_cap|daily_budget|lifetime_budget" 
git diff $D -- app | grep -nE "Http::(post|put|patch|delete)\(.*graph\.facebook"
# Dinero:
git diff $D -- app database | grep -nEi "USD|float\(|->double\(|currency.*=>.*(?!MXN)"
```

Cada coincidencia se revisa y se justifica o se corrige:

- **IA** solo con `App\Modules\Addons\IA\Services\IA::enviar('marketing.<función>', …)` y su clave
  declarada en `app/Modules/Addons/IA/config/modulos.php`. Sin llaves, clientes HTTP ni modelos propios.
- **WhatsApp**: los mensajes de WhatsApp de las campañas **solo se GENERAN como texto** dentro del
  contenido. El código nuevo **no envía** nada, no usa `WhatsAppGateway` ni ninguna línea, y **no toca
  ni se conecta a Sofía** (Sofía es del módulo de Mapas y vive en Meta; no tiene nada que ver con
  Marketing). Las pantallas viejas de Conversaciones/bot de ventas se rediseñan sin cambiar su lógica.
- **Mapas** solo por el módulo de mapas compartido.
- **Meta Ads**: solo peticiones **GET** para leer gasto y tope. Cero escrituras a campañas, conjuntos,
  anuncios, presupuestos o al tope (`spend_cap`). Credenciales del Hub (`meta-business`), nunca nuevas.
- **Dinero en MXN**: columnas `decimal(12,2)`, moneda `MXN` fija, sin `float`/`double`. Si Meta reporta
  otra moneda, **no se convierte en silencio**: se marca y se avisa.
- El aviso al **80 % del tope** existe, se prueba con 79.99 %, 80 % y 100 %, y no se repite cada minuto.

## 7. Regresión de los otros módulos

- Del paso 1: cero fallas nuevas en **todos** los módulos (no solo Marketing).
- Confirmar que el diff no toca nada fuera de lo acordado:
  ```bash
  git diff --stat $(git merge-base HEAD main)
  ```
  Para el rediseño de Marketing, solo se permite: `app/Modules/Addons/Marketing/**`,
  `resources/js/components/module/marketing/**`, `tests/Feature/Marketing/**`, y las líneas de registro
  de componentes de marketing en `resources/js/app.js` (si Henry lo aprobó). Cualquier otro archivo
  (layout, menú, CSS global, otros módulos) = **alto** y se pregunta a Henry.
- Pruebas que ya existen para piezas compartidas que se usaron (WhatsApp, IA, Publicación, Video MVM):
  correrlas por archivo y que sigan verdes.
- `npm run dev` compila sin errores ni advertencias nuevas.
- Pantallas existentes de Marketing (Leads, Conversaciones, Video MVM, Publicación, Brand Kit) siguen
  abriendo y funcionando (en el navegador, paso 8).

## 8. Reporte COMPLETO a Henry — y DETENERSE

Instrucción fija de Henry: al cerrar cada fase se entrega el reporte **completo** con TODAS estas
secciones (ninguna se omite; si una no aplica se dice "no aplica" y por qué). En español; la parte
"cómo probarlo" en lenguaje de usuario (clics y pantallas, sin nombres de archivo).

```
## Fase N — <nombre> · Resultado: APROBADA | DETENIDA

### 1. Comandos que se corrieron
<cada comando exacto, en el orden en que se corrió, con su código de salida>

### 2. Resultado de cada prueba
| Prueba (archivo::caso) | Tipo (normal/error/vacío/borde/aislamiento/rol/seguridad) | Resultado (pasa/falla) |
|---|---|---|
- Suite completa: <total> pruebas · <fallas que ya existían en la base> · <fallas nuevas: 0>
- Migraciones: en limpio pasa/falla · rollback pasa/falla (<N> migraciones)

### 3. Cobertura
- Medida (si hay pcov/xdebug): <% de líneas de los archivos nuevos/modificados>, comando usado.
- Funcional (siempre): tabla "pieza nueva (método/endpoint/pantalla) → prueba que la cubre";
  cualquier pieza sin prueba se lista como HUECO (y un hueco = fase DETENIDA).

### 4. Fallas encontradas y cómo se corrigieron
- <qué falló> · <cómo se reproducía, paso a paso> · <qué se cambió> · <prueba que ahora lo cubre>

### 5. Revisión de seguridad
- Validación · consultas seguras · asignación masiva · llaves expuestas (salida del grep) · XSS · archivos

### 6. Aislamiento por empresa y por rol
- Matriz empresa A vs B (ver/editar/borrar/listar/KPIs) y matriz de roles admin/marketing/ventas/sin permiso

### 7. Reglas del sistema
- IA por módulo IA · WhatsApp solo texto generado, sin envíos ni Sofía · mapas centrales · Meta solo lectura · MXN

### 8. Archivos modificados
<salida de git diff --stat contra la base> — todos dentro del módulo Marketing (o lista de excepciones aprobadas por Henry)

### 9. Cómo probarlo en pantalla
1. Entra a http://<host>/marketing/... con el usuario ...
2. Haz clic en ... → debes ver ...
3. Con un usuario de otra empresa / rol ventas → no debe aparecer ...

### 10. Para avanzar
¿SÍ (commit de esta fase y continuar a la siguiente) o NO (dime qué corregir)?
```

**Después del reporte: DETENERSE y esperar la respuesta de Henry.**
- **SÍ** → se pide/hace el commit de la fase (con su confirmación) y se continúa a la siguiente.
- **NO** → se corrige exactamente lo que indique y se vuelve a correr esta skill desde el paso 1.
- Nadie (supervisor, agentes ni la sesión principal) hace commit, pasa de fase o cambia de módulo
  sin ese SÍ.

Además, guardar el mismo reporte en `docs/bitacora/<YYYY-MM-DD>-marketing-fase-<N>.md` (regla
permanente de bitácora del CLAUDE.md; append si se repite el mismo día).

**Si el resultado es DETENIDA, no se pide commit ni se pasa a la siguiente fase.**

---

## 9. Después del «APROBADO por Claude de parte de Henry» — subir la fase a producción

Solo con ese texto exacto en la conversación. Sin él, nada se commitea ni se sube. Proceso de LM (ver
memoria `project_release_parcial` y `CLAUDE.md` → Deploy remoto):

1. **Commit** de la fase en la rama de trabajo (`git add` archivo por archivo, mensaje en español,
   firmado como Henry). Integrar a `main` desde `/var/www/megaisp` (verificar `git branch --show-current`
   = main antes de mezclar).
2. **Versión parcial solo con Marketing**: rama `release/<V>` desde la etiqueta de la última versión
   publicada + `cherry-pick -m 1` solo de los merges de Marketing → así el `migrate` de producción
   solo encuentra migraciones de Marketing. Comprobar `git diff --name-only <tag-anterior>` = solo
   archivos de Marketing autorizados. Revisar que no lleve llaves ni datos de clientes (el repo es público).
3. **Permisos**: el deploy NO corre `permissions:sync-roles`; todo permiso nuevo debe crearse y asignarse
   en una migración (aditiva, `givePermissionTo`, resolviendo usuarios por permiso/`login_user`, nunca por id).
4. **Bitácora ANTES** de publicar: `docs/bitacora/<fecha>-marketing-fase-<N>-prod.md` con qué se sube,
   qué migraciones, respaldo y quién lo pidió.
5. **Publicar** la etiqueta + GitHub Release (`releases:publish-github <V> --force`). Si el clasificador
   niega el push, dejar el script probado para que la persona lo corra con `! bash <ruta>`.
6. **Aplicar en producción**: «Buscar actualizaciones» en la pantalla de LM de producción. El
   `remote:deploy` hace, en este orden: respaldo de la base (`backup_db`) → código → ensayo de
   migraciones (`migrate_dryrun`) → compilación → `migrate`. Si algo falla ANTES de `migrate`, regresa
   el código solo. Si falla `migrate`, queda en `failed` SIN regresar (la base puede quedar a medias):
   **detenerse y avisar a Henry e Irving**, no improvisar.
7. **Prueba rápida en producción** y **reporte** de lo que se subió (versión, commits, migraciones,
   resultado del deploy, prueba).
8. Si la prueba en producción falla: regresar a la versión anterior y avisar. **Toda acción que exija
   aprobación de Irving o acceso que esta sesión no tiene se detiene y se avisa; no se busca otra vía.**
