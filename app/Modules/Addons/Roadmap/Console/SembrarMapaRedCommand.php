<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;

/**
 * Siembra de los 29 items del módulo MAPA DE RED (MR-00 … MR-28).
 *
 * IDEMPOTENTE: dedupe por `title` exacto. Re-ejecutar no duplica; los items que ya existen se
 * reportan como omitidos y no se tocan (salvo el enlace al paraguas, que se repara si falta).
 *
 * DECISIONES DE IRVING APLICADAS EN ESTA SIEMBRA (2026-09-03):
 *
 *  1. Los 29 nacen `estado_aprobacion = aprobado_irving` (salvo MR-27/MR-28 en `requiere_irving`)
 *     PERO con `excluir_pool_automatico = true`. Motivo: `aprobado_irving` es estado ELEGIBLE para
 *     el pool (ver `RoadmapItem::sqlElegibleParaPool()`) y el circuito NO está pausado — sin el
 *     freno, la Torre repartiría los 29 sola, en paralelo y sin respetar la secuencia (MR-05
 *     copiaría datos antes de que MR-02 hiciera el respaldo). El documento de origen es explícito:
 *     "No ejecutar ninguno de los items. La ejecución arranca en una sesión aparte con MR-01."
 *     Para arrancar: liberar el flag de MR-01 desde la Torre, item por item, en orden.
 *     El freno además protege a MR-00 del bucle reap de paraguas (#738/#745/#830/#816/#818/#848/#878).
 *
 *  2. Se siembran los 29 que el cuerpo del documento define (el encabezado decía 28 por error de
 *     conteo). `requiere_irving` va en MR-27 y MR-28, que son los que el listado marca así.
 *
 *  3. Cuatro referencias cruzadas venían con off-by-one de un renumerado previo y se CORRIGIERON
 *     al sembrar: MR-11 (MR-15→MR-16), MR-12 (MR-18→MR-19), MR-13 (MR-17→MR-18) y MR-14
 *     (MR-16→MR-17, MR-20→MR-21).
 *
 * MAPEO DE CAMPOS (el documento de origen usa nombres que no existen en la tabla):
 *   `prompt_para_claude` → `prompt` · `item_padre` → `origen_item_id` · `tipo` → NO EXISTE
 *   (los items de respuesta se marcan por título `[RESPUESTA]` + `origen_item_id`).
 */
class SembrarMapaRedCommand extends Command
{
    protected $signature = 'roadmap:sembrar-mapa-red
                            {--dry-run : Imprime el plan sin escribir nada}';

    protected $description = 'Siembra idempotente de los 29 items del módulo MAPA DE RED (MR-00 a MR-28).';

    /**
     * Bloque obligatorio al final del `prompt` de LOS 29 items. Copiado textual del documento de
     * origen, incluido el placeholder `{id de este item}` (es autoexplicativo para la terminal que
     * lo lea, y el documento pidió copiarlo "textualmente ... sin excepciones").
     */
    private const CANAL_RESPUESTA = <<<'TXT'

## Canal de respuesta (obligatorio)

Antes de marcar este item como `completado`, evalúa tu propio reporte. Si
contiene ALGUNA de estas cinco cosas, crea un item `tipo='respuesta'`:

1. Pregunta abierta que no pudiste resolver sin criterio humano.
2. Decisión que no te toca (alcance, arquitectura, negocio).
3. Hallazgo fuera de alcance (algo roto que no era parte de este item).
4. Desviación: hiciste algo distinto a lo pedido y hay que ratificarlo.
5. Riesgo asumido o trabajo a medias con un supuesto por confirmar.

Si nada de eso aplica, NO crees nada. Un cierre limpio no genera respuesta.

El item de respuesta nace: `tipo='respuesta'`, `origen_item_id`={id de este
item}, `estado_aprobacion='requiere_irving'`, `nivel_riesgo` mínimo `B`.
Título: `[RESPUESTA] {título de este item} — {resumen en ≤10 palabras}`.
Máximo UNO por item origen. Cuerpo: Contexto · Qué se hizo · Qué NO se hizo y
por qué · La decisión pendiente · Opciones con recomendación · Qué se bloquea ·
Qué validar con screenshot. La recomendación es obligatoria.
TXT;

