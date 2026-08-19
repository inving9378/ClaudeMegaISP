# Fase 2B · Paso 0 — ¿contra qué mediría `medirContraSpec()`?

> **Medido el 2026-08-18** sobre los 43 `module.json` de `app/Modules/`.
> Reproducible en cualquier momento: **`php artisan circuito:inventario-spec --detalle`**
> (READ-ONLY). Las cifras de abajo son de esa corrida; si envejecen, vuelve a correrlo — por eso es
> un comando y no sólo una tabla pegada aquí.
>
> **Pregunta de Irving:** *"si la mayoría declara poco, el primer trabajo real es enriquecer las
> declaraciones, no escribir el detector."* Ésta es la respuesta con números.

## Veredicto

**No están vacíos, pero cubren un tercio del sistema y una parte de lo declarado ya no es cierto.**
Escribir el detector AHORA es correcto para un subconjunto acotado; lanzarlo sobre los 43 módulos
generaría más ruido que trabajo.

- Hay **117 endpoints declarados** contra **2,117 rutas atribuibles a un módulo** → el spec describe
  **5.5 %** de la superficie. Un detector "declarado vs real" mide un recorte, no el sistema.
  *(Cifra afinada después de la primera medición: el denominador correcto excluye HEAD y las 133
  rutas de controllers legacy fuera de `app/Modules`, que no pertenecen a ningún manifiesto y no
  pueden declararse por esta vía. Es el techo honesto de este mecanismo.)*
- **26/43 módulos declaran `api_endpoints`**; 17 lo traen vacío o ausente.
- **Sólo 9/43 declaran `screens`** (28 pantallas en total) — y ésas sí traen `steps`, `actions` y
  `terms` de calidad. Es el campo más rico y el menos poblado.
- **11 módulos están casi vacíos** (0 endpoints, 0 screens, 0 permisos), casi todos `Core`:
  Auth, Dashboard, Layout, Usuarios, Release, Auditoria, Documentacion, PortalCliente…

## Lo declarado, ¿es cierto?

| Señal | Declarado | Corresponde con la realidad |
|---|---:|---|
| `api_endpoints` → ruta registrada (método + path) | 117 | **101 (86 %)** |
| `permission` de cada endpoint → fila en `permissions` | 111 | **111 (100 %)** ✅ |
| `screens[].url` → ruta GET registrada | 28 | **22 (79 %)** |

**El detector de permisos es el único con confianza ~1.0 hoy** — 100 % de acierto, cero ruido.

Los desajustes de ruta son **reales, no artefactos de normalización** (verificados a mano):

- **Flotas** declara `/api/flotas/vehiculos`; lo que existe son **65 rutas bajo `flotas/api/…`**.
  El prefijo está invertido en la declaración.
- **Planes** declara `/planes/internet`, `/planes/voz`, `/planes/custom`, `/planes/paquetes`;
  lo que existe es `paquetes`, `planes/contratables`, … La declaración describe un esquema de URLs
  que nunca se construyó así.

## La trampa de diseño de 2B

Un endpoint declarado sin ruta significa **una de dos cosas, y el lookup no las distingue**:

1. **se declaró y no se construyó** → gap real, hay que construirlo; o
2. **se construyó distinto y la declaración envejeció** → el que está mal es el `module.json`.

Flotas y Planes son del tipo (2). Si el detector emite *"falta construir X"*, va a fabricar trabajo
para reconstruir cosas que ya existen con otro nombre.

> **Regla para el detector:** el hallazgo se llama **«declaración y realidad no coinciden»**, y la
> primera pregunta del item es *cuál de las dos se corrige*. Nunca *"falta construir"*.

Es el mismo principio de toda la fase 2A: una señal que no distingue sus dos causas se lee mal, y se
lee mal en la dirección cómoda.

## Qué se implementó a partir de esto (2026-08-18)

La conclusión de Irving sobre estos números: *"la pregunta ya no es cómo escribo el detector
semántico, es **por qué el sistema no tiene con qué medirse**"*. El primer producto del generador no
son huecos de código sino **huecos de declaración** — ver [`circuito/directiva-2b.md`](circuito/directiva-2b.md).

`AuditorService::medirContraSpec()` implementado (`c1aa78fd`) con cuatro detectores por lookup.
**DRY-RUN: 41 gaps.**

| detector | gaps |
|---|---:|
| `spec_declaracion_incompleta` | 21 |
| `spec_modulo_sin_declarar` | 15 |
| `spec_desalineada` | 5 |
| `spec_permiso_inexistente` | **0** — lo esperado: es guardia contra regresiones, no generador |

⚠️ **14 de los 41 caen en módulos que `circuito.auditor.carriles` no recorre**, y dos entradas de esa
config no resuelven a ningún directorio (`Roadmap / Circuito CC` es un *footprint*, no un nombre de
módulo; `Reportes` no existe) — el motor las audita en vacío sin avisar, así que **el módulo del
propio circuito nunca se ha auditado**. Registrado como item **#809**.

## Recomendación de arranque

1. **Detector de permisos declarados** — confianza 1.0, 0 % de ruido medido. Empezar aquí.
2. **Detector de rutas**, emitiendo *"declaración ≠ realidad"*, acotado a los **26 módulos que
   declaran endpoints**. Los 16 hallazgos actuales son la primera cosecha, y son verdaderos.
