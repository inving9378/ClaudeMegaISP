## 2026-10-16 17:45 — Preparación de la release V1.61 (SIN publicar; pendiente del OK de Irving)

Base: `V1.60-15.10.2026` (`e77125ee`). `main` lleva **30 commits** (sin merges) encima, 44 archivos.
Nada se ha publicado: no hay tag ni push. Al publicar con «+ Nueva versión» sale **todo** `main`.

### Notas de la versión (para usuarios)

**Facturación**
- **Prefactura actualizada:** cada factura del cliente (Clientes › Facturación › Facturas y
  Finanzas › Facturas) tiene un botón para sacar su prefactura al día en PDF: estado (pendiente o
  pagado), conceptos, IVA en pesos, total con letra, referencia para pagar y datos fiscales del
  cliente. El PDF pesa unos 29 KB (antes ~1.1 MB). Documento informativo, sin valor fiscal.
- **Enviar:** el correo lleva la prefactura en PDF adjunta, con asunto «Tu prefactura {periodo} —
  Meganet». En una factura **pagada** pide confirmación y se manda como **comprobante de pago**
  («Tu comprobante de pago {periodo} — Meganet»); ya no sale «Monto a pagar: 0.00».
- **Enviar ya no cambia una factura pagada a «Enviada»**.
- Las acciones de cada factura quedan en orden: Prefactura, Imprimir, Enviar, Marcar pagada y,
  separado al final, Borrar.

**Clientes**
- Nueva tarjeta **Datos fiscales** en Información (RFC, razón social, régimen, C.P., uso del CFDI,
  correo fiscal). Se muestran en la prefactura; no se emite factura fiscal.

**Correos**
- Los correos del sistema ya no salen con el asunto «Notificación»: llevan su asunto real.

**Plantillas de documentos**
- El editor carga bien la plantilla al editarla, avisa en vez de guardarla vacía y ya no «fija» los
  datos de la empresa al guardar. «Previsualizar» muestra el logo.

**Desarrollador** (de otra sesión, ver decisión 1)
- Panel Terminal con pestañas (ventanas tmux) y la terminal detrás de sesión con rol.

### Cambios técnicos

| Item | Commits | Qué |
|---|---|---|
| #480 | `328ffd4e` `f88142e2` `84c8d7e7` `bebf5ad4` `2692b479` `40a5e1e4` `3320ad22` `ffdda906` `78ad960e` `5cfadb43` | Prefactura v2: editor de plantillas, no-horneado, MontoEnLetras, PrefacturaDataProvider/PdfService, migración de la plantilla + `prefactura:actualizar-plantilla`, ruta y botón, tarjeta fiscal (+ fix 500 de su API), Enviar con PDF |
| #497 | `b04a93da` `8b154972` `52b2fd2b` `1e9a964b` | facturacion@ en bloque fiscal, logo en Previsualizar (`App\Support\LogoPdf`), botón fiscal arriba, orden de acciones |
| #486 | `ace30365` | `StandardMail` usa `title ?? subject` |
| #498 | `dc7bf2d2` `c3d35a8b` `c031bf49` | `markAsSent()` solo draft→issued; Enviar en pagada = comprobante (sin fallback si falla); migración del permiso `finanzas_prefactura_generar` |
| #482 (pieza) | `a6d97387` | `app:send-all-emails-command` ignora pendientes de más de 7 días (`config('billing.correos_pendientes_max_dias')`) |
| #479 + ttyd (otra sesión) | `2fa5e100` `2abb1a8a` `93eeb271` `120766d7` | DevTools: terminal ttyd en loopback con auth por sesión/rol; pestañas tmux |
| docs | bitácoras | — |

**Migraciones (2, aditivas):**
1. `2026_10_16_170000_ajusta_plantilla_prefactura_para_provider` — ajusta «Prefactura.» SOLO si su
   sha1 es el esperado (`24ee6f55…`); si no, **no falla**: no-op + `Log::warning` + item en la Hoja
   de Ruta. `down()` restaura el original embebido.