    public function handle(): int
    {
        $dry  = (bool) $this->option('dry-run');
        $defs = $this->definiciones();

        if (count($defs) !== 29) {
            $this->error('Definiciones incompletas: se esperaban 29, hay ' . count($defs) . '.');
            return self::FAILURE;
        }

        $this->info($dry ? '── DRY-RUN: no se escribe nada ──' : '── SIEMBRA MAPA DE RED ──');

        $padreId  = null;
        $pos      = (int) RoadmapItem::max('position');
        $filas    = [];
        $creados  = 0;
        $omitidos = 0;

        foreach ($defs as $d) {
            $existente = RoadmapItem::where('title', $d['title'])->first();

            if ($existente) {
                // Idempotencia: no se re-crea ni se pisa. Sólo se repara el enlace al paraguas si
                // una corrida anterior murió entre MR-00 y sus hijos.
                if ($d['codigo'] === 'MR-00') {
                    $padreId = (int) $existente->id;
                } elseif (! $dry && $padreId && ! $existente->origen_item_id) {
                    $existente->origen_item_id = $padreId;
                    $existente->save();
                }
                $omitidos++;
                $filas[] = [$existente->id, $d['codigo'], $this->corta($d['title']), $d['nivel'],
                            $existente->estado_aprobacion, $existente->origen_item_id ?? '—', 'ya existía'];
                continue;
            }

            if ($dry) {
                $filas[] = ['(nuevo)', $d['codigo'], $this->corta($d['title']), $d['nivel'],
                            $d['estado'], $d['codigo'] === 'MR-00' ? 'null' : '(MR-00)', 'se crearía'];
                $creados++;
                continue;
            }

            $item = RoadmapItem::create([
                'title'               => $d['title'],
                'description'         => $d['description'],
                'prompt'              => rtrim($d['prompt']) . "\n" . self::CANAL_RESPUESTA,
                'modulo'              => $d['modulo'],
                'nivel_riesgo'        => $d['nivel'],
                'nivel_riesgo_origen' => 'interno',
                'estado_aprobacion'   => $d['estado'],
                'priority'            => $d['priority'],
                'status'              => 'pending',
                'origen_item_id'      => $d['codigo'] === 'MR-00' ? null : $padreId,
                // Freno de despacho: la ejecución la libera Irving item por item (decisión 1).
                'excluir_pool_automatico' => true,
                'position'            => ++$pos,
            ]);

            if ($d['codigo'] === 'MR-00') {
                $padreId = (int) $item->id;
            }

            $creados++;
            $filas[] = [$item->id, $d['codigo'], $this->corta($d['title']), $d['nivel'],
                        $item->estado_aprobacion, $item->origen_item_id ?? '—', 'creado'];
        }

        $this->newLine();
        $this->table(['id', 'código', 'título', 'nivel', 'estado', 'padre', ''], $filas);
        $this->newLine();
        $this->line("Creados: <info>{$creados}</info>   Omitidos (ya existían): <comment>{$omitidos}</comment>   Total definido: 29");

        if (! $dry) {
            $this->warn('Los 29 quedaron con excluir_pool_automatico=true: la Torre NO los despachará.');
            $this->warn('Para arrancar, liberar MR-01 desde la Torre. La ejecución es una sesión aparte.');
        }

        return self::SUCCESS;
    }

    private function corta(string $t, int $n = 52): string
    {
        return mb_strlen($t) > $n ? mb_substr($t, 0, $n - 1) . '…' : $t;
    }

    /**
     * Las 29 definiciones, EN ORDEN DE EJECUCIÓN (el orden del documento es la secuencia).
     */
    private function definiciones(): array
    {
        $D = $this->tablaDecisiones();

        return [

        // ───────────────────────── FASE 0 — Salvamento y verdad de partida ─────────────────────────

        [
            'codigo' => 'MR-00', 'nivel' => 'B', 'priority' => 'alta',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-00 — Épica: módulo MAPA DE RED (convivencia con Mapas, migración y corte)',
            'description' => <<<TXT
Item paraguas. Construir un módulo nuevo "MAPA DE RED" que convive con el módulo "Mapas" actual sin
degradarlo, partiendo de una copia de sus datos, agregándole el modelo de planta que hoy falta
(puerto, hilo, empalme, splitter con pérdidas, enlace de servicio), motor de trazo y presupuesto
óptico, y una navegación que reemplace el árbol de 7 niveles. Al final se comparan los dos y se
retira el perdedor.

{$D}
TXT,
            'prompt' => <<<'TXT'
No se ejecuta. Es el contenedor de MR-01 a MR-28.

Cualquier terminal que tome un hijo debe leer primero este item completo, en especial la tabla
D1–D30 de su `description`, y NO volver a preguntar nada que ya esté resuelto ahí.

⚠️ Este item es un PARAGUAS: no tiene trabajo propio. No lo reclames para "hacerlo". Cierra solo,
por el hook de cascada del modelo (`RoadmapItem::saved`), cuando el último de sus sub-items cierre.
Reclamarlo para ejecutarlo es el bucle reap ya documentado siete veces en CLAUDE.md
(#738, #745, #830, #816, #818, #848, #878).
TXT,
        ],

        [
            'codigo' => 'MR-01', 'nivel' => 'A', 'priority' => 'alta',
            'modulo' => 'Mapas', 'estado' => 'aprobado_irving',
            'title'  => 'MR-01 — Mapas: auditoría READ-ONLY del modelo actual (Paso 0 del rediseño)',
            'description' => 'Auditoría de solo lectura del módulo Mapas. Cero escrituras: no tocar código, esquema ni datos.',
            'prompt' => <<<'TXT'
Entregá en `comentarios_claude` estos 8 puntos con evidencia (nombres reales de tabla, conteos, rutas):

1. Inventario de tablas del módulo con conteo de filas y el diagrama de relaciones real (FKs
   declaradas + las implícitas por convención).
2. ¿Existen entidades de puerto y de hilo, o la capacidad se guarda como texto/contador? Citar
   tabla y columna.
3. De las 204 rutas de `route:list`, cuáles se invocan desde el frontend y cuáles quedaron muertas
   (grep en Blade/Vue).
4. Conteo por tipo de elemento (pack, cupboard, junction_box, source, splitter, equipo activo/pasivo)
   y cuántos tienen padre nulo o inexistente.
5. Cómo se relaciona hoy un cliente con una caja: ¿campo directo, tabla pivote, o solo texto?
6. Profundidad máxima real del árbol de carpetas y cuántas carpetas tienen un solo hijo.
7. Qué permisos gatean realmente cada grupo de rutas y cuáles caen al bloqueo admin-only por no
   tener permiso propio.
8. Peso en disco de las tablas del módulo y cuántos elementos tienen lat/lng nulos o en (0,0).

**DoD:** los 8 puntos respondidos, cero escrituras.
TXT,
        ],

        [
            'codigo' => 'MR-02', 'nivel' => 'A', 'priority' => 'alta',
            'modulo' => 'Mapas', 'estado' => 'aprobado_irving',
            'title'  => 'MR-02 — Respaldo verificado de las tablas de Mapas + prueba de restauración',
            'description' => 'Antes de copiar nada, tener un respaldo del que sepamos que se puede volver. Recordar el incidente del 25-ago: la BD de dev quedó en 0 tablas.',
            'prompt' => <<<'TXT'
1) `mysqldump` **solo de las tablas del módulo Mapas** (no la BD completa) a
   `/var/backups/mysql/mapas-YYYYMMDD-HHMM.sql.gz`. No inventes un esquema de backup paralelo; usá
   esa ruta que ya existe.
2) Restaurá ese dump en la base `megaisp_restore` (ya existe) y verificá con conteos fila por fila
   que coinciden con el origen.
3) Documentá en el reporte el comando exacto de restauración de emergencia.

**DoD:** dump creado, restauración probada en `megaisp_restore`, conteos idénticos, comando de
vuelta documentado. **Prohibido** tocar `megaisp` (dev) más allá de lectura.
TXT,
        ],

