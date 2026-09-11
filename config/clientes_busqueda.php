<?php

/**
 * Buscador v2 del listado de Clientes (item roadmap #9990803).
 * Flag maestro en false: comportamiento idéntico al buscador anterior (D5).
 * Reversión: CLIENTES_BUSQUEDA_V2=false + warm-up (config:clear, NUNCA config:cache con esto activo).
 */

return [

    'v2_habilitado' => (bool) env('CLIENTES_BUSQUEDA_V2', false),

    // D6 — mínimo de caracteres para disparar la consulta (los prefijos explícitos
    // de abajo se saltan este mínimo).
    'min_caracteres' => 3,

    // D6 — tope de resultados por búsqueda.
    'max_resultados' => 200,

    /*
     * Mapa de campos buscables — es la ÚNICA lista blanca que el backend acepta (D4):
     * lo que el front pida fuera de aquí se descarta en silencio.
     *
     * Cada entrada se resuelve con whereExists correlacionado por client_id (D2) —
     * ninguna entrada agrega un join a la consulta principal, así que columnas 1:N
     * (como el SN real sincronizado de SmartOLT en olt_onus, ver 'sn_ont_smartolt')
     * no duplican filas de cliente.
     *
     *   tabla        tabla real a correlacionar ('clients' = la propia tabla base)
     *   columna      columna a comparar, o expresión SQL si 'raw' => true
     *   correlacion  columna de esa tabla que referencia a clients.id (null si tabla=clients)
     *   match        exacto | contiene | prefijo | sufijo
     *   normalizar   null | telefono | mac | sn
     */
    'campos' => [
        'name' => ['tabla' => 'client_main_information', 'columna' => 'name', 'correlacion' => 'client_id', 'match' => 'contiene'],
        'father_last_name' => ['tabla' => 'client_main_information', 'columna' => 'father_last_name', 'correlacion' => 'client_id', 'match' => 'contiene'],
        'mother_last_name' => ['tabla' => 'client_main_information', 'columna' => 'mother_last_name', 'correlacion' => 'client_id', 'match' => 'contiene'],
        'full_name' => ['tabla' => 'client_main_information', 'columna' => "CONCAT(name, ' ', father_last_name, ' ', mother_last_name)", 'raw' => true, 'correlacion' => 'client_id', 'match' => 'contiene'],
        'phone' => ['tabla' => 'client_main_information', 'columna' => 'phone', 'correlacion' => 'client_id', 'match' => 'contiene', 'normalizar' => 'telefono'],
        'phone2' => ['tabla' => 'client_main_information', 'columna' => 'phone2', 'correlacion' => 'client_id', 'match' => 'contiene', 'normalizar' => 'telefono'],
        'email' => ['tabla' => 'client_main_information', 'columna' => 'email', 'correlacion' => 'client_id', 'match' => 'contiene'],
        'nif_pasaport' => ['tabla' => 'client_main_information', 'columna' => 'nif_pasaport', 'correlacion' => 'client_id', 'match' => 'contiene'],
        'id' => ['tabla' => 'clients', 'columna' => 'id', 'correlacion' => null, 'match' => 'exacto'],
        'modem_sn' => ['tabla' => 'client_additional_information', 'columna' => 'modem_sn', 'correlacion' => 'client_id', 'match' => 'contiene', 'normalizar' => 'sn'],
        'gpon_ont' => ['tabla' => 'client_additional_information', 'columna' => 'gpon_ont', 'correlacion' => 'client_id', 'match' => 'contiene', 'normalizar' => 'sn'],
        'box_nomenclator' => ['tabla' => 'client_additional_information', 'columna' => 'box_nomenclator', 'correlacion' => 'client_id', 'match' => 'contiene'],
        'mac' => ['tabla' => 'client_internet_services', 'columna' => 'mac', 'correlacion' => 'client_id', 'match' => 'contiene', 'normalizar' => 'mac'],
        'ip_ranges' => ['tabla' => 'network_ips', 'columna' => 'ip', 'correlacion' => 'client_id', 'match' => 'contiene'],
        'service_user_name' => ['tabla' => 'client_internet_services', 'columna' => 'user', 'correlacion' => 'client_id', 'match' => 'contiene'],
        // SN real sincronizado desde SmartOLT (1:N: un cliente puede tener varias ONUs,
        // confirmado en dev — 18 clientes con más de una fila en olt_onus). Hoy NO es una
        // columna visible del listado (ver Fase 0 del item #9990803); solo se alcanza vía
        // el prefijo explícito `sn:` de abajo (D3.2), independiente de columnas visibles.
        'sn_ont_smartolt' => ['tabla' => 'olt_onus', 'columna' => 'sn', 'correlacion' => 'client_id', 'match' => 'contiene', 'normalizar' => 'sn'],
    ],

    // D3.2 — prefijos explícitos: funcionan SIEMPRE, aunque la columna esté oculta
    // o no esté en el set de "columnas visibles" de arriba.
    'prefijos' => [
        'sn' => ['gpon_ont', 'modem_sn', 'sn_ont_smartolt'],
        'tel' => ['phone', 'phone2'],
        'ip' => ['ip_ranges'],
        'id' => ['id'],
        'mac' => ['mac'],
    ],

];
