<?php

/*
|--------------------------------------------------------------------------
| Torre 24/7 Pieza 4 — Fase 2: criterio AUTO/BANDEJA de hardening (#918)
|--------------------------------------------------------------------------
|
| Sub-item de seguimiento de #880 (Pieza 4). SOLO DECLARATIVO: este archivo no lo lee
| ningún Service/Provider todavía — eso es la fase de wiring, el sub-item SIGUIENTE de
| #880 (que sí tocará PriorizarSeguridadCommand.php / RevisorService::briefarSeguridad()).
|
| Contexto completo (frontera dura real, brief de Opus con la lista AUTO): ver
| comentarios_claude de #880. Resumen: la frontera dura real vive en
| config('circuito.jarvis.escalamiento') + las tablas circuito_fronteras /
| circuito_frontera_terminos (item #673, editable desde Torre → Configuración). Este
| archivo NO la reemplaza ni la toca — es un criterio nuevo, aparte, para decidir qué
| hallazgos de hardening podrían un día auto-corregirse sin pasar por Irving.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Criterio AUTO vs BANDEJA
    |--------------------------------------------------------------------------
    |
    | Descriptivo (prosa de la condición), no código ejecutable — la fase de wiring decide
    | cómo evaluarlo contra un item real. Default restrictivo (#918 punto 3): cualquier
    | término que NO esté en `auto_terminos` cae en BANDEJA. Nunca se invierte la carga
    | de prueba.
    |
    */
    'criterio' => [
        'auto_if'    => 'nivel_riesgo en [B, C] && aditivo && reversible && !frontera_dura',
        'bandeja_if' => 'nivel_riesgo == A || frontera_dura || !reversible',
        'default'    => 'bandeja',
    ],

    /*
    |--------------------------------------------------------------------------
    | Carril AUTO — lista CORTA y CONSERVADORA
    |--------------------------------------------------------------------------
    |
    | Copiada tal cual del brief de Opus (comentarios_claude de #880). No ampliar sin ese
    | mismo nivel de revisión — el arranque conservador es a propósito.
    |
    */
    'auto_terminos' => [
        'sanitizar_validar_entrada',
        'parametrizar_queries',
        'escapar_salida',
        'headers_seguridad',
        'guards_null',
        'bump_dependencia_cve',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excepciones explícitas a BANDEJA
    |--------------------------------------------------------------------------
    |
    | Términos que a primera vista encajarían en AUTO pero arrancan en BANDEJA a propósito.
    | El propio brief de Opus pide ver "mover secreto hardcodeado a .env" en bandeja unas
    | semanas antes de aflojarlo — no meterlo en `auto_terminos` todavía.
    |
    */
    'bandeja_excepciones' => [
        'mover_secreto_hardcodeado_a_env',
    ],

    /*
    |--------------------------------------------------------------------------
    | Carril BANDEJA existente — solo referencia informativa
    |--------------------------------------------------------------------------
    |
    | Ya vive en la infra real (config/circuito.php → circuito.jarvis.escalamiento, y las
    | tablas circuito_fronteras/circuito_frontera_terminos de #673). Este archivo NO la
    | toca ni la reduce; se copia aquí solo para que el criterio de arriba se lea completo
    | sin tener que saltar a otro archivo.
    |
    */
    'bandeja_terminos_existentes' => [
        'permiso', 'rol', 'spatie', 'auth', 'login', 'password', 'contraseña', 'credencial',
        'bcrypt', 'idor', '.env', 'secret', 'dinero', 'pago', 'cobro', 'factura', 'nómina',
        'comisión', 'openpay', 'spei',
    ],

];
