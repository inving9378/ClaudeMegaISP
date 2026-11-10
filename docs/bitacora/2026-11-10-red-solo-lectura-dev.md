## 2026-11-10 — Red en solo lectura para dev (routers MikroTik y OLTs)

- Pedido de Irving (vía la sesión de david): cargar la base de producción en dev para monitorear
  dedicados y ver las ONT reales, sin que nada de lo que se haga en dev cambie routers u OLTs.
- Rama `feat/red-solo-lectura-dev`. No toca la base ni el `.env`.

### Cómo estaba
- MikroTik: fuera de producción `RouterConnection::destinoConexion` desvía toda conexión a
  `MIKROTIK_DEV_*` o a un router de prueba que ya no existe (#997). Dev no escribía en routers reales,
  pero tampoco podía leerlos. Excepción: `CreateFilesToRectifyMikrotikCommand` sube por FTP a la IP
  real del router.
- Colector de métricas: router real solo en producción; en dev, laboratorio o simulado.
- SmartOLT: la configuración de la base (`olt_smartolt_config`) manda sobre el `.env`; con la base de
  producción, dev usaría su token y podría reiniciar, autorizar o cambiar ONUs.
- OLT Huawei: el `.env` de dev apunta a la OLT real. `exec` ya pasa por `ReadOnlyGuard`, pero
  `execWrite` no (solo lo usa `multiolt:w3-write --confirm`).

### Qué cambia
- `config/red.php` + `App\Support\Red\SoloLectura`. `RED_SOLO_LECTURA` sin definir = puesto fuera de
  producción y quitado en producción. Producción se reconoce por APP_ENV, por el actualizador o por
  el nombre de la base (`metricas.bases_produccion`); basta una señal.
- MikroTik: `ClienteRouterOs` (hereda del cliente PEAR2) revisa cada comando en `send()`, por donde
  pasan `sendSync`, `sendAsync` y `Util`. Pasan `print`, `getall`, `get`, `find`, `monitor`,
  `monitor-traffic`, `listen`, `/ping`, `/tool/ping`, `/ip/route/check`, `/cancel` y `/quit`; todo lo
  demás se rechaza sin salir. Se usa en los dos lugares que crean conexiones (`RouterConnection` y
  `MikrotikMetricasDriver`).
- `RED_ROUTERS_REALES_EN_DEV=true`, solo con el candado puesto: dev se conecta a los routers reales
  de la base (conexión general y colector de métricas) para leerlos.
- SmartOLT: middleware en el cliente HTTP compartido; con el candado, todo lo que no sea GET se
  rechaza antes de salir.
- OLT Huawei: `execWrite` se rechaza con el candado puesto.
- `CreateFilesToRectifyMikrotikCommand` no corre con el candado puesto.
- En producción, sin candado, todo se comporta igual que antes.

### Verificado
- `tests/Unit/Red/SoloLecturaTest.php` 10/10. `WriteExecutionTest` ahora declara el candado quitado,
  porque prueba el camino de escritura: 23/23. Sin cambios: `HuaweiTransportTest` 17/17,
  `HuaweiDriverTest` 58/58, `SmartOltDriverTest` 8/8 (su aviso de función obsoleta ya existía),
  `EntornoMetricasTest` 4/4 y `ProbadorLecturaTest` 12/12.
- En dev, desde tinker y sin escribir nada:
  - el candado se pone solo;
  - un router registrado sigue desviándose al de prueba;
  - con `RED_ROUTERS_REALES_EN_DEV=true` va al real y el colector usa `mikrotik`;
  - con además `RED_SOLO_LECTURA=false` vuelve a desviarse;
  - un POST a SmartOLT se rechaza sin salir a la red.

### Falta
- No se ha probado una lectura contra un router real desde dev. Se hace con la base de producción
  ya cargada y `RED_ROUTERS_REALES_EN_DEV=true`.
