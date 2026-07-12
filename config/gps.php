<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ingesta GPS TCP (Sub-fase 2.3a, item #283)
    |--------------------------------------------------------------------------
    | El listener flotas:gps-listen calcula el CRC del frame Ruptela pero, para
    | no perder datos reales mientras el algoritmo de CRC no estaba verificado
    | contra hardware, no lo hacía cumplir. Puerto 5027 sigue cerrado hoy, pero
    | el plan es exponerlo a WAN — antes de eso, endurecer:
    |
    |   strict_crc: si el driver soporta CRC y no coincide, descarta el frame
    |     (no persiste posiciones, no auto-registra el device). Default true.
    |     Poner en false SOLO para pruebas con datos legacy/simulador si el
    |     algoritmo de CRC llegara a diferir del hardware real.
    |
    |   auto_register_unknown_imei: el flujo legítimo de alta de un GPS físico
    |     SIEMPRE pasa primero por la UI (FleetGpsController::activateDevice),
    |     que crea el FleetDevice YA vinculado a un vehículo antes de que el
    |     dispositivo se conecte. Por eso un IMEI que llega SIN estar dado de
    |     alta no es un caso de uso normal — default false: se loguea y se
    |     descarta, sin crear fila. Si Irving necesita una ventana de
    |     descubrimiento (onboarding masivo), puede activarlo temporalmente;
    |     en ese caso el registro queda protegido por imei_new_per_ip_per_hour.
    |
    |   imei_new_per_ip_per_hour: cuota de IMEIs nuevos que una misma IP puede
    |     auto-registrar por hora cuando auto_register_unknown_imei está en
    |     true (evita inflar fleet_devices desde una sola fuente).
    |
    */

    'strict_crc' => (bool) env('GPS_STRICT_CRC', true),

    'auto_register_unknown_imei' => (bool) env('GPS_AUTO_REGISTER_UNKNOWN_IMEI', false),

    'imei_new_per_ip_per_hour' => (int) env('GPS_IMEI_NEW_PER_IP_PER_HOUR', 3),

];
