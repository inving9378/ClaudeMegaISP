<?php

/*
 * Preferencias de vista por usuario (tabla preferencias_vista_usuario).
 *
 * Lista cerrada: solo se leen y guardan las vistas y preferencias declaradas aquí, y el valor debe
 * cumplir su regla de validación. Para que otro listado guarde sus predeterminados basta con
 * declarar su vista; la tabla y los endpoints no cambian.
 */
return [
    // Tamaño máximo del valor ya convertido a JSON.
    'max_bytes' => 2048,

    'vistas' => [
        // Administración › Listado de Administradores.
        'administradores' => [
            'estado' => ['required', 'string', 'in:todos,activo,inactivo,bloqueado'],
            'rol' => ['required', 'string', 'max:100', 'not_in:client'],
            'colores_estado' => ['required', 'boolean'],
        ],

        // Vendedores › Listado de vendedores.
        'vendedores' => [
            'estado' => ['required', 'string', 'in:todos,activo,inactivo,bloqueado'],
        ],
    ],
];
