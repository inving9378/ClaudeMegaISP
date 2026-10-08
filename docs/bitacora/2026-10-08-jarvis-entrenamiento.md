## 2026-10-08 08:31 — JARVIS (robotsito): entrenamiento completo del sistema, pedir datos concretos y batería de pruebas

Rama: `feature/jarvis-entrenamiento`. Sin item de la Hoja de Ruta (rama aparte normal). Pedido: "darle
más entrenamiento para que solicite datos concretos, entrenamiento completo del sistema, pulirlo".
Decisiones del equipo: las guías las escribe la IA del sistema (no a mano) y se hacen los pasos 1 a 3.

**1. Conocimiento de todo el sistema — `php artisan asistente:aprender`**
- `Asistente\Entrenamiento\FuentesSistema` junta del propio sistema: `docs/modulos/*.md` (46), el menú
  lateral renderizado como lo ve un usuario con todos los permisos (121 pantallas con su sección), las
  194 altas (POST store/crear) con sus campos (`field_modules`) y el manual del módulo.
- Lo que pide cada alta se CALCULA (no lo adivina la IA): reglas del FormRequest (se ejecuta `rules()`)
  + lo que valida el `store()`; separa obligatorios / según el caso / opcionales / se llenan solos
  (usuario y contraseña generados, valores por defecto). 96 de 194 altas tienen reglas legibles.
- `GeneradorGuias` (IA del sistema, clave nueva `asistente.entrenamiento`, cae a la del chat) escribe
  39 guías `resources/asistente/guia/modulos/*.md` (excluye auth, devtools, layout, modulemanager,
  release, roadmap, security) + `_temas.md`, que va en cada mensaje. Revisión automática: solo enlaces a
  pantallas reales y sin dominio, reintento si se cuela lenguaje técnico, prohibido inventar pasos;
  las guías revisadas a mano (clientes, pagos-y-facturacion, tickets, red-y-equipos) se le dan como
  verdad. Huella por módulo: solo regenera lo que cambió.
- Primera versión: la IA inventó los pasos de "registrar un pago" (Finanzas › Pagos en vez de la ficha)
  y adivinó obligatorios (usuario/contraseña, "Dirección IP"). Se corrigió con las dos medidas de arriba.
- Memoria: presupuesto 16k → 32k tokens (el arranque ya ocupaba ~5k y compactaba casi en cada mensaje)
  y resultados de herramienta hasta 8,000 caracteres (las guías no se cortan).

**2. Pedir datos concretos**
- Instrucciones: sección "Cómo pides datos" (lista numerada con etiquetas y opciones, todo de una vez,
  solo lo obligatorio, nunca "¿algún dato?", identificar cliente por número/nombre completo/teléfono,
  periodo con opciones), apellidos completos con ejemplo, no mencionar nombres de herramientas, revisar
  herramientas antes de decir que no puede.
- Herramientas de alta: se llaman con lo que haya y responden la lista exacta de lo que falta; si la
  persona ya existe devuelven sus datos reales (`ya_existe`) para que no los invente.

**3. Pulir con pruebas — `php artisan asistente:evaluar`**
- `resources/asistente/pruebas.json`: 36 pruebas (listados, un cliente, pide datos, altas, cómo se
  hace, lo que no puede, seguridad, permisos con un técnico, memoria). Cada una es una plática real con
  la IA dentro de una transacción que se deshace; reporte en `storage/logs/asistente-evaluacion-*.json`.
- Vueltas: 26/35 → 34/36 → 35/36 → **36/36** (reporte `asistente-evaluacion-20261008-083605.json`; quedan 2 avisos menores: una lista de 22 renglones y un "más información" al negarse). Fallas reales encontradas y
  corregidas: respuestas de 33 renglones, "no puedo" cuando sí tenía la herramienta (regla demasiado
  estricta), al técnico sin permiso de cobranza le inventó "hoy no se cobró nada" mirando un cliente,
  no pedía el apellido materno, pedía datos que ya tienen valor, inventaba el estado de un cliente
  existente, se le escapaba el nombre de una herramienta.

**Dato real visto en las pruebas:** a las 07:45 el usuario que reportó la falla dio de alta a Romelio
Eugenio Suárez Espinosa (cliente 7696) con JARVIS, confirmado por él; bitácora "Vía JARVIS".