        // ──────────────── FASE 1 — Andamiaje del módulo nuevo (paridad, sin mejoras) ────────────────

        [
            'codigo' => 'MR-03', 'nivel' => 'B', 'priority' => 'alta',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-03 — Crear el módulo MapaRed (esqueleto, registro, sidebar, permisos)',
            'description' => 'Alta del módulo nuevo siguiendo el contrato de módulos v0.9. El módulo "Mapas" no se toca.',
            'prompt' => <<<'TXT'
Crear `app/Modules/Addons/MapaRed` con `module.json` completo (incluidos `api_endpoints` declarados
desde el día uno, ≥30% de cobertura real), registro en `module_registry`, entrada de sidebar con
label **"MAPA DE RED"**, y permisos con prefijo `mapa_red_`.

Asignar los permisos nuevos a `super-administrator` y `DESARROLLADOR` y correr
`php artisan permissions:sync-roles`. Blade con `@if(auth()->user()->can('...'))`, nunca `@can()`.
Cerrar con `view:clear && config:clear && route:clear` y warm-up `view:cache && config:cache`.

**DoD:** ambos módulos visibles en el sidebar; entrar a `/mapa-red` da una pantalla vacía pero viva;
`/mapas` sigue funcionando idéntico.
**Screenshot a validar:** sidebar con las dos entradas.
TXT,
        ],

        [
            'codigo' => 'MR-04', 'nivel' => 'B', 'priority' => 'alta',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-04 — Esquema espejo mapared_* (migración aditiva)',
            'description' => 'Crear las tablas del módulo nuevo como espejo de las de Mapas, con el prefijo mapared_, más las columnas de geometría de D8 y empresa_id nullable de D30.',
            'prompt' => <<<'TXT'
Migraciones **aditivas** que creen `mapared_*` replicando la estructura auditada en MR-01, agregando:
`lat`/`lng` DECIMAL(10,7), `geom_json` LONGTEXT, `bbox_*`, `empresa_id` nullable, `origen_legacy_id`
(para trazabilidad con el registro viejo) y timestamps.

Índices por `(lat,lng)` y por `origen_legacy_id`.

**Nunca** `ALTER` ni `DROP` sobre tablas del módulo viejo.

**DoD:** `php artisan migrate` corre limpio, las tablas existen vacías, `mapas` intacto (verificar
con conteos antes/después).
TXT,
        ],

        [
            'codigo' => 'MR-05', 'nivel' => 'B', 'priority' => 'alta',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-05 — Comando de copia de datos legacy → mapared_* (idempotente, con dry-run)',
            'description' => 'Traer los datos actuales al módulo nuevo sin tocar el origen.',
            'prompt' => <<<'TXT'
Crear `php artisan mapared:importar-legacy {--dry-run} {--zona=} {--truncar}`.

Reglas: lectura pura del módulo viejo; escritura solo en `mapared_*`; idempotente por
`origen_legacy_id` (re-ejecutar no duplica); `--dry-run` imprime el plan sin escribir; `--truncar`
solo vacía tablas `mapared_*` y jamás las del viejo.

El reporte final debe listar: filas leídas, insertadas, actualizadas, **omitidas y por qué**
(lat/lng nula, padre inexistente, tipo desconocido).

**DoD:** dry-run sobre toda la base y corrida real; conteos del viejo y del nuevo coinciden salvo
las omitidas, que quedan listadas una por una.
TXT,
        ],

        [
            'codigo' => 'MR-06', 'nivel' => 'B', 'priority' => 'alta',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-06 — Paridad funcional 1:1 de la pantalla actual en MAPA DE RED',
            'description' => 'El módulo nuevo debe hacer, de arranque, exactamente lo mismo que el viejo. Todavía sin mejoras.',
            'prompt' => <<<'TXT'
Portar controladores, vistas y JS del mapa actual al módulo nuevo apuntando a `mapared_*` y a
Leaflet (D7).

Mantener por ahora el árbol y los botones tal cual, aunque los vayamos a reemplazar: primero
paridad, después mejora. Compilar con `npm run prod`.

**DoD:** checklist punto por punto de que cada acción del módulo viejo existe y funciona en el nuevo
con los mismos datos.
**Screenshot a validar:** las dos pantallas lado a lado mostrando el mismo troncal de Tultitlán.
TXT,
        ],

