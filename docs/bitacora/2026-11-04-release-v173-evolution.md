## 2026-11-04 — V1.73-04.11.2026 publicada (parcial: instalador de Evolution, segunda parte)

- Pedida en sesión después del intento 2 de instalar Evolution en PROD (ver
  `2026-11-04-evolution-prod.md`): el build ya pasó y `prisma migrate deploy` falló con P3018.
- Alcance: **solo** `e65641348` (cherry-pick `d2e34284d`), sobre la etiqueta `V1.72-04.11.2026`.

### Choque de número con la V1.73 de Henry (#1159)
- Había una `release/V1.73-04.11.2026` **local**, sin subir, preparada por la persona Henry
  (consulta pública de cobertura, #1159; bitácora `2026-11-04-release-v173.md` en esa rama). No se
  publicó porque espera la decisión de Henry (item nivel B sin aprobación).
- El usuario decidió publicar este arreglo como V1.73. Por eso **la de Henry pasa a ser V1.74** y
  hay que rehacerla sobre `V1.73-04.11.2026` (sus 4 commits encima). Su rama y su worktree
  (`/var/www/megaisp-release-v173`) no se tocaron. Esta rama se llama
  `release/V1.73-04.11.2026-evolution` para no pisar la suya.

### Qué corrige
- `lib/20-evolution.sh` (`_ensure_prisma_deploy`): antes de migrar revisa la BD. Si tiene tablas
  y Prisma no tiene historial, arma `evolution_api_ref` aplicando las migraciones una por una
  (cliente mysql como root) y busca hasta cuál coincide la estructura (columnas, índices, llaves
  foráneas). Registra esas como aplicadas (`migrate resolve --applied`) sin tocar datos y
  `migrate deploy` aplica el resto. Si no coincide con ninguna: vacía → respaldo en
  `/var/backups/megaisp` y BD recreada; con datos → muestra la diferencia y aborta sin tocar nada.
  Se compara contra las migraciones y no contra el esquema Prisma porque en Evolution no
  coinciden (la BD de dev tiene la misma diferencia).
- `patches/02-evolution-lid-migracion.patch`: la migración `20250918183910_add_kafka_integration`
  de Evolution borra la columna `lid` de `IsOnWhatsapp` que agrega `20250613143000`; el esquema
  parchado la usa. En dev existe porque se agregó a mano (commit `c0a0b6c1` de
  `/opt/evolution-api`). Migración propia `20251104120000_megaisp_lid_is_onwhatsapp`, idempotente.
  El parche 01 se renombró (`01-evolution-mysql-megaisp.patch`, mismo contenido): prod con V1.72
  lo tiene aplicado y se omite.
- `respaldar_bd_evolution` en `common.sh`, compartido con el desinstalador.

### Verificación
- `tests/test-20-prisma.sh` (nuevo; mysqld privado + clon parchado, sin root): 26/26. Incluye
  el estado de prod reproducido (tablas de las 18 migraciones, `_prisma_migrations` sin la init,
  `migrate deploy` directo → P3018 «Table already exists») → reconoce 18, aplica solo la de `lid`,
  conserva los datos; y el de dev (lid a mano) → reconoce las 19.
- Paso de parches: con solo el 01 aplicado (prod tras V1.72) aplica el 02 y recompila; en clon
  nuevo aplica ambos; segunda corrida omite ambos.
- `test-30-apache` 33/33, `test-90-unprovision` 27/27, `test-lib` 32/32, sobre la rama de release.
- El hook pre-commit frenó la primera versión de la prueba por una contraseña de juguete fija;
  ahora se genera al azar en cada corrida.

### Cómo se armó
1. Rama `release/V1.73-04.11.2026-evolution` desde `V1.72-04.11.2026` (worktree
   `/var/www/megaisp-release-v173-evo`) + `cherry-pick -x e65641348` → `d2e34284d`.
2. Etiqueta anotada `V1.73-04.11.2026`; se subieron solo la etiqueta y esa rama. `origin/main`
   sigue en `162979a2e`.
3. Fila `releases` #121 + descripción, a nombre de MEGAISP.
4. `releases:publish-github V1.73-04.11.2026 --force` → GitHub Release 434386844,
   `deployment_logs` #97. `releases/latest` = V1.73.
5. Juntada con `main` en `4164bca4f` (sin cambios de contenido; el arreglo entró en `7bb618492`).
- El circuito siguió corriendo.

### Pendiente
- En PROD: «Buscar actualizaciones» → V1.73 (sin migraciones de MegaISP) y reintentar WhatsApp
  Agent → Instalar dependencias.
- Avisar a Henry: su versión ahora es V1.74, sobre V1.73.
