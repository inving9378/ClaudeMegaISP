# Catálogo de Acciones de la Consola (item #876)

> Fundamento de los botones de la Torre. Una acción se **declara como datos** (clave, permiso,
> confirmación, si es reversible, qué registra) y un **ejecutor central** es el único que la
> corre. Ningún botón habla directo con su servicio.

## Por qué existe

La consola va a tener del orden de cuarenta botones. Construidos uno por uno son cuarenta
endpoints improvisados, cada uno con su propio criterio de permisos, confirmación y registro.
Definido una sola vez, agregar un botón pasa a ser **declarar datos, no escribir código** — y
nace permisado, confirmado, registrado y con aviso de resultado por construcción.

## Las piezas

- `App\Modules\Addons\Roadmap\Services\ConsolaAcciones\ConsolaAccion` — value object inmutable.
  Valida sus propias reglas al construirse (no al ejecutarse):
  - `reversible = false` exige `confirma` distinto de `'no'` — una acción irreversible sin
    confirmación es la incoherencia que este catálogo hace imposible de declarar.
  - Ninguna clave puede tocar un guardrail (`toca_guardrail => true`, o la clave coincide con un
    patrón protegido: `prod`, `migrate.fresh`, `git.add.all`, `guard.disable`, `tope.duro`).
  - `confirma` distinto de `'no'` exige `advertencia` (el texto del diálogo).
- `ConsolaAccionCatalogo` — registro en memoria, singleton por request (bindeado en
  `ModuleServiceProvider::register()`). `declarar()` lanza si la clave ya existe.
- `TorreAccionesCatalogo::declarar(ConsolaAccionCatalogo $catalogo)` — el único lugar donde se
  declaran las acciones reales de la Torre. Se llama una vez al poblar el singleton.
- `ConsolaAccionExecutor` — el ejecutor central. Todo botón migrado pasa por
  `$executor->ejecutar($accion, $user, $handler, $payload, $confirmacionTexto)`:
  1. Valida el permiso con `Gate::forUser($user)->authorize($accion->permiso)` — la MISMA
     semántica que `Controller::authorize()` usa por dentro, así que un botón migrado rechaza
     exactamente igual que antes de migrarlo.
  2. Si `confirma = si_con_texto`, exige que `$confirmacionTexto` sea igual al nombre de la
     acción (`ConsolaAccion::confirmacionEsperada()`); si no coincide, lanza
     `ValidationException`.
  3. Corre `$handler()` (debe devolver el array que el controller responde tal cual).
  4. Registra SIEMPRE una fila en `consola_acciones_ejecuciones` (clave, actor, payload, ok,
     mensaje, timestamp) — éxito o excepción, sin excepción y sin opción de apagarlo por acción.
     Un fallo al escribir el registro NUNCA tumba la acción que ya se ejecutó (solo se pierde esa
     fila de auditoría; se loguea aparte).
- Migración `2026_08_20_220000_crea_consola_acciones_ejecuciones.php` — tabla aditiva, sin FK
  (el `actor` guarda la etiqueta `"irving:login_user"`, no un id — los ids no son estables entre
  entornos, ver CLAUDE.md).

## Cómo declarar una acción nueva

1. Abre `TorreAccionesCatalogo::declarar()` y agrega un bloque:

   ```php
   $catalogo->declarar([
       'clave'          => 'torre.ejemplo.hacer_algo',      // estable, no la renombres luego
       'nombre'         => 'Hacer algo',                     // lo que dice el botón
       'descripcion'    => 'Qué hace, en una frase.',
       'permiso'        => 'circuito.decidir',                // permiso Spatie existente
       'confirma'       => \App\Modules\Addons\Roadmap\Services\ConsolaAcciones\ConsolaAccion::CONFIRMA_SI,
       'advertencia'    => 'Esto va a hacer X, ¿continuar?',  // obligatorio si confirma != 'no'
       'reversible'     => true,                              // false exige confirma != 'no'
       'simula_primero' => false,
       'registra'       => 'qué guarda en el payload de auditoría, en una frase',
       'respuesta'      => 'Hecho.',                          // plantilla del aviso de resultado
   ]);
   ```

   Si el permiso todavía no existe, créalo vía `PermissionSyncService` (convención del proyecto,
   ver CLAUDE.md §3 Permisos) — el catálogo nunca inventa permisos nuevos por su cuenta.

2. En el controller, inyecta `ConsolaAccionCatalogo` y `ConsolaAccionExecutor` por constructor
   (Laravel los resuelve solos — el catálogo es singleton) y reemplaza la llamada directa al
   servicio por:

   ```php
   $accion = $this->consolaAcciones->obtener('torre.ejemplo.hacer_algo');
   abort_if(! $accion, 500, "ConsolaAccion 'torre.ejemplo.hacer_algo' no está declarada.");

   $resultado = $this->consolaExecutor->ejecutar(
       $accion,
       auth()->user(),
       function () use (/* lo que el handler necesite */) {
           // La lógica de negocio, IDÉNTICA a como estaba antes de migrar. Debe devolver el
           // array que el controller responde tal cual (mismo shape que antes).
           return ['ok' => true];
       },
       ['algo' => 'para el payload de auditoría'],   // opcional
       $request->input('confirmacion')               // solo si confirma = si_con_texto
   );

   return response()->json($resultado);
   ```

   La **resolución de datos** (ej. 404 si el recurso no existe) queda ANTES de llamar al
   ejecutor, igual que en el resto del controller — así una acción migrada no cambia su contrato
   de respuesta ni sus casos de error existentes.

3. Verifica: `php -l` del archivo tocado, y en tinker que `ConsolaAccionCatalogo::obtener()`
   devuelve la acción declarada.

## Lo que el catálogo NO puede declarar

No existe forma de declarar una acción que toque: la separación dev/prod, `migrate:fresh`,
`git add -A`, el `guard()` de la vía externa, o los cuatro topes duros del circuito. El ejecutor
las rechaza al declararse (`ConsolaAccion` lanza `InvalidArgumentException`) — no es una
convención documentada, es imposible de construir.

## Ejemplo real ya migrado

`torre.seguimiento.crear` — botón "＋ Seguimiento" → "Crear seguimiento" en `TorreControl.vue`,
servido por `RoadmapController::seguimiento()`. Antes: `$this->authorize('circuito.decidir')`
inline. Ahora: pasa por el ejecutor central con el mismo permiso `circuito.decidir`, mismo
contrato de respuesta, y además queda registrado en `consola_acciones_ejecuciones`.

## Estado de la migración de botones

Esta fase (#876) deja el fundamento operativo y UN botón real migrado como prueba end-to-end. El
resto de los botones de la Torre (~30, incluido el kill switch `circuito.pause` y el disparo de
vueltas `circuito.disparar`, deliberadamente NO tocados aquí por ser el corazón operativo del
circuito) quedan pendientes de migrar en sub-item(s) separados, agrupados por permiso y por
riesgo — ver el item de seguimiento creado desde #876.
