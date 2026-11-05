<?php

/*
 * Proyecto de unificación de Talento (UNI-T) — interruptores.
 *
 * CONVENCIÓN: cada cambio de comportamiento del proyecto lee un interruptor de aquí cuyo valor
 * por defecto es el comportamiento ACTUAL (apagado). Emitir una versión publica `main` completo,
 * así que ningún cambio puede depender de estar "solo en dev": o está apagado por defecto, o no entra.
 *
 *   (interruptores nuevos: un archivo en config/unificacion.d/, ver el README ahí)
 *
 * Encender en DEV sin tocar producción: poner UNIFICACION_<NOMBRE>=true en el .env de DEV y luego
 * `php artisan config:clear` (nunca config:cache suelto). Si se edita el .env con un Edit, verificar
 * `ls -la .env` y `chgrp www-data .env`. El .env de producción no se toca.
 *
 * Ver docs/unificacion/07-entorno-y-promocion.md.
 */
$base = [
    // Interruptores del proyecto: ninguno encendido por defecto. Cada item UNI agrega el suyo aquí.
    'interruptores' => [
        // I3 Vendedores: /statics/* y /vendedores/prospectos(/**) se mapean a seller_view_statics /
        // seller_view_prospects (route_permission.php), y el listado de instalaciones del corte deja de
        // insertar cut_installations al abrirse: la sincronización pasa a una acción explícita e idempotente.
        'vendedores_i3' => (bool) env('UNIFICACION_VENDEDORES_I3', false),

        // I1 Talento: «Mi progreso» del vendedor muestra la comisión del estado de cuenta (saldo actual)
        // en vez de leer commissions.account_balance (tabla vacía).
        'mi_progreso_estado_cuenta' => (bool) env('UNIFICACION_MI_PROGRESO_ESTADO_CUENTA', false),

        // B3b Cortes de vendedor: los POST de alta (extras-incomes, installations, suppliers-expenses,
        // observations) exigen su permiso seller_cuts_add_* y no solo ver el corte.
        'cortes_vendedor_b3b' => (bool) env('UNIFICACION_CORTES_VENDEDOR_B3B', false),

        // G1 Tickets: «Asignados a mí» por assigned_to, aviso al nuevo responsable, hilos y permiso de edición.
        'tickets_g1' => (bool) env('UNIFICACION_TICKETS_G1', false),

        // H1 Talento: tableros (técnico/equipo) y escalafón (cuota) cuentan unidades con
        // LiquidationService::countBillableUnits, el mismo conteo de la liquidación y el desglose.
        'unidades_unicas' => (bool) env('UNIFICACION_UNIDADES_UNICAS', false),

        // B4 Scheduling: lista blanca de campos al crear/editar tareas, tareas de orden (talento_type_id)
        // sin cambio de estado/asignados desde Scheduling, mapeo de archive/{id}, misma condición de
        // visibilidad en conteo/búsqueda/listado y aviso «Nueva tarea asignada» solo a asignados nuevos.
        'scheduling_b4' => (bool) env('UNIFICACION_SCHEDULING_B4', false),

        // F1 Scheduling: un solo escritor/lector de fecha y hora (TaskScheduleService) en alta/edición
        // de tareas y en el calendario, con la zona horaria de negocio en vez de la hora del servidor.
        'programacion_unica' => (bool) env('UNIFICACION_PROGRAMACION_UNICA', false),

        // C3 Talento: un solo criterio de track (Actor::trackDeRoles) en Actor, la ficha y el puesto;
        // SUPERVISOR_MOSTRADOR cuenta como mostrador en todos lados.
        'track_unico' => (bool) env('UNIFICACION_TRACK_UNICO', false),

        // B5a Talento: evidencias, evidencia práctica, producción diaria, IA/activación/encuesta y alta de
        // acceso del cliente revisan pertenencia (propia, de su equipo o staff); ids ajenos dan 404 y cada
        // entrega de credenciales del cliente queda en el historial.
        'acceso_registro_b5a' => (bool) env('UNIFICACION_ACCESO_REGISTRO_B5A', false),

        // B3d Talento: autorizar un préstamo exige 'talento.prestamos.autorizar' y quien lo registró no
        // puede autorizarlo salvo con 'talento.prestamos.autorizar_propio' y un motivo (queda en historial).
        'prestamos_autorizar_b3d' => (bool) env('UNIFICACION_PRESTAMOS_AUTORIZAR_B3D', false),

        // E3 Scheduling: borrar una tarea la archiva (deleted_at) en vez de eliminarla; sus evidencias,
        // firmas, incidencias y bono de Talento se conservan. Apagado = borrado duro como antes.
        'tareas_recuperables_e3' => (bool) env('UNIFICACION_TAREAS_RECUPERABLES_E3', false),

        // C4b Talento: departamento del colaborador sale de un catálogo (selector en la ficha, validado en
        // el servidor) en vez de texto libre. Apagado = texto libre como antes. La normalización de los
        // valores ya guardados es un comando aparte: talento:normalizar-departamentos.
        'departamentos_catalogo_c4b' => (bool) env('UNIFICACION_DEPARTAMENTOS_CATALOGO_C4B', false),

        // J1 Talento: el registro de incentivos (talento_ledger_entries) es idempotente por
        // (colaborador, concepto, origen, periodo) y la migración agrega el índice único.
        'ledger_identidad_unica' => (bool) env('UNIFICACION_LEDGER_IDENTIDAD_UNICA', false),

        // J2b Talento: ProjectBonusService::award no vuelve a acreditar el bono de un proyecto que ya está
        // 'done' o que ya tiene movimientos 'bonus' (devuelve entries_written=0, already_awarded=true). Apagado = como antes.
        'bono_proyecto_idempotente' => (bool) env('UNIFICACION_BONO_PROYECTO_IDEMPOTENTE', false),

        // J2a Talento: el bono de salud de red no se vuelve a registrar si ya hay un movimiento 'bonus' para esa
        // orden/tarea y colaborador (evaluate/evaluateTask/endpoint manual). Apagado = como antes.
        'bono_salud_idempotente' => (bool) env('UNIFICACION_BONO_SALUD_IDEMPOTENTE', false),

        // B5c: nadie firma por otro con permisos ordinarios (work_orders.manage deja de abrir documentos).
        'firma_documentos_estricta' => (bool) env('UNIFICACION_FIRMA_DOCUMENTOS_ESTRICTA', false),

        // B6 Talento: la cuenta (users.estado) debe estar activa para entrar por web (Actor), por el
        // login de la app y en cada petición Sanctum; inactivar la cuenta revoca sus tokens.
        'cuenta_activa' => (bool) env('UNIFICACION_CUENTA_ACTIVA', false),

        // B3a Talento: registrar pago de comisión y cobrar deuda exigen `talento.comisiones.registrar_pago`
        // (antes bastaba el permiso de ver). Apagado = comportamiento anterior.
        'comisiones_pago_b3a' => (bool) env('UNIFICACION_COMISIONES_PAGO_B3A', false),

        // F4 Scheduling: el calendario carga solo el rango visible (rango_inicio/rango_fin) y excluye
        // canceladas. Apagado = carga todo como antes. (El arreglo de calendario vacío no depende de él.)
        'calendario_f4' => (bool) env('UNIFICACION_CALENDARIO_F4', false),

        // B3c Scheduling: task_view_full_task (solo ver) deja de abrir crear/editar/borrar/subir/quitar
        // archivo de tareas; esas acciones exigen su permiso propio o tareas.gestionar_ordenes.
        'permisos_tareas_b3c' => (bool) env('UNIFICACION_PERMISOS_TAREAS_B3C', false),

        // B5b Vendedores/Talento: estado de cuenta, deuda, recibos y caja de un vendedor solo para la propia
        // persona o con alcance explícito (admin, cortes, ver pagos de vendedores); cerrar una caja ya cerrada da 409.
        'comisiones_acceso_b5b' => (bool) env('UNIFICACION_COMISIONES_ACCESO_B5B', false),

        // J4 Talento: liquidación con estados calculada→cerrada→pagada (LiquidationStateService) y su historial
        // de eventos. Apagado = solo draft/closed como hoy y el servicio se niega a operar.
        'liquidacion_estados_j4' => (bool) env('UNIFICACION_LIQUIDACION_ESTADOS_J4', false),

        // F2 Scheduling: la tarea programada «Actualizar Fechas Pasadas» (update_task_old_command:process)
        // deja de reescribir fechas: lo atrasado conserva su fecha original. Reprogramar es acción del usuario.
        // (Hasta el 2026-11-04 estaba por error dentro de 'departamentos' y el comando nunca lo leía.)
        'fechas_atrasadas_intactas_f2' => (bool) env('UNIFICACION_FECHAS_ATRASADAS_INTACTAS_F2', false),
    ],

    // C4b: catálogo base de departamentos (se le suman los de talento_role_departments) y equivalencias
    // explícitas «valor guardado» → «departamento del catálogo». Lo que no coincida queda sin tocar.
    'departamentos' => [
        'catalogo' => ['Planta externa', 'Planta interna', 'Mostrador', 'Ventas', 'Sistema'],
        'equivalencias' => [
            'TECNICO PI' => 'Planta interna',
        ],
        'respaldo_dir' => '/var/backups/mysql/preservados/uni',
    ],

    // I5 Compatibilidad de incentivos comerciales (decisión K2 #905, q2 → matriz configurable, default = hoy).
    // Interruptor apagado = ningún motor consulta la matriz y todo se reconoce como hoy (compatible).
    // Por combinación: 'compatible' | 'excluyente' | 'por_campana'. Los lee IncentiveCompatibilityService (Fase 2).
    // 'campanas' = campañas donde una combinación 'por_campana' sí es compatible (default vacío).
    'compatibilidad_incentivos' => [
        'activo' => (bool) env('UNIFICACION_COMPATIBILIDAD_INCENTIVOS', false),
        'matriz' => [
            'vendedor_talento'   => env('UNIFICACION_COMPAT_VENDEDOR_TALENTO', 'compatible'),
            'vendedor_embajador' => env('UNIFICACION_COMPAT_VENDEDOR_EMBAJADOR', 'compatible'),
            'talento_embajador'  => env('UNIFICACION_COMPAT_TALENTO_EMBAJADOR', 'compatible'),
        ],
        'campanas' => [],
    ],

    // J3 Talento: semana de pago ya cerrada (liquidación `closed`). Fase 1 solo DETECTA y ofrece la tabla
    // talento_period_adjustments; ninguna escritura la consulta aún. Apagado = comportamiento de hoy.
    'ajustes_semana_cerrada' => [
        'activo' => (bool) env('UNIFICACION_AJUSTES_SEMANA_CERRADA', false),
    ],

    // Candado de publicación: emitir versión se detiene si entre el último tag y HEAD hay commits
    // con la línea `Proyecto-UNI:`. Excepción solo con el permiso, un motivo e historial.
    'candado_publicacion' => [
        'activo'            => (bool) env('UNIFICACION_CANDADO_PUBLICACION', true),
        'permiso_excepcion' => 'releases.excepcion_proyecto_uni',
    ],
];

/*
 * Interruptores NUEVOS: un archivo por interruptor en config/unificacion.d/<clave>.php que devuelve
 * ['<clave>' => (bool) env('UNIFICACION_<CLAVE>', false)]. Se fusionan en 'interruptores' al cargar.
 * Así dos items en paralelo no editan el mismo archivo y sus merges no chocan. Los de arriba no se mueven.
 * (Se evita config/unificacion/ porque Laravel lo anidaría en config('unificacion.*').)
 */
foreach (glob(__DIR__ . '/unificacion.d/*.php') ?: [] as $archivo) {
    $base['interruptores'] = array_merge($base['interruptores'], (array) require $archivo);
}

return $base;