3. **Enriquecer `screens` antes de detectar contra ellas**: 9/43 no da para medir nada, y es el
   campo con mejor material (`steps`/`actions`/`terms` ya redactados) donde sí existe.
4. **Los 11 módulos casi vacíos son `Core`**: declararlos es un trabajo aparte, y probablemente
   manual — no hay de dónde inferirlo.

## Inventario completo

| módulo | tipo | endp | perm | menu | card | cfg | scr | steps | actions |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Vendedores | Addons | 16 | 35 | 1 | 1 | 2 | 5 | 7 | 7 |
| Talento | Addons | 12 | 42 | 1 | 1 | 0 | 0 | 0 | 0 |
| Roadmap | Addons | 7 | 2 | 0 | 0 | 0 | 1 | 0 | 0 |
| Flotas | Addons | 7 | 16 | 1 | 1 | 1 | 0 | 0 | 0 |
| CobranzaBlaster | Addons | 6 | 3 | 1 | 1 | 2 | 0 | 6 | 6 |
| Marketing | Addons | 6 | 13 | 1 | 1 | 3 | 0 | 10 | 9 |
| WarRoom | Addons | 5 | 7 | 1 | 1 | 0 | 0 | 0 | 0 |
| WhatsAppAgent | Addons | 5 | 6 | 1 | 0 | 2 | 0 | 6 | 5 |
| Finanzas | Addons | 4 | 22 | 1 | 1 | 2 | 4 | 6 | 7 |
| Planes | Addons | 4 | 21 | 1 | 1 | 1 | 4 | 4 | 4 |
| Embajadores | Addons | 4 | 7 | 1 | 1 | 1 | 0 | 3 | 3 |
| EvaluadorEmpresarial | Addons | 4 | 3 | 1 | 1 | 0 | 0 | 0 | 0 |
| GestionRed | Addons | 4 | 22 | 1 | 1 | 2 | 0 | 6 | 7 |
| Inventario | Addons | 3 | 29 | 1 | 1 | 1 | 4 | 4 | 4 |
| Tickets | Addons | 3 | 16 | 1 | 1 | 1 | 3 | 3 | 4 |
| Hub | Addons | 3 | 1 | 1 | 0 | 1 | 0 | 4 | 4 |
| IA | Addons | 3 | 12 | 1 | 1 | 2 | 0 | 6 | 6 |
| Manual | Addons | 3 | 2 | 1 | 1 | 1 | 0 | 3 | 3 |
| Payments | Addons | 3 | 6 | 1 | 1 | 2 | 0 | 4 | 3 |
| Scheduling | Addons | 3 | 33 | 1 | 1 | 1 | 0 | 3 | 4 |
| SmartImportExport | Addons | 3 | 4 | 1 | 0 | 3 | 0 | 10 | 8 |
| Mapas | Addons | 2 | 44 | 1 | 1 | 1 | 2 | 3 | 1 |
| DevTools | Addons | 2 | 0 | 1 | 1 | 1 | 0 | 3 | 3 |
| MegaFamilia | Addons | 2 | 3 | 1 | 1 | 1 | 0 | 2 | 1 |
| Mensajes | Addons | 2 | 6 | 1 | 1 | 1 | 0 | 3 | 3 |
| Demo | Addons | 1 | 2 | 1 | 1 | 1 | 0 | 0 | 0 |
| Configuracion | Core | 0 | 29 | 1 | 4 | 24 | 0 | 75 | 60 |
| Clientes | Core | 0 | 0 | 0 | 0 | 0 | 3 | 0 | 0 |
| CRM | Core | 0 | 0 | 0 | 0 | 0 | 2 | 0 | 0 |
| VoIP | Addons | 0 | 7 | 1 | 1 | 1 | 0 | 0 | 0 |
| Documentos | Core | 0 | 0 | 0 | 0 | 2 | 0 | 6 | 7 |
| Localizacion | Core | 0 | 0 | 0 | 5 | 1 | 0 | 3 | 1 |
| PortalPago | Addons | 0 | 4 | 1 | 1 | 1 | 0 | 0 | 0 |
| Domiciliacion | Addons | 0 | 4 | 0 | 0 | 1 | 0 | 0 | 0 |
| ModuleManager | Core | 0 | 0 | 0 | 1 | 1 | 0 | 3 | 4 |
| Usuarios | Core | 0 | 0 | 0 | 2 | 0 | 0 | 0 | 0 |
| Auditoria · Auth · Dashboard · Documentacion · Layout · PortalCliente · Release | Core | 0 | 0 | 0 | 0–1 | 0 | 0 | 0 | 0 |

## Nota sobre la spec

`circuito-fase2a.md` **no está en el repo** (`find` sobre `/var/www` y `/home/meganet` no lo
encuentra, y no hay `docs/` con ese nombre). Lo de arriba se midió contra el esquema real de los
`module.json`, no contra esa spec. Si el documento existe fuera del repo, conviene commitearlo antes
de escribir el detector — o será la próxima regla que vive en un solo lugar.

`AuditorService::medirContraSpec()` sigue devolviendo `[]` y su docblock describe un contrato
**distinto** al que ahora se plantea: dice *"un RoadmapItem con marcador `[SPEC]` en el título"*.
Mover la fuente de verdad a `module.json` es un cambio de contrato deliberado y hay que reescribir
ese docblock en el mismo commit que lo implemente.