        [
            'codigo' => 'MR-07', 'nivel' => 'A', 'priority' => 'alta',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-07 — Prueba de no regresión del módulo Mapas viejo',
            'description' => 'Certificar que después de MR-03 a MR-06 el módulo viejo quedó exactamente igual.',
            'prompt' => <<<'TXT'
Comparar contra el respaldo de MR-02: conteos por tabla, checksum de esquema (`SHOW CREATE TABLE`),
`route:list` filtrado por `/mapas`, y permisos existentes.

Cualquier diferencia se reporta y **se revierte**.

**DoD:** cero diferencias, o diferencias revertidas y documentadas.
TXT,
        ],

        // ───────────────────── FASE 2 — El modelo de datos que hoy falta ─────────────────────

        [
            'codigo' => 'MR-08', 'nivel' => 'B', 'priority' => 'alta',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-08 — Catálogos: tipos de cable, tipos de splitter, tipos de caja, conectores',
            'description' => 'Los catálogos son la base de todo lo demás (pérdidas, colores, capacidades). Patrón tomado de AdminOLT/SmartOLT: primero se configuran tipos, después se dibuja.',
            'prompt' => <<<'TXT'
Tablas:

- `mapared_tipo_cable` (nombre, fabricante, número de hilos, hilos por buffer, esquema de color
  EIA/TIA-598 o ABNT NBR 14771 según D12, atenuación dB/km por ventana, precio, unidad de medida)
- `mapared_tipo_splitter` (nombre, fabricante, balanceado/desbalanceado, ratio, número de puertos,
  pérdida dB según D13/D14, tipo de conector, precio)
- `mapared_tipo_caja` (nombre, capacidad de puertos, capacidad de fusiones, IP/IK)
- `mapared_tipo_conector` (nombre, pérdida dB)

Sembrar con los valores de D13, D14 y D15. CRUD completo con permisos `mapa_red_catalogo_*`.

**DoD:** los cuatro catálogos con CRUD y semilla cargada; las pérdidas son editables desde la UI,
no constantes en código.
TXT,
        ],

        [
            'codigo' => 'MR-09', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-09 — Entidad cable con estructura interna (buffers e hilos)',
            'description' => 'El cable deja de ser una línea dibujada y pasa a tener estructura interna instanciada: buffers e hilos con su color.',
            'prompt' => <<<'TXT'
`mapared_cables`: tipo de cable (FK a catálogo), geometría como GeoJSON LineString en `geom_json`,
longitud calculada en metros, holgura/slack, estado (planeado / en construcción / activo /
retirado), proyecto, zona.

Al guardar un cable, **auto-instanciar** sus buffers e hilos según el tipo (patrón blueprint de
netbox-fms): un cable de 96 hilos crea 96 filas de hilo con su color de buffer y de hilo según el
esquema del catálogo.

**DoD:** crear un cable FO96 en Tultitlán genera 8 buffers × 12 hilos con colores correctos;
borrarlo los borra en cascada; el cálculo de longitud coincide con la distancia real de la polilínea
±2%.
TXT,
        ],

        [
            'codigo' => 'MR-10', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-10 — Entidad puerto (OLT PON, splitter in/out, NAP, ODF, ONT)',
            'description' => 'El puerto como entidad de primera clase, polimórfica, para poder contar ocupación real en una sola query.',
            'prompt' => <<<'TXT'
`mapared_puertos` polimórfica: `puertable_type`/`puertable_id` (elemento dueño), número, rol
(`pon`, `splitter_in`, `splitter_out`, `nap_salida`, `odf`, `ont`), estado (`libre`, `ocupado`,
`reservado`, `dañado`), etiqueta.

Para OLT usar el patrón Frame/Slot/Port como lo maneja MultiOLT.

**DoD:** una NAP con splitter 1:8 tiene 8 puertos de salida y 1 de entrada creados automáticamente;
el conteo libre/ocupado es consultable en una sola query.
TXT,
        ],

        [
            'codigo' => 'MR-11', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-11 — Entidad hilo (strand) de primera clase',
            'description' => 'El hilo deja de ser un número en un campo y pasa a ser fila propia con estado, para que un hilo dañado se refleje en el trazo.',
            'prompt' => <<<'TXT'
`mapared_hilos`: cable (FK), buffer, número, color, estado (`libre`, `asignado`, `dañado`,
`reservado`), y observaciones. Índice por `(cable_id, buffer, numero)`. Vista de ocupación por cable.

**DoD:** listar los 96 hilos de un troncal con su color y estado; marcar uno como dañado se refleja
en el trazo de MR-16.

> Nota de siembra: el documento original decía "el trazo de MR-15"; MR-15 es el backfill y el trazo
> es MR-16. Corregido al sembrar (off-by-one de un renumerado previo).
TXT,
        ],

        [
            'codigo' => 'MR-12', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-12 — Entidad empalme (unión de hilos) y bandejas',
            'description' => 'Es el "thread union" de AdminOLT: unión de hilos Rack→NAP, NAP→NAP y Splitter→NAP.',
            'prompt' => <<<'TXT'
`mapared_empalmes`: hilo A, hilo B (o hilo → puerto de splitter), elemento contenedor
(mufa/NAP/rack), bandeja, posición, tipo (`fusión`, `mecánico`, `conectorizado`), pérdida dB
(default del catálogo, editable por empalme), fecha y técnico.

Validaciones duras: un hilo no puede estar en dos empalmes activos; no se puede empalmar un hilo
consigo mismo; el contenedor debe existir.

UI: doble clic en un marcador tipo Rack, Mufa o NAP abre el panel de unión de hilos con las dos
columnas de hilos disponibles.

**DoD:** unir hilo 3 del troncal con la entrada del splitter de una NAP real de Tultitlán y ver la
unión reflejada en la carta de empalme de MR-19.

> Nota de siembra: el documento original decía "carta de empalme de MR-18"; MR-18 es el presupuesto
> óptico y la carta de empalme es MR-19. Corregido al sembrar.
TXT,
        ],

