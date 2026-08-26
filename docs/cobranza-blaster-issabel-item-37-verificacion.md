# Item #37 — "Debug Issabel call blaster: CSV upload OK pero download da No Data Found" (VERIFICACIÓN — premisa incorrecta)

## Premisa del item

> Meta: blaster de cobranza con SIP, transferencia a agente, y voz IA integrada. El upload de CSV
> funciona; el download falla.

Item creado 2026-05-31, sin módulo asignado originalmente (Thomas le asignó footprint VoIP
después, #419), sin archivos ni contexto técnico — el revisor lo escaló a Irving por tratarse
aparentemente de una integración con un PBX externo y lógica de cobranza (decisión de negocio).
Irving lo aprobó igualmente (log `aprobado_irving`, 2026-08-25).

## Hallazgo

Búsqueda exhaustiva en todo el repositorio (`app/`, `resources/`, `routes/`, `config/`, `docs/`,
`.md`, `.json`, `.vue`, `.js`, excluyendo `vendor`/`node_modules`):

1. **"Issabel" / "Elastix": cero coincidencias.** No hay ninguna referencia a ese producto PBX en
   todo el codebase. Issabel es un PBX de terceros (fork de Elastix, sobre Asterisk/FreePBX) con
   interfaz web propia — MegaISP no lo embebe ni lo administra: solo se conecta a **Asterisk**
   directo vía AMI (`App\Modules\Core\Voice\AmiClient`, `CobranzaBlaster\Services\AmiConnectionService`).
2. **"No Data Found": cero coincidencias.** No es un string que genere MegaISP; es el mensaje
   clásico de una UI basada en DataTables — coherente con que pertenezca a la interfaz nativa de
   Issabel (o un plugin de Call Center de esa PBX), no a este proyecto.
3. **CSV upload/download en el flujo de llamadas: no existe.** Revisado
   `app/Modules/Addons/CobranzaBlaster/` completo (`Controllers/CampanaController.php`,
   `Controllers/VoipConfiguracionController.php`, `routes.php`, `Services/CobranzaCampanaService.php`,
   vistas) — ningún endpoint de import/export/CSV. El flujo real es:
   `CampanaController@activar` → `CobranzaCampanaService::activarCampana()` carga los clientes
   morosos **directo de la base de datos** (`clients`/`invoices`, saldo vencido, servicio activo,
   con teléfono) — no hay paso de "subir/descargar una lista" en ningún punto.
   (Sí existen combos upload/download de CSV en otros módulos del sistema —
   EvaluadorEmpresarial, PortalPago, MegaFamilia, SmartImportExport, DevTools — pero ninguno tiene
   relación con llamadas, blaster o cobranza telefónica.)

## Lectura del item

La "meta" que describe el item — **blaster de cobranza con SIP, transferencia a agente y voz IA
integrada** — es exactamente lo que el módulo `CobranzaBlaster` (`app/Modules/Addons/CobranzaBlaster/`,
ver `docs/modulos/cobranzablaster.md`) ya implementa de punta a punta: origina llamadas por Asterisk/AMI,
lee un mensaje generado por OpenAI TTS, soporta transferencia a agente (DTMF) y registra el
resultado de cada intento. La diferencia es la arquitectura: en vez de administrar un archivo CSV
subido/descargado a mano (como hace el Call Blaster nativo de Issabel), `CobranzaBlaster` carga la
lista de morosos **automáticamente desde la BD** en cada activación de campaña — un diseño
estrictamente mejor (sin paso manual, sin archivo que se pueda desincronizar) que de paso hace que
el bug reportado ("download da No Data Found") **no tenga superficie donde ocurrir**: no hay
descarga de CSV en el flujo.

Todo indica que el item nació de un intento/exploración temprana con el Call Blaster nativo de
Issabel (fecha de creación muy cercana al arranque de `CobranzaBlaster`, finales de mayo), y que el
equipo optó por construir el robocaller propio sobre Asterisk en vez de operar el de Issabel. El
bug original, si existió, vivía en la interfaz de Issabel — un sistema externo sin código en este
repositorio, fuera del alcance de un cambio en MegaISP.

## Lo que NO se tocó (y por qué)

No hay cambio de código funcional: no existe una función de CSV en el flujo de llamadas de
`CobranzaBlaster` que debatir si "arreglar" — construir un import/export CSV nuevo sería agregar
una capa que el propio diseño actual (carga automática desde BD) hace innecesaria, y no fue lo que
pidió el item (pedía debuggear un download roto, no un requerimiento nuevo).

## Conclusión

Sin cambio de código funcional — la meta que persigue el item (blaster SIP + agente + voz IA) ya
está resuelta por `CobranzaBlaster`, con un diseño que no tiene el punto de falla que describía el
bug (no hay CSV en el camino). El sistema "Issabel" original no tiene código en este repositorio.
Cerrado como resuelto por otro camino.
