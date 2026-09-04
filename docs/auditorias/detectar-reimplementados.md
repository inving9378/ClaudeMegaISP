# Detección de re-implementación silenciosa (#9990346)

> Generado automáticamente por `php artisan circuito:detectar-reimplementados` el 2026-09-04 17:14:40.
> **READ-ONLY**: no cierra items, no mergea, no crea items, no borra ramas. Cada fila trae su
> `git diff main...<rama>` para verificar a mano — la confirmación siempre es humana.

Universo escaneado (completado, con rama propia, sin `merge_commit`, no archivado): 25.

## Duplicado probable

Señal fuerte: otro item ya completado y mergeado declara parentesco (`origen_item_id`) o lo
menciona en su título.

| Item | Título | Señal | Reemplazado por |
|---|---|---|---|
| #672 | Pieza 1 — Instrumentar la válvula: contador de aperturas de frontera … | origen_item_id | #764 (9241aedef1534119ef63bc4fac10adac36533809) |
| #705 | Vigilante on-box (#208) — invariantes de Cola y discrepancias Sistema… | origen_item_id, titulo | #771 (35a6901f6497c5bcbc81d60e0db0345160f84a04) \| #772 (d098bed97e119a48e1b384134d6dc41afe5fe4a6) \| #773 (a86d94d859729c098f538796463897db155fabd7) \| #774 (8827b6e5612434c1c3642b5b8a696e6b429bc6ff) \| #775 (79bb2b9dde0f2768fa91015415e26ce0f0cc039a) |
| #734 | DocumentacionCorporativa Fase 2a — Repositorio documental: carga, ver… | origen_item_id, archivos | #767 (00759663a5ec5a19f7150d720dd0c787dd479746) \| archivos en común: app/Modules/Addons/DocumentacionCorporativa/Controllers/DocumentoController.php, app/Modules/Addons/DocumentacionCorporativa/Models/DcDocumento.php, app/Modules/Addons/DocumentacionCorporativa/Models/DcDocumentoVersion.php, app/Modules/Addons/DocumentacionCorporativa/Resolvers/DocumentoResolver.php, app/Modules/Addons/DocumentacionCorporativa/Services/CompletitudService.php… |
| #740 | Deriva de esquema #216 — Fase 3: consumidores + entregable final + ci… | origen_item_id | #807 (685fcabb94d49f63aec5cc05060085026bdbc089) |
| #759 | DocumentaciónCorporativa Fase 5b — servicio de armado de entrega (ZIP… | origen_item_id, archivos | #810 (dad06ae810e2a0d890d964247b41946840c26112) \| archivos en común: app/Modules/Addons/DocumentacionCorporativa/Models/DcEntrega.php, app/Modules/Addons/DocumentacionCorporativa/Models/DcEntregaItem.php, app/Modules/Addons/DocumentacionCorporativa/Models/DcSolicitud.php, app/Modules/Addons/DocumentacionCorporativa/migrations/2026_08_29_150000_create_dc_solicitudes_table.php, app/Modules/Addons/DocumentacionCorporativa/migrations/2026_08_29_150100_create_dc_entregas_tables.php |
| #806 | Jarvis Parte 3b — Decidir dónde vive 'el chat' de las sugerencias (br… | origen_item_id, git, archivos | #826 (502daa600d988395553ed310687653ae2b645778) \| commit 4204a47b3e: docs(circuito#713): documenta el bucle reap sobre el paragu… \| archivos en común: app/Modules/Addons/Roadmap/Controllers/JarvisChatController.php, app/Modules/Addons/Roadmap/Models/JarvisConversacion.php, app/Modules/Addons/Roadmap/Models/JarvisMensaje.php, app/Modules/Addons/Roadmap/Services/JarvisChatService.php, app/Modules/Addons/Roadmap/migrations/2026_08_29_220000_crea_jarvis_chat_tablas.php… |
| #833 | Fase 1a-ii parte 3/N: resolver colisión failed_jobs (aislado de #831)… | origen_item_id, titulo, git, archivos | #9990204 (f1d680efc11d4b3da37241ea3eac194ce9f3af8c) \| #507 (8cc5081d2e3cee62414f8da51ce68179c60d6567) \| commit 185f73232f: docs(circuito#830): cierra el bucle reap sobre el paraguas … \| archivos en común: database/migrations/2026_05_27_140743_create_failed_jobs_table.php, database/migrations/2026_08_28_970000_add_review_flags_and_unique_client_invoice_id_to_invoices_table.php |
| #9990076 | Fase 1 - Repro secuencial (mismo proceso) del cascade sobre #32 + ins… | origen_item_id, git | #9990099 (9d58a39d8db264661b06d9eb0da710518762c894) \| commit d6b6bb728b: docs(circuito#9990223): Fase 1b ya resuelta por cadena para… |
| #9990328 | MR-06a — Backend: portar los 6 controllers Geo (grupo vivo) a MapaRed… | origen_item_id | #9990333 (c7bc73fe97d7d10e37298bd1ef7f4984c8f380fb) |


## Sospecha

Señal débil (solo mención en un commit de main, o solapamiento de archivos) — no es suficiente
para concluir duplicado sin mirar el diff.

| Item | Título | Señal | Detalle |
|---|---|---|---|
| #758 | DocumentaciónCorporativa Fase 5a — dc_solicitudes: correr migraciones… | git, archivos | commit 4615c1f511: feat(circuito#760): trae modelos/migraciones dc_solicitudes… \| archivos en común: app/Modules/Addons/DocumentacionCorporativa/Controllers/DcSolicitudController.php, app/Modules/Addons/DocumentacionCorporativa/Models/DcActivoDigital.php, app/Modules/Addons/DocumentacionCorporativa/Models/DcEntrega.php, app/Modules/Addons/DocumentacionCorporativa/Models/DcEntregaItem.php, app/Modules/Addons/DocumentacionCorporativa/Models/DcInventarioAcceso.php… |


## Sin señal

15 item(s) del universo sin ninguna de las 4 señales — no significa que
estén bien, sólo que este detector no encontró indicio de re-implementación.