        [
            'codigo' => 'MR-13', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-13 — Splitter como objeto con ratio, nivel, pérdida y ubicación',
            'description' => 'El splitter deja de ser un texto y pasa a ser objeto con ratio, nivel y pérdida heredada del catálogo, soportando cascada nivel 1 → nivel 2.',
            'prompt' => <<<'TXT'
`mapared_splitters`: tipo (FK catálogo), nivel (1 o 2), elemento contenedor (NAP, mufa o rack),
puerto de entrada, puertos de salida auto-generados según el ratio, pérdida heredada del catálogo y
sobreescribible. Soportar cascada nivel 1 → nivel 2.

**DoD:** modelar una cadena real 1:8 (nivel 1, en rack) → 1:8 (nivel 2, en NAP) y que el presupuesto
óptico de MR-18 sume 11+11 dB más las fusiones y los km.

> Nota de siembra: el documento original decía "el presupuesto óptico de MR-17"; MR-17 es el trazo
> inverso de impacto y el presupuesto óptico es MR-18. Corregido al sembrar.
TXT,
        ],

        [
            'codigo' => 'MR-14', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-14 — enlace_de_servicio: cliente/ONT ↔ puerto de NAP ↔ hilo',
            'description' => 'Es la costura entre la planta y el negocio: sin esto no hay impacto por MRR ni semáforo de salud.',
            'prompt' => <<<'TXT'
`mapared_enlaces_servicio`: cliente (resuelto **por nombre/número de contrato, nunca por ID**, según
D19), ONT (serie), puerto de NAP, hilo del drop, fecha de alta, estado.

Es la costura entre la planta y el negocio: sin esto no hay impacto por MRR (MR-17) ni semáforo de
salud (MR-21).

**DoD:** al menos 20 clientes reales de Tultitlán enlazados y visibles al abrir su NAP.

> Nota de siembra: el documento original citaba MR-16 y MR-20; el impacto por MRR es MR-17 y el
> semáforo de salud es MR-21. Corregido al sembrar.
TXT,
        ],

        [
            'codigo' => 'MR-15', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-15 — Backfill: mapear los datos copiados al modelo nuevo',
            'description' => 'Los datos de MR-05 llegaron con la forma vieja. Acá se convierten al modelo nuevo hasta donde los datos alcancen.',
            'prompt' => <<<'TXT'
Comando `php artisan mapared:backfill {--dry-run} {--zona=}`: deducir tipo de cable por el nombre
(`FO96`, `FO288`, `FO24`), instanciar hilos, crear puertos para las cajas con capacidad conocida, y
**dejar explícitamente en un reporte todo lo que no se pudo deducir**.

Prohibido inventar datos: lo que no se sabe queda nulo y listado.

**DoD:** reporte con tres columnas — convertido / parcial / no convertible — y el porcentaje de
cobertura por zona. Si Tultitlán queda por debajo del 80% convertido, crear item de respuesta.
TXT,
        ],

        // ──────────── FASE 3 — El motor (lo que ninguno de los baratos tiene) ────────────

        [
            'codigo' => 'MR-16', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-16 — Grafo de red y trazo extremo a extremo OLT → ONT',
            'description' => 'Servicio de grafo dirigido sobre puertos, hilos y empalmes: dado un puerto PON o un cliente, devolver la ruta completa en orden.',
            'prompt' => <<<'TXT'
Servicio de grafo dirigido sobre puertos, hilos y empalmes, con pathfinding tipo DAG.

API: dado un puerto PON de OLT o un cliente, devolver la ruta completa (elementos, cables, hilos,
empalmes, splitters) en orden.

Debe tolerar ciclos y rutas incompletas sin colgarse: si la ruta se corta, devolver hasta dónde
llegó y por qué. Cachear por elemento con invalidación al editar empalmes.

**DoD:** desde un cliente real de Tultitlán, el trazo devuelve la cadena completa hasta el puerto
PON, y se dibuja resaltada en el mapa.
TXT,
        ],

        [
            'codigo' => 'MR-17', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-17 — Trazo inverso de impacto (clientes y MRR afectados)',
            'description' => 'La pregunta de oro del NOC: "si corto aquí, ¿a quién dejo sin servicio y cuánto dinero es?". Ninguna plataforma barata del mercado la responde con la dimensión comercial.',
            'prompt' => <<<'TXT'
Dado cualquier elemento (cable, hilo, mufa, NAP, splitter, puerto PON), devolver: lista de clientes
aguas abajo, conteo, y **MRR afectado** sumando el plan de cada cliente.

Botón "¿Quién depende de esto?" en la ficha del elemento. Excluir clientes cancelados.

**DoD:** cortar virtualmente el troncal `T-TULTITLAN-FO96-1` devuelve la lista de clientes y el monto
mensual en riesgo.
**Screenshot a validar:** ese panel de impacto.
TXT,
        ],

