# Estrategia de datos en dev — Fase 1: inventario de tablas vacías + bucketing mecánico

Item #9990479 (sub-item de #9990478). SOLO LECTURA — no se importó, sembró ni borró nada.

## Metodología

Barrido reproducido con:

```sql
SELECT EXISTS(SELECT 1 FROM `<tabla>` LIMIT 1)
```

por cada tabla `BASE TABLE` del schema activo de dev (`information_schema.TABLES`, excluyendo
vistas y la tabla `migrations`). No se tocó ninguna fila.

- **Tablas base totales:** 561
- **Tablas vacías medidas:** **223** (coincide con el número esperado por el item padre #9990478)
- Sin errores de lectura en el barrido (0 tablas ilegibles/corruptas).

La clasificación de este documento es **puramente mecánica por patrón de nombre** (prefijo/sufijo
+ pertenencia obvia de módulo por el propio nombre), sin abrir código de controladores/modelos —
eso es explícitamente fase 2/3 del item padre. Las reglas usadas:

| Bucket | Regla aplicada |
|---|---|
| **EN CONSTRUCCIÓN** | prefijo `mapared_`, `ipv6_` (salvo `ipv6_policies`, ver abajo), `dc_` |
| **NUNCA USADA EN DEV** | prefijo `ia_`, `whatsapp_`, `marketing_`, `parental_`, `cobranza_`, `talento_`; + `voip_configuracion` (4ª tabla del mismo módulo CobranzaBlaster, documentada junto a las `cobranza_*` en CLAUDE.md) y `megafamilia_settings` (mismo módulo que las `parental_*`, MegaFamilia) |
| **RED SIN MAPEAR** | equipo/infraestructura física de red de fibra por nombre obvio: `fibers`, `points`/`point_accessories`, `poles`/`pole_accessories`, `ports`, `racks`, `boxes`/`box_inputs`, `buffers`, `cut_fibers`, `cuts_observations`, `modems`, `passive_equipments`, `active_equipments`/`active_equipment_peripherals`, `splitters`, `transceivers`, `trays`, `trenches`, `tubes` |
| **CATÁLOGO** | explícitas del item (`brands`, `colors`, `contratable_packages`, `contratable_services`, `positions`, `social_providers`, `ipv6_policies`) + cualquier tabla `*_types` |
| **ambigua-fase3** | no encaja claramente en ninguno de los 4 anteriores — se deja sin forzar, para fase 3 |

**Excepción explícita señalada por el propio item:** `ipv6_policies` cae por prefijo en el patrón
`ipv6_*` (EN CONSTRUCCIÓN), pero el item la nombra textualmente como ejemplo de CATÁLOGO — se
clasificó como CATÁLOGO, no como EN CONSTRUCCIÓN, siguiendo la instrucción explícita del spec
sobre el patrón genérico.

## Resumen por bucket

| Bucket | Tablas |
|---|---:|
| EN CONSTRUCCIÓN | 36 |
| NUNCA USADA EN DEV | 80 |
| RED SIN MAPEAR | 21 |
| CATÁLOGO | 13 |
| **ambigua-fase3** | **73** |
| **TOTAL** | **223** |

## Referencias ya resueltas (contexto de #9990471, no se re-investigan aquí)

Tres tablas de la lista ya tienen veredicto documentado por el item #9990471 (cerrado); se listan
en `ambigua-fase3` de este inventario porque no encajan por *nombre* en ninguno de los 4 buckets
mecánicos, pero **no están realmente pendientes** — su resolución ya existe:

- **`sales`** — descontinuada (0 consumidores en código; la UI real de Vendedores lee de
  `client_main_information`).
- **`prospects`** — descontinuada (0 consumidores en código; Prospectos real lo sirve el módulo
  CRM, `crm_lead_information`/`crm_main_information`).
- **`commissions`** — huérfana: su hija `commissions_details` tiene **566,780 filas reales**, pero
  el listener que puebla `commissions` (`CalculateClientCommission`/`CalculateProspectCommission`)
  tiene el cuerpo comentado. No se puede afirmar si es "falta importar de prod" o "descontinuada
  tras jul-2024" sin que Irving confirme el estado en prod (ver detalle completo en
  `docs/vendedores-sales-commissions-prospects-item-9990471-verificacion.md`).

## Tabla completa (223 tablas)

