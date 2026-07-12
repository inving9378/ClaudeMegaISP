# Inventario de supuestos ⚠️VERIFY — RuptelaDriver (item roadmap #286)

FASE 1 del item #286. Solo inventario/clasificación — **no se tocó el parsing** (Fases 2-4
del item quedan bloqueadas hasta que Irving apunte un GPS Ruptela físico y se capture una
traza cruda real; ver `deploy/README-gps-real.md`).

Archivo: `app/Modules/Addons/Flotas/Services/Gps/Drivers/RuptelaDriver.php`

## Clasificación por impacto

### Bloque A — CONECTIVIDAD (si están mal, el dispositivo puede reintentar indefinidamente
o el listener puede rechazar frames válidos)

| # | Línea(s) | Supuesto | Riesgo si es incorrecto | Cómo validar con traza real |
|---|----------|----------|--------------------------|------------------------------|
| A1 | `:197-198` (`calculateCrc16`) | CRC16-CCITT, poly `0x1021`, init `0x0000` | Bajo *hoy* (el parseo es tolerante: solo loguea, no descarta), pero si se endurece a "rechazar" con el poly/init equivocado se perderían frames reales | Comparar `calculateCrc16(payload)` calculado contra el CRC recibido (`u16` en offset `2+len`) de varias tramas reales; si difiere sistemáticamente, probar variantes (init `0xFFFF`, poly distinto, orden de bytes) hasta que coincida siempre |
| A2 | `:159` / `buildAck()` | Formato del ACK `[len=0x0002][0x64][ack:1]` | **Alto**: si el equipo no acepta este ACK, reenvía el mismo paquete indefinidamente (reenvíos infinitos, saturación del listener) | Verificar en la traza que, tras enviar el ACK actual, el dispositivo NO reenvíe el mismo frame; si reenvía, contrastar contra doc oficial Ruptela (puede requerir el CRC del propio ACK) |
| A3 | `:31,:70` (`COMMAND_EXT_RECORDS = 0x44`, rama `if ($cmd !== self::COMMAND_RECORDS) return []`) | Extended records (`0x44`/`0x68`) no implementados — hoy se descartan silenciosamente sin generar posiciones | Medio: si el equipo real usa records extendidos (p. ej. por firmware/config), esas tramas nunca producen `Position` | Confirmar en la traza qué `cmd` envía el equipo real; si aparece `0x44`/`0x68` con frecuencia, implementar su parseo |

### Bloque B — EXACTITUD (el frame se acepta y genera posición, pero el dato puede quedar
mal escalado)

| # | Línea(s) | Supuesto | Riesgo si es incorrecto | Cómo validar con traza real |
|---|----------|----------|--------------------------|------------------------------|
| B1 | `:189` (`decodeImei`) | IMEI = 8 bytes big-endian uint64 → decimal (no BCD) | Medio: si el equipo real codifica en BCD, el IMEI decodificado no coincidiría con el IMEI físico del dispositivo → el listener no lo asociaría a ningún `fleet_devices` y quedaría como `unregistered` | Comparar el IMEI decodificado contra el IMEI real impreso/configurado en el equipo |
| B2 | `:115` (`$alt`) | Altitud en metros enteros (no décimas de metro) | Bajo-medio: si el divisor real es 0.1m, la altitud reportada saldría 10× mayor de lo real | Comparar altitud reportada contra altitud GPS conocida del punto de prueba |
| B3 | `:116` (`$angle`) | Ángulo/rumbo = `u16 / 100.0` (centésimas de grado) | Bajo-medio: rumbo mal escalado (dirección de viaje incorrecta en mapa/geocercas) | Comparar el rumbo calculado contra la dirección real de desplazamiento durante la prueba |
| B4 | `:151` (`skipIoSection`) | Los IOs (ignition/battery/etc.) se recorren para avanzar el puntero pero **no se extraen** | Bajo (no rompe nada hoy — es funcionalidad ausente, no un bug de exactitud), pero bloquea features futuras que dependan de ignition/batería | No es "validar", es implementar: una vez confirmado el layout IO con datos reales, extraer los IDs de interés (ignition, batería) en vez de solo saltarlos |

## Cómo usar este inventario cuando llegue el hardware (Fases 2-4 del item)

1. Apuntar el GPS Ruptela físico siguiendo `deploy/README-gps-real.md` y capturar tramas
   crudas (el listener ya loguea CRC ok/mismatch; ampliar logging temporal si hace falta
   ver bytes crudos).
2. Recorrer este inventario fila por fila contra la traza real.
3. Ajustar código por lote, un commit por hallazgo confirmado (`fix(flotas): validar
   parsing Ruptela contra GPS real (<campo>)`), quitando el marcador `⚠️VERIFY`
   correspondiente en `RuptelaDriver.php` una vez confirmado (o corrigiendo si difiere).
4. Bloque A primero (conectividad) — sin ACK/CRC correctos no vale la pena afinar exactitud.
5. **No desplegar el listener a WAN** hasta cerrar el Bloque A (regla ya establecida en el
   prompt original del item).