        [
            'codigo' => 'MR-18', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-18 — Presupuesto óptico automático desde el trazo',
            'description' => 'Acumular pérdidas sobre la ruta y contrastar el presupuesto calculado contra el RX real que reporta MultiOLT.',
            'prompt' => <<<'TXT'
Sobre la ruta de MR-16, acumular: atenuación por km según ventana, pérdida por splitter (D13/D14),
por fusión, por conector (D15).

Mostrar el desglose elemento por elemento y el total, comparado contra la potencia de transmisión de
la OLT y el umbral de sensibilidad de la ONT. Marcar en rojo si el enlace no cierra.

Comparar el calculado contra el RX real que reporta MultiOLT y mostrar la diferencia.

**DoD:** para 10 clientes reales, la diferencia entre el presupuesto calculado y el RX real de
MultiOLT es menor a 3 dB en al menos 7 de ellos; los otros 3 quedan explicados en el reporte.
TXT,
        ],

        [
            'codigo' => 'MR-19', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-19 — Carta de empalme por caja (vista + PDF)',
            'description' => 'Diagrama por elemento contenedor, bandeja por bandeja, exportable a PDF para que el técnico lo lleve impreso.',
            'prompt' => <<<'TXT'
Diagrama por elemento contenedor mostrando bandeja por bandeja qué hilo entra, con qué se fusiona y
a dónde sale, con los colores del esquema del catálogo. Exportable a PDF para que el técnico lo
lleve impreso.

**DoD:** generar la carta de empalme de una mufa real de Tultitlán y que un técnico pueda seguirla
sin explicación adicional.
TXT,
        ],

        [
            'codigo' => 'MR-20', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-20 — Ocupación de puertos y semáforo de NAP en el mapa',
            'description' => 'Color del marcador según ocupación, con la escala de D16, para que el mapa se lea de un golpe de vista.',
            'prompt' => <<<'TXT'
Color del marcador según ocupación, con la escala de D16 (gris 0–50, amarillo 51–70, naranja 71–99,
rojo 100).

Filtro "solo NAPs con puertos libres". Al pasar el cursor: puertos usados / totales.

**DoD:** el mapa de Tultitlán se lee de un golpe de vista; el filtro de puertos libres devuelve
exactamente las NAPs con capacidad.
TXT,
        ],

        [
            'codigo' => 'MR-21', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-21 — Mapa de estado de red (salud) integrado con MultiOLT',
            'description' => 'Semáforo de salud por NAP alimentado por MultiOLT (D18: no crear lector de OLT propio).',
            'prompt' => <<<'TXT'
Consumir MultiOLT (D18, no crear lector propio) para: promedio de RX por NAP, semáforo de salud
según D17, y un dashboard al hacer clic en la NAP con potencia promedio de la caja, ONUs activas,
ONUs offline, ONUs con señal baja, y la lista de ONUs con nombre, serie, estado, señal, puerto de
NAP, Tx, Rx y OLT.

**DoD:** una NAP con una ONU offline real se ve en amarillo o rojo y su dashboard lo explica.
**Screenshot a validar:** el mapa de estado y un dashboard de NAP.
TXT,
        ],

        // ─────────────────────── FASE 4 — La navegación entendible ───────────────────────

        [
            'codigo' => 'MR-22', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-22 — Nueva navegación: buscador, filtros por capa, clustering, render por zoom',
            'description' => 'Este item es el que resuelve el dolor original: un árbol de 7 niveles con "TRONCAL" dentro de "TRONCAL".',
            'prompt' => <<<'TXT'
Reemplazar el árbol como navegación primaria por:

(a) buscador global que acepte nombre de elemento, nombre de cliente, serie de ONT o dirección y
    vuele a la ubicación;
(b) panel de capas encendibles (OLT, troncales, mufas, NAPs, drops, clientes, postes, cobertura);
(c) clustering de marcadores;
(d) render dependiente de zoom — drops y clientes solo aparecen a partir de cierto nivel.

El árbol se conserva pero derivado y de máximo 3 niveles (D22).

**DoD:** con las 6 zonas cargadas, el mapa abre en menos de 3 segundos y encontrar una NAP por
nombre toma menos de 5 segundos.
TXT,
        ],

        [
            'codigo' => 'MR-23', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-23 — Ficha lateral del elemento (reemplaza los 5 iconos por fila)',
            'description' => 'Todas las acciones de un elemento viven en un panel lateral, no repartidas en iconos por renglón.',
            'prompt' => <<<'TXT'
Un clic en cualquier elemento abre un panel lateral con: identificación, ubicación, tipo, puertos
ocupados/libres, empalmes, clientes colgados, fotos, historial de cambios, y las acciones (editar,
mover, borrar, trazar, ver impacto).

Todas las acciones viven ahí, no repartidas en iconos por renglón.

**DoD:** ninguna acción del módulo requiere volver al árbol.
TXT,
        ],

        [
            'codigo' => 'MR-24', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-24 — Alta de elementos en 3 pasos con snap y nomenclatura automática',
            'description' => 'Dar de alta una NAP debe tomar menos de 30 segundos, sin escribir el nombre a mano.',
            'prompt' => <<<'TXT'
Modo dibujo: clic en el mapa con enganche (snap) al cable o elemento más cercano dentro de 15 m;
formulario mínimo de tipo + splitter; guardar.

Zona, proyecto y nombre se heredan y se generan según D23 y D24. Igual para cables: dibujar
polilínea con snap a los extremos.

**DoD:** dar de alta una NAP nueva completa toma menos de 30 segundos y queda correctamente nombrada
y colgada del troncal correcto sin escribir el nombre a mano.
**Screenshot a validar:** el alta de una NAP de principio a fin.
TXT,
        ],