2. `2026_10_16_180000_crea_permiso_finanzas_prefactura_generar` — crea el permiso y lo da solo a
   super-administrator y DESARROLLADOR (el deploy no corre `permissions:sync-roles`).

**Sin cambios de `.env`.** Config nueva sin `env()`: `billing.correo_facturacion`,
`billing.correos_pendientes_max_dias`.

### Decisiones antes de publicar
1. **¿Viajan los 4 commits de DevTools de la otra sesión?** En prod son inertes si allá no hay
   ttyd/tmux configurado. Si NO deben ir: release parcial (rama `release/V1.61` desde
   `V1.60-15.10.2026` + cherry-pick de lo nuestro; ver memoria «Publicar solo parte de main»).
2. Confirmar que `main` no recibió commits ajenos entre esta preparación y el clic de publicar.

### Checklist de deploy en PROD (.108, `/var/www/ClaudeMegaISP`, `meganet_prod_claude`)

Lo hace `remote:deploy` al «Buscar actualizaciones → Aplicar»; marcar cada paso al verlo en el log:
- [ ] **Respaldo BD** (`backup_db`, automático). Opcional: uno manual antes, `php artisan backup_db:process`, y anotar el archivo de `/var/backups/mysql/`.
- [ ] **Código** (`git_sync` al tag V1.61).
- [ ] **Dry-run de migraciones**: deben salir las 2 de arriba como pendientes. Exit 2 (sin GRANT `{db}_dryrun`) solo omite, no bloquea.
- [ ] **Build** (`npm ci && npm run prod`).
- [ ] **Migrate** (candado de hash): en `storage/logs/laravel-<fecha>.log` buscar `[Prefactura] Plantilla: Plantilla actualizada.`
      Si sale `La plantilla NO se actualizó`, correr `php artisan prefactura:actualizar-plantilla --dry-run`, revisar el diff y aplicar con `--force`.
- [ ] **Cachés** (`optimize` = config/route/view clear + view:cache, **sin config:cache**; además está bloqueado por #496).
- [ ] **queue:restart** (imprescindible: cambió `StandardMail`, que usan los workers).

Verificación después del deploy:
- [ ] `php artisan prefactura:actualizar-plantilla --dry-run` → `Situación: ya_aplicada`.
- [ ] `php artisan permission:cache-reset` (la caché de permisos es por checkout).
- [ ] `SELECT r.name FROM permissions p JOIN role_has_permissions rp ON rp.permission_id=p.id JOIN roles r ON r.id=rp.role_id WHERE p.name='finanzas_prefactura_generar';` → super-administrator, DESARROLLADOR.
- [ ] Botón **Prefactura actualizada** en una factura pendiente (chip ámbar) y en una pagada (chip verde, «Pago recibido»).
- [ ] Editor de plantillas: «Prefactura.» → Cargar → Previsualizar (con logo).
- [ ] **Enviar**: NO probar con clientes reales. Abrir el diálogo en una factura pagada y cancelar
      (debe decir «se enviará como comprobante PAGADO»). El envío real ya se probó en dev
      (2 correos recibidos en inving9378@gmail.com, PDF 28.5 KB).
- [ ] Durante la verificación: `tail -f storage/logs/laravel-$(date +%F).log | grep -i prefactura`.

Después (no bloquean la release): **#503** reponer «Pagada» a ~10 facturas (escritura en prod con
bitácora previa + respaldo + OK), **#482** Fase 3 (cancelar 32,500 `invoice_emails` y la cola de
`billing_notifications`; NO quitar `envio_pausado` antes), **#496** `env()` de MonitoreoOficial.

**Si algo falla:** un fallo en `migrate` deja el deploy `failed` sin rollback de código (diseño del
pipeline). La plantilla anterior está embebida en su migración y en el respaldo; con la plantilla
ajustada, `php artisan migrate:rollback --path=database/migrations/2026_10_16_170000_ajusta_plantilla_prefactura_para_provider.php` la restaura.
