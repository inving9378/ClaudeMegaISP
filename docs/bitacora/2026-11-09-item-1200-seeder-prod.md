## PREPARADA 2026-11-09 — NO EJECUTADA — Seeder «Habilitar caja» en producción (item #1200)

> Entrada previa obligatoria (regla del 2026-10-11: ninguna escritura manual en producción sin
> bitácora ANTES). Se prepara con el código; **se completa y se commitea antes de correr el
> paso 2**. Al ejecutarla, cambiar el título a `## YYYY-MM-DD HH:MM — EJECUTADA — …` y llenar
> los campos `<…>`.

| Campo | Valor |
|---|---|
| Qué | `php artisan db:seed --class=TalentoPlantaExternaSeeder --force` |
| Dónde | PROD, `/var/www/ClaudeMegaISP`, base `meganet_prod_claude` |
| Cuándo | `<fecha y hora>`, **después** del release que trae las migraciones `2026_11_09_150000` a `150300` |
| Quién lo corre | `<persona>` |
| Respaldo previo | `<archivo de /var/backups/mysql/ del paso 1>` |
| Qué escribe | 1 fila en `talento_work_order_types` (tipo «Habilitar caja», planta externa, 0 puntos, no cobrable), 2 en `talento_ot_type_evidence_requirements` (Foto antes, Foto después) y 1 en `talento_work_order_templates`. Nada de dinero, permisos ni autenticación. |

### 0. Comprobaciones previas (solo lectura)

```bash
cd /var/www/ClaudeMegaISP
git describe --tags                                   # el release que trae #1200
php artisan migrate:status | grep 2026_11_09_15       # las 4 deben decir Ran
```

**El proyecto debe existir por NOMBRE** (los ids de dev y prod no coinciden; en dev es el 10, en
prod puede ser otro). El seeder lo busca por título sin importar mayúsculas ni espacios repetidos:

```sql
SELECT id, title
FROM projects
WHERE deleted_at IS NULL
  AND LOWER(REGEXP_REPLACE(TRIM(title), '[[:space:]]+', ' ')) = LOWER('Habilitación de caja Zona: Caja');
```

(Probada en dev, MySQL 8.4: devuelve el proyecto 10. `REGEXP_REPLACE` necesita MySQL 8; si prod
no la tiene, la misma búsqueda que hace el seeder, solo lectura:
`php artisan tinker --execute='echo json_encode(DB::table("projects")->whereNull("deleted_at")->get(["id","title"])->filter(fn($p)=>mb_strtolower(trim(preg_replace("/\s+/u"," ",$p->title)))===mb_strtolower("Habilitación de caja Zona: Caja"))->values());'`)

- **1 fila** → seguir. Anotar el id: `<id del proyecto en prod>`.
- **0 filas** → NO crear el proyecto a mano. El seeder deja el tipo sin proyecto (aviso en
  `storage/logs/laravel-*.log`) y ese tipo rechaza órdenes con un 422 claro hasta asignarle
  proyecto en la Fase C. Decidir con Irving si se corre así o se espera.
- **Más de 1 fila** → parar: el seeder tomaría el de id menor. Decidir con Irving cuál es.

Lo demás que el seeder necesita, también por nombre:

```sql
SELECT id, name, area FROM talento_work_order_types WHERE name = 'Habilitar caja';            -- esperado: 0 filas
SELECT id, name FROM talento_evidence_types WHERE name IN ('Foto antes', 'Foto después');     -- esperado: 2 filas
```

Si el tipo ya existe, el seeder no lo modifica (solo le pone proyecto si no tiene): revisar con
Irving antes de seguir.

### 1. Respaldo

```bash
php artisan backup_db:process
ls -la /var/backups/mysql | tail -3        # anotar el archivo arriba
```

### 2. Correr el seeder

```bash
php artisan db:seed --class=TalentoPlantaExternaSeeder --force
```

Salida esperada: «Tipo «Habilitar caja» creado (id N).», dos «Evidencia obligatoria … agregada.»
y «Plantilla «Habilitar caja» creada.». Si aparece «No existe el proyecto…», es el caso de
0 filas del paso 0.

### 3. Verificación (solo lectura)

```sql
SELECT id, name, category, area, project_id, points, is_billable, requires_validation, active
FROM talento_work_order_types WHERE name = 'Habilitar caja';
-- category=campo, area=planta_externa, project_id = el del paso 0, points=0, is_billable=0

SELECT e.name FROM talento_ot_type_evidence_requirements r
JOIN talento_evidence_types e ON e.id = r.evidence_type_id
JOIN talento_work_order_types t ON t.id = r.ot_type_id
WHERE t.name = 'Habilitar caja';                                   -- Foto antes, Foto después

SELECT id, name, active, JSON_LENGTH(fields) campos, JSON_LENGTH(checklist) materiales
FROM talento_work_order_templates WHERE name = 'Habilitar caja';  -- 13 campos, 7 materiales
```

En pantalla: Talento › Órdenes de trabajo › Nueva orden → «Habilitar caja» aparece en el bloque
«Planta externa» y pide sus 5 datos.

### 4. Cómo deshacerlo (solo si no hay órdenes de ese tipo)

```sql
SELECT COUNT(*) FROM tasks t JOIN talento_work_order_types w ON w.id = t.talento_type_id
WHERE w.name = 'Habilitar caja';                                   -- debe ser 0 para borrar
```

Con 0 órdenes: borrar la plantilla, las 2 evidencias del tipo y el tipo, en ese orden. Con
órdenes: no borrar; desactivar (`active = 0`) el tipo y la plantilla.

### Resultado

`<pegar la salida del seeder y de las consultas del paso 3>`