        // ───────────────────── FASE 5 — Interoperabilidad y cobertura ─────────────────────

        [
            'codigo' => 'MR-25', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-25 — Importador KML/KMZ + GeoJSON + CSV y exportadores',
            'description' => 'Doble propósito: cargar lo que hoy vive en Google Earth, y ser el gancho para migrar redes de otros sistemas hacia MegaISP (importante para Medussa).',
            'prompt' => <<<'TXT'
Importador con: previsualización antes de escribir, mapeo de campos, detección de duplicados por
proximidad (<5 m) y nombre, y reporte de lo omitido.

Formatos por prioridad D20 (KML/KMZ, GeoJSON, CSV; DXF/SHP puede quedar para una ronda posterior y
en ese caso se registra como item nuevo).

Exportadores según D21: KML, GeoJSON, carta de empalme PDF y BOM en XLSX con el precio de los
catálogos.

**DoD:** importar un KMZ real exportado del Google Earth de Meganet y que los elementos caigan en su
lugar con su tipo correcto.
TXT,
        ],

        [
            'codigo' => 'MR-26', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-26 — Cobertura comercial derivada de la infraestructura',
            'description' => 'El polígono de cobertura vendible se genera solo, como unión de los radios de drop de las NAPs con puertos libres.',
            'prompt' => <<<'TXT'
Generar automáticamente el polígono de cobertura vendible como la unión de los radios de drop
(parámetro configurable, default 200 m) de las NAPs **con puertos libres**. Recalcular cuando cambia
la ocupación.

Exponer un endpoint de consulta por coordenada o dirección: "¿hay cobertura aquí?" → sí/no + NAP más
cercana + puertos libres + distancia.

Guardar también, como capa importable, los sectores inalámbricos (azimut, apertura, alcance, altura)
sin construir simulador (D29).

**DoD:** consultar una dirección real de Tultitlán responde correctamente; ocupar el último puerto de
una NAP encoge el polígono.
TXT,
        ],

        // ───────────────────────── FASE 6 — Comparación y corte ─────────────────────────

        [
            'codigo' => 'MR-27', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'requiere_irving',
            'title'  => 'MR-27 — Comparativa formal Mapas vs MAPA DE RED y decisión de corte',
            'description' => 'El punto donde se decide cuál gana, con datos y no por gusto (D26).',
            'prompt' => <<<'TXT'
Producir un cuadro comparativo con evidencia: conteo de elementos en cada módulo, funciones que solo
existen en uno, tiempos de carga medidos, elementos que no se pudieron migrar, y el resultado de la
prueba de trazo OLT→ONT en la zona piloto de Tultitlán.

**No** borrar nada.

Cerrar el item con una recomendación explícita y esperar la decisión de Irving.

**DoD:** cuadro entregado + recomendación + decisión de Irving registrada en el item.
TXT,
        ],

