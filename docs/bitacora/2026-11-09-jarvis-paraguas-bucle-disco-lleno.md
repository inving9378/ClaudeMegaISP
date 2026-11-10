## 2026-11-09 12:30 — Disco lleno en dev por un bucle de Jarvis sobre dos paraguas

### Síntoma
`dev.meganett.com.mx` respondía 500 con `fwrite(): Write of 2842 bytes failed with errno=28 No space
left on device`. `/dev/sda1` (148 GB, partición única) al 100%.

### Causa
`AvanceService::cerrarParaguasConHijosCerrados()` (lo corre `circuito:jarvis-vigilar` cada minuto)
intentaba cerrar los paraguas **#995** y **#576**, que tienen todos sus hijos cerrados. Un guard del
`saving` de `RoadmapItem` vetaba el cierre y los dejaba en `aprobado_irving`:

- #576: nivel C con rama y sin `merge_commit` → guard (1), queda esperando el merge de Irving.
- #995: nivel B con rama y sin `merge_commit` → gate de cierre con bloqueantes (`cierre_incompleto`).

Como seguían cumpliendo la consulta, al minuto siguiente se reintentaban. Cada intento añadía uno o dos
eventos al `log` y reescribía la fila completa (3.3 MB y 1 MB). Con `binlog_format=ROW` y
`binlog_row_image=FULL`, eso eran ~8 MB de binlog por minuto: **~10 GB/día**, con retención de 30 días.

### Corrección (rama `fix/jarvis-paraguas-bucle`)
Si el último evento `paraguas_cerrado` de `jarvis-avance` tiene menos de 24 h
(`AvanceService::HORAS_REINTENTO_PARAGUAS`) y el item sigue sin cerrar, se salta. Pasada la espera se
reintenta. Prueba: `tests/Feature/Roadmap/ParaguasVetadoNoSeReintentaTest.php` (falla sin el cambio).

### Limpieza del disco (dev, 2026-11-09)
| Acción | Liberado |
|---|---|
| `npm cache clean --force` | ~3 GB |
| `PURGE BINARY LOGS` (2 días y luego 1 día) | ~15 GB |
| `SET PERSIST binlog_expire_logs_seconds = 86400` (antes 2592000) | tope ~10 GB |
| 25 worktrees ya mergeados a main y sin cambios (`git worktree remove`) | ~13 GB |
| Transcripts de Claude con más de 7 días (1,217 sesiones) | ~12 GB |
| `php artisan backups:purge-test --force` (keep=7) | 6.7 GB |

Quedó en 47 GB libres (67%). Las ramas de los worktrees se conservan. Una bitácora sin commitear de
`megaisp-panorama` se rescató en
`/home/meganet/backups/rescate-worktrees-20261109/megaisp-panorama/docs/bitacora/`.

### Escritura manual en la BD de dev (no es producción)
Compactar el `log` de #995 y #576: cada racha de eventos repetidos (`jarvis-avance`/`paraguas_cerrado`
y `jarvis:verificarCierre`/`cierre_incompleto` con el mismo contenido) queda en una sola entrada, la
última, con `repeticiones` y `desde`. Respaldo previo de las dos filas:
`/home/meganet/backups/roadmap_items-576-995-20261109.sql`. Lo corre la sesión de Claude Code de Irving.

### Pendiente
- #576 y #995 siguen sin cerrar: necesitan su merge (o `sin_merge_esperado`) y, #995, `enlace_revision`.
- `backups:purge-test` está programado en `Kernel.php`, pero en dev no hay `schedule:run`: no corre solo.
- `/var/log/btmp.1` pesa 278 MB: muchos intentos de login fallidos contra el servidor.