| Tabla | Bucket |
|---|---|
| `active_equipment_peripherals` | RED SIN MAPEAR |
| `active_equipment_types` | CATÁLOGO |
| `active_equipments` | RED SIN MAPEAR |
| `auditoria_minero_cursores` | ambigua-fase3 |
| `auditoria_senales` | ambigua-fase3 |
| `billing_addresses` | ambigua-fase3 |
| `box_inputs` | RED SIN MAPEAR |
| `box_types` | CATÁLOGO |
| `boxes` | RED SIN MAPEAR |
| `brands` | CATÁLOGO |
| `buffers` | RED SIN MAPEAR |
| `cards` | ambigua-fase3 |
| `change_plan_voz_clients` | ambigua-fase3 |
| `client_cfdi_invoices` | ambigua-fase3 |
| `client_contratable_subscriptions` | ambigua-fase3 |
| `client_fiscal_data` | ambigua-fase3 |
| `client_invoice_cfdi` | ambigua-fase3 |
| `client_payment_promises` | ambigua-fase3 |
| `client_payment_services` | ambigua-fase3 |
| `client_recurring_cards` | ambigua-fase3 |
| `client_serviceables` | ambigua-fase3 |
| `cobranza_campanas` | NUNCA USADA EN DEV |
| `cobranza_llamada_eventos` | NUNCA USADA EN DEV |
| `cobranza_llamadas` | NUNCA USADA EN DEV |
| `colors` | CATÁLOGO |
| `commissions` | ambigua-fase3 (ver "Referencias ya resueltas" — huérfana, #9990471) |
| `conciliation_settings` | ambigua-fase3 |
| `contratable_packages` | CATÁLOGO |
| `contratable_services` | CATÁLOGO |
| `credential_images` | ambigua-fase3 |
| `cut_fibers` | RED SIN MAPEAR |
| `cuts_observations` | RED SIN MAPEAR |
| `dc_accionistas` | EN CONSTRUCCIÓN |
| `dc_actas` | EN CONSTRUCCIÓN |
| `dc_activos` | EN CONSTRUCCIÓN |
| `dc_activos_digitales` | EN CONSTRUCCIÓN |
| `dc_capital_variaciones` | EN CONSTRUCCIÓN |
| `dc_concesion_pagos` | EN CONSTRUCCIÓN |
| `dc_concesiones` | EN CONSTRUCCIÓN |
| `dc_contratos` | EN CONSTRUCCIÓN |
| `dc_documento_versiones` | EN CONSTRUCCIÓN |
| `dc_documentos` | EN CONSTRUCCIÓN |
| `dc_entrega_items` | EN CONSTRUCCIÓN |
| `dc_entregas` | EN CONSTRUCCIÓN |
| `dc_inventario_accesos` | EN CONSTRUCCIÓN |
| `dc_poderes` | EN CONSTRUCCIÓN |
| `dc_proveedor_clasificaciones` | EN CONSTRUCCIÓN |
| `deal_crms` | ambigua-fase3 |
| `demo_items` | ambigua-fase3 |
| `discounts` | ambigua-fase3 |
| `discounts_sales` | ambigua-fase3 |
| `distribution_commission_sales` | ambigua-fase3 |
| `distribution_commission_sales_amount` | ambigua-fase3 |
| `enrollment_links` | ambigua-fase3 |
| `equipment_links` | ambigua-fase3 |
| `evaluaciones_empresariales` | ambigua-fase3 |
| `fibers` | RED SIN MAPEAR |
| `fleet_device_events` | ambigua-fase3 |
| `fleet_document_ocr_runs` | ambigua-fase3 |
| `fleet_driver_push_tokens` | ambigua-fase3 |
| `general_notifications` | ambigua-fase3 |
| `ia_bot_leads` | NUNCA USADA EN DEV |
| `ia_chat_conversations` | NUNCA USADA EN DEV |
| `ia_conversaciones` | NUNCA USADA EN DEV |
| `ia_memoria_proyecto` | NUNCA USADA EN DEV |
| `ia_mensajes` | NUNCA USADA EN DEV |
| `ia_message_files` | NUNCA USADA EN DEV |
| `ia_notas_proyecto` | NUNCA USADA EN DEV |
| `ia_prompts_usuario` | NUNCA USADA EN DEV |
| `ia_proveedores` | NUNCA USADA EN DEV |
| `ia_proyectos` | NUNCA USADA EN DEV |
| `ia_sesiones_trabajo` | NUNCA USADA EN DEV |
| `ia_tareas` | NUNCA USADA EN DEV |
| `ia_uso_tokens` | NUNCA USADA EN DEV |
| `invoice_serviceables` | ambigua-fase3 |
| `ipv6_bloques` | EN CONSTRUCCIÓN |
| `ipv6_dual_stack_cutoff_dry_runs` | EN CONSTRUCCIÓN |
| `ipv6_plan_segmentos` | EN CONSTRUCCIÓN |
| `ipv6_policies` | CATÁLOGO (excepción explícita del item, ver Metodología) |
| `ipv6_policy_assignments` | EN CONSTRUCCIÓN |
| `ipv6_renumbering_plans` | EN CONSTRUCCIÓN |
| `ipv6_renumbering_transitions` | EN CONSTRUCCIÓN |
| `ipv6_routers` | EN CONSTRUCCIÓN |
| `jarvis_conversaciones` | ambigua-fase3 |
| `jarvis_mensajes` | ambigua-fase3 |
| `manual_pages` | ambigua-fase3 |
| `manual_screenshots` | ambigua-fase3 |
| `map_links` | ambigua-fase3 |
| `map_routes` | ambigua-fase3 |
| `mapared_cables` | EN CONSTRUCCIÓN |
| `mapared_correlativos` | EN CONSTRUCCIÓN |
| `mapared_devices_ports_connections` | EN CONSTRUCCIÓN |
| `mapared_empalmes` | EN CONSTRUCCIÓN |
| `mapared_enlaces_servicio` | EN CONSTRUCCIÓN |
| `mapared_fibers` | EN CONSTRUCCIÓN |
| `mapared_fibers_cut` | EN CONSTRUCCIÓN |
| `mapared_hilos` | EN CONSTRUCCIÓN |
| `mapared_layers` | EN CONSTRUCCIÓN |
| `mapared_layers_routes` | EN CONSTRUCCIÓN |
| `mapared_ports` | EN CONSTRUCCIÓN |
| `mapared_proyects` | EN CONSTRUCCIÓN |
| `mapared_puertos` | EN CONSTRUCCIÓN |
| `mapared_splitters` | EN CONSTRUCCIÓN |
| `marketing_attributions` | NUNCA USADA EN DEV |
| `marketing_campaigns` | NUNCA USADA EN DEV |
| `marketing_content_templates` | NUNCA USADA EN DEV |
| `marketing_lead_forms` | NUNCA USADA EN DEV |
| `marketing_lead_pipeline` | NUNCA USADA EN DEV |
| `marketing_lead_sources` | NUNCA USADA EN DEV |
| `marketing_pipeline_stages` | NUNCA USADA EN DEV |
| `marketing_pipelines` | NUNCA USADA EN DEV |
| `marketing_publication_logs` | NUNCA USADA EN DEV |
| `marketing_publications` | NUNCA USADA EN DEV |
| `megafamilia_settings` | NUNCA USADA EN DEV |
| `migration_logs` | ambigua-fase3 |
| `mikrotik_client_hostpot_radius` | ambigua-fase3 |
| `mikrotik_client_hostpot_users` | ambigua-fase3 |
| `modems` | RED SIN MAPEAR |
| `orphan_client_backfill_log` | ambigua-fase3 |
| `parental_alerts` | NUNCA USADA EN DEV |
| `parental_app_blocks` | NUNCA USADA EN DEV |
| `parental_consents` | NUNCA USADA EN DEV |
| `parental_locations` | NUNCA USADA EN DEV |
| `parental_task_assignments` | NUNCA USADA EN DEV |
| `parental_web_blocks` | NUNCA USADA EN DEV |
| `passive_equipment_types` | CATÁLOGO |
| `passive_equipments` | RED SIN MAPEAR |
| `password_resets` | ambigua-fase3 |
| `payment_accounts` | ambigua-fase3 |
| `payment_clabes` | ambigua-fase3 |
| `payment_instruments` | ambigua-fase3 |
| `payment_promises` | ambigua-fase3 |
| `payment_receipts` | ambigua-fase3 |
| `payment_webhooks_log` | ambigua-fase3 |
| `payments_details` | ambigua-fase3 |
| `payments_sellers` | ambigua-fase3 |
| `plan_custom_client` | ambigua-fase3 |
| `point_accessories` | RED SIN MAPEAR |
| `points` | RED SIN MAPEAR |
| `pole_accessories` | RED SIN MAPEAR |
| `poles` | RED SIN MAPEAR |
| `portal_profile_change_log` | ambigua-fase3 |
| `ports` | RED SIN MAPEAR |
| `positions` | CATÁLOGO |
| `project_types` | CATÁLOGO |
| `prospects` | ambigua-fase3 (ver "Referencias ya resueltas" — descontinuada, #9990471) |
| `push_tokens` | ambigua-fase3 |
| `quote_crms` | ambigua-fase3 |
| `racks` | RED SIN MAPEAR |
| `radius_sessions` | ambigua-fase3 |
| `reconciliation_tickets` | ambigua-fase3 |
| `recurring_charge_attempts` | ambigua-fase3 |
| `referral_share_logs` | ambigua-fase3 |
| `release_snapshots` | ambigua-fase3 |
| `reported_payments` | ambigua-fase3 |
| `role_permission_scopes` | ambigua-fase3 |
| `sales` | ambigua-fase3 (ver "Referencias ya resueltas" — descontinuada, #9990471) |
| `setting_table` | ambigua-fase3 |
| `sites` | ambigua-fase3 |
| `social_providers` | CATÁLOGO |
| `splitters` | RED SIN MAPEAR |
| `system_users` | ambigua-fase3 |
| `tables` | ambigua-fase3 |
| `talento_activity_report_participants` | NUNCA USADA EN DEV |
| `talento_caja_baselines` | NUNCA USADA EN DEV |
| `talento_caja_inspections` | NUNCA USADA EN DEV |
| `talento_certifications` | NUNCA USADA EN DEV |
| `talento_compensation_rule_history` | NUNCA USADA EN DEV |
| `talento_compensation_rules` | NUNCA USADA EN DEV |
| `talento_credentials` | NUNCA USADA EN DEV |
| `talento_device_tokens` | NUNCA USADA EN DEV |
| `talento_devices` | NUNCA USADA EN DEV |
| `talento_employee_documents` | NUNCA USADA EN DEV |
| `talento_escalafon_config` | NUNCA USADA EN DEV |
| `talento_exam_attempts` | NUNCA USADA EN DEV |
| `talento_exam_questions` | NUNCA USADA EN DEV |
| `talento_exams` | NUNCA USADA EN DEV |
| `talento_funds` | NUNCA USADA EN DEV |
| `talento_installation_surveys` | NUNCA USADA EN DEV |
| `talento_ledger_entries` | NUNCA USADA EN DEV |
| `talento_level_assignments` | NUNCA USADA EN DEV |
| `talento_loans` | NUNCA USADA EN DEV |
| `talento_location_pings` | NUNCA USADA EN DEV |
| `talento_penalties` | NUNCA USADA EN DEV |
| `talento_penalty_appeals` | NUNCA USADA EN DEV |
| `talento_portal_preferences` | NUNCA USADA EN DEV |
| `talento_practical_evaluations` | NUNCA USADA EN DEV |
| `talento_project_activities` | NUNCA USADA EN DEV |
| `talento_project_activity_reports` | NUNCA USADA EN DEV |
| `talento_project_deviations` | NUNCA USADA EN DEV |
| `talento_projects` | NUNCA USADA EN DEV |
| `talento_puesto_document_templates` | NUNCA USADA EN DEV |
| `talento_responsibility_windows` | NUNCA USADA EN DEV |
| `talento_route_deviations` | NUNCA USADA EN DEV |
| `talento_route_stops` | NUNCA USADA EN DEV |
| `talento_routes` | NUNCA USADA EN DEV |
| `talento_settlement_items` | NUNCA USADA EN DEV |
| `talento_settlements` | NUNCA USADA EN DEV |
| `talento_shift_extensions` | NUNCA USADA EN DEV |
| `talento_warranty_overrides` | NUNCA USADA EN DEV |
| `talento_work_order_activations` | NUNCA USADA EN DEV |
| `talento_work_order_ia_validations` | NUNCA USADA EN DEV |
| `talento_work_order_media` | NUNCA USADA EN DEV |
| `talento_work_order_signatures` | NUNCA USADA EN DEV |
| `task_closures` | ambigua-fase3 |
| `transceivers` | RED SIN MAPEAR |
| `trays` | RED SIN MAPEAR |
| `trenche_types` | CATÁLOGO |
| `trenches` | RED SIN MAPEAR |
| `tube_types` | CATÁLOGO |
| `tubes` | RED SIN MAPEAR |
| `user_column_dt_expand` | ambigua-fase3 |
| `user_relationships` | ambigua-fase3 |
| `vigilante_discrepancias` | ambigua-fase3 |
| `voip_configuracion` | NUNCA USADA EN DEV |
| `warroom_action_items` | ambigua-fase3 |
| `warroom_meeting_notes` | ambigua-fase3 |
| `whatsapp_conversations` | NUNCA USADA EN DEV |
| `whatsapp_identification_sessions` | NUNCA USADA EN DEV |
| `whatsapp_instance_functions` | NUNCA USADA EN DEV |
| `whatsapp_messages` | NUNCA USADA EN DEV |
| `whatsapp_payment_extractions` | NUNCA USADA EN DEV |
| `work_flows` | ambigua-fase3 |

## Notas sobre el bucket `ambigua-fase3` (73 tablas)

Deliberadamente **no forzadas** a un bucket, tal como pide el spec. A simple vista (solo por
nombre, sin abrir código) se agrupan en familias temáticas reconocibles que la fase 3 puede usar
como punto de partida — **esto es una observación, no una clasificación**:

- **Fiscal/CFDI/facturación:** `client_cfdi_invoices`, `client_fiscal_data`, `client_invoice_cfdi`,
  `billing_addresses`, `invoice_serviceables`, `discounts`, `discounts_sales`.
- **Pagos/OpenPay/conciliación (fuera de lo ya cubierto en `reported_payments`/whatsapp del padre
  #9990471):** `payment_accounts`, `payment_clabes`, `payment_instruments`, `payment_promises`,
  `payment_receipts`, `payment_webhooks_log`, `payments_details`, `payments_sellers`,
  `client_payment_promises`, `client_payment_services`, `client_recurring_cards`,
  `recurring_charge_attempts`, `conciliation_settings`, `reconciliation_tickets`,
  `reported_payments`, `role_permission_scopes`.
- **CRM (aparte del módulo CRM activo):** `deal_crms`, `quote_crms`.
- **Comisiones/distribución (familia de `commissions`):** `distribution_commission_sales`,
  `distribution_commission_sales_amount`.
- **Flotas (features documentadas como parciales/pendientes en CLAUDE.md):**
  `fleet_device_events`, `fleet_document_ocr_runs`, `fleet_driver_push_tokens`.
- **Mapa de red — posibles tablas legacy pre-`mapared_*`:** `map_links`, `map_routes`.
- **JARVIS (asistente del circuito):** `jarvis_conversaciones`, `jarvis_mensajes`.
- **Documentación/manual del sistema:** `manual_pages`, `manual_screenshots`.
- **Mikrotik/RADIUS hotspot:** `mikrotik_client_hostpot_radius`, `mikrotik_client_hostpot_users`,
  `radius_sessions`.
- **War Room (addon, mismo perfil que MegaFamilia/Embajadores):** `warroom_action_items`,
  `warroom_meeting_notes`.
- **Auditoría/circuito interno:** `auditoria_minero_cursores`, `auditoria_senales`,
  `migration_logs`, `orphan_client_backfill_log`, `release_snapshots`, `vigilante_discrepancias`.
- **Framework/genéricas de bajo contexto:** `password_resets` (tabla estándar de Laravel; el
  sistema usa auth propio base64→bcrypt, no el flujo nativo — ver `PasswordService`),
  `user_column_dt_expand`, `user_relationships`, `system_users`, `setting_table`, `tables`,
  `general_notifications`, `push_tokens`, `enrollment_links`, `equipment_links`, `credential_images`,
  `demo_items`, `cards`, `change_plan_voz_clients`, `client_contratable_subscriptions`,
  `client_serviceables`, `evaluaciones_empresariales`, `referral_share_logs`, `sites`,
  `plan_custom_client`, `task_closures`, `work_flows`, `portal_profile_change_log` (esta última
  **sí tiene código real** — feature de audit-trail del Portal Cliente documentada en CLAUDE.md,
  commit `0329a1d` — simplemente nadie ha editado un perfil en dev todavía).

## Próximo paso

Fase 2/3 del item padre #9990478: investigar código (controladores/modelos/rutas) para las 73
`ambigua-fase3`, y decidir si `commissions` es "falta importar de prod" o "descontinuada" (requiere
confirmación de Irving sobre el estado de esa tabla en prod, según ya señaló #9990471).
