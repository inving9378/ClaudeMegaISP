<!-- Generada por `php artisan asistente:aprender cobranzablaster` desde docs/modulos/cobranzablaster.md, el menú y las pantallas de alta · huella 3e9bd2a42a1466518ba1eb42ef33ba7c584367d7 · 2026-10-08. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# CobranzaBlaster
**En pocas palabras:** Sistema de llamadas automáticas para cobrar cuentas pendientes.

## Para qué sirve
- Automatiza las llamadas a clientes con saldo vencido.
- Reduce el tiempo del equipo de cobranza al eliminar el marcado manual.
- Crea campañas de cobranza segmentadas según criterios definidos.
- Registra el resultado de cada intento de llamada para seguimiento.

## Pantallas
- [Campañas](/cobranza/campanas) — Crear, listar y gestionar campañas de cobranza.
- [Config. VoIP](/cobranza/voip) — Configurar la conexión con el servidor Asterisk para realizar llamadas.

## Cómo se hace
### Crear campaña de cobranza
1. Ve a [Campañas](/cobranza/campanas) y selecciona la opción para crear una nueva campaña.

### Configurar conexión AMI
1. Ve a [Config. VoIP](/cobranza/voip) y completa los campos necesarios para la conexión con el servidor Asterisk.

## Qué datos pide
### Alta de campaña ([Campañas](/cobranza/campanas))
- **Obligatorios:** Nombre de campaña
- **Opcionales:** Ninguno

### Alta de configuración AMI ([Config. VoIP](/cobranza/voip))
- **Obligatorios:** Sip host, Sip username, Sip secret
- **Opcionales:** Ninguno

## Quién lo usa
Este módulo es utilizado principalmente por el personal del área de cobranza y administración que gestiona llamadas a clientes morosos.

## Con JARVIS
Por ahora JARVIS te explica cómo hacerlo y te da el enlace a la pantalla.
