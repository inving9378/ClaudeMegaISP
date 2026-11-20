# 2026-11-20 — VoIP: el teléfono web lleva un número disponible que no se parece al del aparato

## Pedido
Irving: el teléfono web y el teléfono físico son dos lugares completamente distintos y no pueden coincidir en
ninguna forma. Si el aparato es la 1080, el web es otro número, no necesariamente parecido: uno disponible. Desde el
web se puede llamar sin que aparezca en el aparato, desde el aparato sin que salga en el web, y desde el web se
puede timbrar al propio aparato.

## Lo que ya cumplía la V1.96 y lo que faltaba
- Desde la V1.96 son dos cuentas SIP distintas (otra contraseña, otro endpoint, `max_contacts` 1); el dialplan
  `_[1-9]XXX` → `Dial(PJSIP/${EXTEN},30)` timbra solo el número marcado, y el navegador solo recibe la cuenta
  `es_webrtc` de la persona (`MiTelefonoController`). Llamar entre los dos ya funcionaba como dos lugares.
- Lo que sí los «emparejaba» era el número: la separación conservaba la terminación (`web1003` → `2003`), y así
  parecía que la 2003 es «la de la 1003». En prod (19-nov), para hablarle a una persona se marcó su aparato
  (1003, sin nada conectado: «No route to destination») en vez de su teléfono web.

## Lo que se hizo
- `SegmentosTelefono::parecido()` (1003 ↔ 2003). `primerLibre()` se salta todo número web cuyo parecido exista;
  `disponible()` busca en el área web de la persona y, si está llena, en todo el 2000–2999. Se quitó `webEspejo()`.
- `SeparadorTelefonosWeb`: las gemelas viejas pasan al primer número disponible de su área (ya no a la misma
  terminación), y además todo teléfono web que se parece a un aparato que existe (como los dejó la V1.96) pasa a
  uno disponible, con `callerid` al número nuevo. `numero_anterior` conserva la gemela vieja (`web1003`) si la
  había, porque es la de casi todo el historial; las llamadas hechas con el número parecido en los días entre la
  V1.96 y esta versión salen sin nombre en el historial.
- `ExtensionController`: no deja dar de alta ni cambiar a un número que se parece a otro que existe, en los dos
  sentidos (web 2003 con la 1003; aparato 1050 con el web 2050), y al web le sugiere uno disponible. Un teléfono
  web sin número toma el primero disponible del área de la persona (`ReclamadorExtensionAutomatico::areaDe()`,
  si su rol no está en la lista, oficinas) y la respuesta trae el número.
- Pantalla de Extensiones: la ayuda lo explica, el número del teléfono web es opcional al crearlo («Automático:
  uno disponible») y el aviso dice qué número le tocó.

## Verificado en test (dev)
- Pruebas: `tests/Feature/VoIP/SegmentosTelefonoTest.php` (5: segmentos, gemelas, primero disponible sin parecerse,
  parecidos de la V1.96, formulario) y `tests/js/numeroMarcado.test.mjs`.
- Respaldo antes de aplicar (`voip_extensiones`, `voip_grupo_extension`, `voip_rangos_numeracion`, `ps_*`,
  `queue_members`) en el scratchpad de la sesión f643e010 (`v197/`).
- «Sincronizar con Asterisk» por la pantalla (cuenta de prueba): 2002 → 2006, 2003 → 2007, 2004 → 2008,
  2011 → 2009 (2001 y 2005 se saltan porque existen la 1001 y la 1005); `callerid` al número nuevo,
  `numero_anterior` sigue en `web100x`; la cola de atención pasó de `PJSIP/2003` a `PJSIP/2007`; 0 fallas; una segunda
  revisión ya no tiene nada que mover.
- Formulario (API de la pantalla): web 2001 → «La 1001 es un aparato… Disponible: 2010.»; aparato 1006 → «La 2006 es
  el teléfono web de …»; web sin número → 2010.
- De punta a punta (Playwright, cuenta de prueba): el navegador recibió la 2010 y se registró; marcar al aparato 1001
  no lo hizo timbrar; marcar la 2010 sí («Llamada entrante…»). Luego se borró por la API (fuera de MegaISP y de
  Asterisk).
- Pantalla (frontend de test recompilado, «Compiled successfully in 19.65m»): insignia «Teléfono web» en
  2006–2009; con tipo «Teléfono web» la ayuda explica que no se parece a ningún aparato, el número dice «Automático:
  uno disponible», y guardar 2001 muestra «La 1001 es un aparato… Disponible: 2010.» (sin guardar nada).
- Pruebas de VoIP completas (`tests/Feature/VoIP` + `tests/Unit/VoIP`): 64 pasan.

## En prod
Con la versión: «Instalar dependencias» de VoIP (paso «Sincronizar», que respalda antes) separa las gemelas que
queden o mueve los números parecidos que dejó la V1.96, y cada quien recarga la página (F5) para tomar su número.
El número web de cada persona se ve en VoIP → Extensiones (insignia «Teléfono web») y en el encabezado del teléfono
del navegador.