        [
            'codigo' => 'MR-28', 'nivel' => 'C', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'requiere_irving',
            'title'  => 'MR-28 — Retiro del módulo perdedor en dos tiempos',
            'description' => 'Solo se ejecuta con la decisión de MR-27 tomada por Irving y escrita en el item.',
            'prompt' => <<<'TXT'
⚠️ Solo se ejecuta con la decisión de MR-27 tomada por Irving y escrita en el item.

**Tiempo 1:** ocultar el módulo perdedor del sidebar y marcarlo como deshabilitado en
`module_registry` con `keep_data:true` (los permisos Spatie NO se eliminan). Esperar **14 días** de
convivencia sin incidencias.

**Tiempo 2:** con respaldo fresco verificado (repetir MR-02 sobre el perdedor) y confirmación
explícita de Irving en el item, eliminar tablas y código.

Si en cualquier momento aparece una función que solo existía en el perdedor, se aborta el retiro y
se registra item nuevo.

**DoD:** módulo perdedor retirado, respaldo verificado archivado, sidebar limpio, cero rutas 404,
`route:clear` + warm-up ejecutados.
TXT,
        ],

        ];
    }

    /**
     * Tabla D1–D30 — decisiones ya tomadas por Irving. Va COMPLETA dentro del `description` de
     * MR-00 para que cualquier terminal que tome un hijo la tenga a la mano y no vuelva a preguntar.
     */
    private function tablaDecisiones(): string
    {
        return <<<'TXT'
## DECISIONES YA RESUELTAS (no preguntar ninguna de estas)

| # | Decisión | Valor definitivo |
|---|---|---|
| D1 | Estrategia general | **Convivencia**. El módulo `Mapas` actual queda intacto y visible en el sidebar. Se crea uno nuevo en paralelo. Nada se borra hasta MR-27/MR-28. |
| D2 | Nombre visible | **MAPA DE RED** (así, en el sidebar, debajo o junto a "Mapas") |
| D3 | Carpeta del módulo | `app/Modules/Addons/MapaRed` |
| D4 | Slug / ruta base | `mapa-red` → `https://dev.meganett.com.mx/mapa-red` |
| D5 | Prefijo de tablas | `mapared_` (ej. `mapared_elementos`, `mapared_cables`) |
| D6 | Prefijo de permisos | `mapa_red_` (ej. `mapa_red_elemento_view`) |
| D7 | Motor de mapa | **Leaflet + OpenStreetMap**, igual que Flotas. **NO Google Maps** (evitamos API key de pago y su facturación por carga). Capa satelital opcional vía Esri World Imagery. |
| D8 | Geometría en BD | MySQL sin PostGIS. Puntos: `lat DECIMAL(10,7)`, `lng DECIMAL(10,7)`. Líneas/polígonos: `geom_json LONGTEXT` (GeoJSON). Índices compuestos `(lat,lng)` + columnas `bbox_min_lat/bbox_max_lat/bbox_min_lng/bbox_max_lng` para consulta por viewport. **No** usar tipos SPATIAL de MySQL. |
| D9 | Copia de datos | Copia **por comando artisan idempotente**, no por `INSERT ... SELECT` a mano, y nunca por `mysqldump` restaurado encima. El original nunca se modifica. |
| D10 | Migraciones | **Solo aditivas.** `migrate:fresh` está PROHIBIDO. Nunca tocar tablas del módulo `Mapas` viejo con `ALTER`/`DROP`/`UPDATE`. |
| D11 | Base del modelo | Lo que ya existe (`source`, `cupboard`, `junction_box`, `pack`, `splitter`, equipo activo/pasivo) se conserva como **tipos de elemento**, no se tira. Encima se agregan puerto, hilo, empalme y enlace de servicio. |
| D12 | Código de colores de fibra | Por defecto **EIA/TIA-598**. Selector alterno **ABNT NBR 14771**. Configurable por instalación en catálogo, no hardcodeado. |
| D13 | Pérdidas de splitter balanceado (dB) | 1:2=4 · 1:4=7 · 1:8=11 · 1:16=15 · 1:32=19 · 1:64=22 · 1:128=26. Editables en catálogo; estos son los valores semilla. |
| D14 | Pérdidas de splitter desbalanceado (dB, paso/derivación) | 50/50=3.6/3.6 · 40/60=2.7/4.4 · 30/70=1.9/5.6 · 20/80=1.3/7.4 · 10/90=0.8/11 · 5/95=0.4/14 · 1/99=0.3/21. Editables en catálogo. |
| D15 | Pérdidas adicionales semilla | Fibra 1310nm = 0.35 dB/km · 1490/1550nm = 0.25 dB/km · fusión = 0.1 dB · conector mecánico = 0.5 dB · empalme mecánico = 0.3 dB. Editables. |
| D16 | Semáforo de ocupación de NAP | Gris 0–50% · Amarillo 51–70% · Naranja 71–99% · Rojo 100%. (Patrón tomado de AdminOLT/SmartOLT.) |
| D17 | Semáforo de salud de NAP | Verde señal estable · Amarillo variación · Rojo señal mala · Gris sin ONUs asociadas. Se calcula con el promedio de RX de las ONUs de esa NAP. |
| D18 | Fuente de señal/ONU | El módulo **MultiOLT (addon-gestion-red)** ya existente. **No** crear un lector de OLT nuevo ni duplicar el motor Huawei. Se consume lo que MultiOLT ya expone. |
| D19 | Relación con el cliente | El enlace es `cliente/ONT ↔ puerto de NAP ↔ hilo`. Se resuelve **por nombre/serie**, nunca por ID de BD (dev y prod divergen). |
| D20 | Importación prioritaria | 1º KML/KMZ · 2º GeoJSON · 3º CSV · 4º DXF/SHP (este último puede quedar en fase posterior si no da el tiempo). |
| D21 | Exportación | KML + GeoJSON + carta de empalme en PDF + BOM en XLSX. |
| D22 | Árbol de navegación | Máximo **3 niveles derivados** (Zona → Tipo → Elemento). Las carpetas manuales del módulo viejo se conservan como atributo `proyecto` del elemento, **no** como estructura de navegación. |
| D23 | Alta de NAP | Máximo **3 pasos**: clic en el mapa → tipo + splitter → guardar. Zona, proyecto y nomenclatura se heredan del tramo/zona donde cayó el clic. |
| D24 | Nomenclatura automática | Patrón existente `T-{ZONA}-FO{HILOS}-{N}` para troncales y `NAP-{ZONA}-{N}` para NAPs. Correlativo por zona, sin huecos, generado en servidor. |
| D25 | Permisos | Todo permiso nuevo se asigna a `super-administrator` y `DESARROLLADOR`, y se corre `permissions:sync-roles`. Nada de reusar permisos prestados del módulo viejo. |
| D26 | Criterio de corte (qué módulo gana) | Se decide en MR-27 con datos, no por gusto: paridad funcional al 100%, cero pérdidas de datos en la comparación, y que el trazo OLT→ONT funcione en una zona piloto real. |
| D27 | Zona piloto | **Tultitlán** (troncales `T-TULTITLAN-FO96-*`), por ser la mejor documentada hoy. |
| D28 | Frontend | Vue 3 + Quasar UMD sobre Blade, como el resto de MegaISP. Leaflet vía npm. `npm run prod` después de cada cambio `.vue`. |
| D29 | Alcance de inalámbrico/WiFi | **Fuera de alcance en esta ronda.** El módulo guarda el polígono de cobertura y el sector (azimut, apertura, alcance, altura) como dato importable, pero **no** se construye simulador de propagación. Los diseños vienen de UISP Design Center vía KMZ. |
| D30 | Multi-tenant / Medussa | Fuera de alcance en esta ronda. Se diseñan las tablas con `empresa_id` nullable previsto, pero no se implementa aislamiento. |

> Nota de siembra (2026-09-03): D1 y D26 decían "MR-26/MR-27" en el documento de origen; la
> comparativa es **MR-27** y el retiro es **MR-28**. Corregido aquí.
TXT;
    }
}
