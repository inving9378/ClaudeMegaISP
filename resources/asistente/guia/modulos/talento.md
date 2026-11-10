<!-- Generada por `php artisan asistente:aprender talento` desde docs/modulos/talento.md, el menú y las pantallas de alta · huella 75057bd656e0fd55578090bebb6c0e65e28bc750 · 2026-11-10. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# Talento (personal de campo)
**En pocas palabras:** Aquí se administra al personal de campo (técnicos, vendedores, instaladores): sus órdenes de trabajo, asistencia, pagos, penalizaciones y formación.

## Para qué sirve
- Dar de alta a los colaboradores, asignarles órdenes de trabajo y validar su trabajo con evidencia (fotos, lectura de señal y firma del cliente).
- Calcular el pago semanal de cada colaborador, con sus bonos, préstamos, adelantos y ahorro, y dejar el registro de cada movimiento.
- Controlar penalizaciones (con evidencia y apelación), credenciales con aviso de vencimiento, cursos, exámenes y certificaciones.
- Registrar recorridos de planta externa con sus hallazgos.
- Cada colaborador ve y hace su trabajo del día desde su propia ficha, en el navegador o en la app "Talento Equipo".

## Pantallas
- [Talento › Colaboradores](/talento) — lista de colaboradores. Al entrar a Talento, cada colaborador cae en su propia ficha.
- [Talento › Órdenes de trabajo](/talento/ordenes) — órdenes en vista Lista o Agenda (mes, semana, día), con filtro por persona y "Solo atrasadas".
- [Talento › Recorridos](/talento/recorridos) — importar rutas grabadas con Geo Tracker y sus fotos, y registrar hallazgos.
- [Talento › Liquidaciones](/talento/liquidaciones) — cierre del pago semanal.
- [Talento › Academia](/talento/academia) — cursos, exámenes y certificaciones.
- [Talento › Credenciales](/talento/credenciales) — credenciales del personal y sus vencimientos.
- [Talento › Penalizaciones](/talento/penalizaciones) — penalizaciones con evidencia y apelación.
- [Talento › Reglas de desempeño](/talento/reglas-desempeno) — reglas de desempeño.
- [Talento › Documentos pendientes](/talento/documentos-pendientes) — documentos pendientes del personal.
- [Talento › Paquetes de documentos](/talento/expediente/paquetes) — paquetes de documentos por puesto; aquí el colaborador encuentra sus documentos para firmar.
- [Talento › Configuración de evidencias](/talento/config/evidencias) — qué evidencia se pide según el tipo de orden.
- [Talento › Escalafón](/talento/escalafon) — escalafón por puntaje.
- [Talento › Mapa en vivo](/talento/mapa-en-vivo) — ubicación del personal en el mapa.
- [Talento › Sitios de checada](/talento/sitios) — sitios donde se registra la asistencia.

## Cómo se hace
### Registrar entrada y salida
Se hace en la ficha del colaborador, en la pestaña Asistencia (con su ubicación). No se hace en "Mi día".

### Trabajar las órdenes del día (el colaborador)
1. Entra a Talento; cae en su ficha, que abre en la pestaña «Mi día». También hay un atajo «Mi día» en el menú de usuario.
2. En «Mi día» están sus órdenes de hoy. Ahí puede iniciarlas, capturar evidencia con la cámara, poner las firmas del técnico y del cliente, completar y aceptar.
3. Las órdenes atrasadas salen arriba y en rojo.

### Reprogramar una orden atrasada
1. Una orden atrasada es la que sigue pendiente o en curso aunque su día ya pasó.
2. La reprograma el técnico con su orden, o quien gestiona órdenes, o su supervisor directo.
3. Se elige un nuevo día (de hoy en adelante) y se escribe el motivo, que es obligatorio.
4. El cambio queda en «Cambios de día» del detalle de la orden.

### Importar un recorrido
Se hace en [Talento › Recorridos](/talento/recorridos). Se sube la ruta (GPX, KML o KMZ) y sus fotos. Cada foto crea un hallazgo, que se ubica con el GPS de la foto, con su hora sobre la ruta o a mano en el mapa. Para enviar un hallazgo se necesitan la foto, la categoría, la severidad (baja, media, alta o crítica), la descripción y la ubicación. Lo enviado ya no se puede cambiar.

### Revisar el material de un colaborador
Se ve en la pestaña Custodia de su ficha.

## Qué datos pide
### Alta de colaborador (dentro de Talento › Colaboradores)
- **Obligatorios:** User, Type, Status
- **Opcionales:** (ninguno)

### Nueva orden de trabajo (dentro de Talento › Órdenes de trabajo)
- **Obligatorios:** Colaborador, Type, Scheduled at
- **Opcionales:** (ninguno)

### Sitio de checada (dentro de Talento › Sitios de checada)
- **Obligatorios:** Nombre, Type, Latitude, Longitude, Radius m
- **Opcionales:** (ninguno)

### Alta de caja
- **Obligatorios:** Caja ref, Baseline power dbm
- **Opcionales:** (ninguno)

### Asignar trabajo
- **Obligatorios:** Colaborador, Fecha, Work order ids
- **Opcionales:** (ninguno)

### Importar recorrido (dentro de Talento › Recorridos)
- **Obligatorios:** Colaborador, Modo, Archivo
- **Opcionales:** (ninguno)

### Otra alta con nombre y estado
- **Obligatorios:** Nombre, Status
- **Opcionales:** (ninguno)

### Otra alta por colaborador y tipo
- **Obligatorios:** Colaborador, Type
- **Opcionales:** (ninguno)

### Vincular dispositivo móvil
- **Obligatorios:** Token
- **Opcionales:** (ninguno)

## Quién lo usa
RH y supervisión administran al personal, sus órdenes y sus pagos. Los técnicos, instaladores y vendedores usan su propia ficha. Ahí ven «Mi día», «Mi dinero», «Mis prospectos» (si venden) y «Mi equipo» (si tienen gente a su cargo). «Mi dinero» muestra la cuenta, el desglose, el fondo y los préstamos de la semana de pago.

## Con JARVIS
Por ahora JARVIS te explica cómo hacerlo y te da el enlace a la pantalla.
