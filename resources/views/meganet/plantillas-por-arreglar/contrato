<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title></title>
<style>
td, th, tr {
border: 1px solid black;
word-wrap: break-word;
}

table {
border-collapse: collapse;
table-layout: fixed;
font-family: Arial, Verdana, sans-serif;
font-size: 10px;
}

.fs {
font-family: Arial, Verdana, sans-serif;
font-size: 10px;
}

body {
margin: 10px;
padding: 10px;
}
</style>
</head>
<body>
<div style="text-align: center" class="fs"><h4>CONTRATO DE SUSCRIPCION No. {{ customer.id }} </h4>
enterado desde 
</div>
<br>
<div>
<table style="width:100%">
<tr>
<td bgcolor="#e0e0e0" style="width: 15%"><b>Nombre de la Empresa:</b></td>
<td colspan="3">MEGANET TELECOMUNICACIONES S.A. DE C.V.</td>
</tr>
<tr>
<td bgcolor="#e0e0e0"><b>VAT id:</b></td>
<td colspan="3"></td>
</tr>
<tr>
<td bgcolor="#e0e0e0"><b>Address</b></td>
<td colspan="3">Av Hda La Purisima Mz 3 Lt 54 Casa A, Nextlalpan Edo Mex, C.P 55796</td>
</tr>
<tr>
<td bgcolor="#e0e0e0"><b>Telefono:</b></td>
<td colspan="3">(55)42-10-62-77</td>
</tr>
<tr>
<td bgcolor="#e0e0e0"><b>Email</b></td>
<td colspan="3">administrador@meganetweb.com, soporte@meganetweb.com</td>
</tr>
<tr>
<td bgcolor="#e0e0e0"><b>Fecha</b></td>
<td style="width: 30%">{{ "now"|date("m/d/Y") }}</td>
<td bgcolor="#e0e0e0" style="width: 10%"><b>Lugar</b></td>
<td>Edo Mex Nextlalpan</td>
</tr>
<tr>
<td bgcolor="#e0e0e0"><b>Firma</b><br>Colaborador MEGANET TELECOMUNICACIONES S.A. DE C.V.</td>
<td colspan="3"></td>
</tr>
</table>
</div>
<br>
<div style="text-align: center">(<b>"PROVEEDOR DE SERVICIOS"</b>) y
</div>
<br>
<div>
<table style="width:100%">
<tr>
<td bgcolor="#e0e0e0" style="width: 15%"><b>Nombre y Apellidos /<br>Nombre de La Empresa</b></td>
<td colspan="3">{{ customer.name }}</td>
</tr>
<tr>
<td bgcolor="#e0e0e0"><b>Direccion de la conexion</b></td>
<td colspan="3"> {{ loader.customer.street_1 }}{% if loader.customer.additionalAttributes.street_2 is not empty %}, {{ loader.customer.additionalAttributes.street_2 }}{% endif %}, {{ customer.city}}{% if loader.customer.zip_code is not empty %}, {{ loader.customer.zip_code }}{% endif %}</td>
</tr>

<tr>
<td bgcolor="#e0e0e0"><b>Email</b><br>Las facturas y recibos serán enviados a esta dirección.</td>
<td colspan="3">{{ loader.customer.email }}</td>
</tr>
<tr>
<td bgcolor="#e0e0e0"><b>Telefono</b></td>
<td colspan="3">{{ loader.customer.phone }}</td>
</tr>
{% if loader.customer.additionalAttributes.contact_2 is not empty %}
<tr>
<td bgcolor="#e0e0e0"><b>Persona que autoriza:</b></td>
<td colspan="3">{{ loader.customer.additionalAttributes.contact_2 }}</td>
</tr>
{% endif %}
{% if loader.customer.additionalAttributes.phone_2 is not empty %}
<tr>
<td bgcolor="#e0e0e0"><b>Telefono de la persona que autoriza</b></td>
<td colspan="3">{{ loader.customer.additionalAttributes.phone_2 }}</td>
</tr>
{% endif %}
{% if loader.customer.additionalAttributes.contact_3 is not empty %}
<tr>
<td bgcolor="#e0e0e0"><b>Segunda persona autorizada:</b></td>
<td colspan="3">{{ loader.customer.additionalAttributes.contact_3 }}</td>
</tr>
{% endif %}
{% if loader.customer.additionalAttributes.phone_3 is not empty %}
<tr>
<td bgcolor="#e0e0e0"><b>Telefono de la segunda persona autorizada:</b></td>
<td colspan="3">{{ loader.customer.additionalAttributes.phone_3 }}</td>
</tr>
{% endif %}
<tr>
<td bgcolor="#e0e0e0"><b>Fecha</b></td>
<td style="width: 30%">{{ "now"|date("m/d/Y") }}</td>
<td bgcolor="#e0e0e0" style="width: 10%"><b>Lugar</b></td>
<td>Mexico</td>
</tr>
<tr>
<td bgcolor="#e0e0e0"><b>Contraseña Wifi</b></td>
<td colspan="3">{{ loader.customer.additionalAttributes.pass_wifi }}</td>
<td> {{loader.customer.pass_wifi}}</td>
</tr>
<tr>
<td bgcolor="#e0e0e0"><b>Numero de Referencia</b></td>
<td style="width: 30%">{{loader.customer.login}}</td>
<td bgcolor="#e0e0e0" style="width: 10%"><b>Contraseña Usuarios Meganet</b></td>
<td> {{loader.customer.password}}</td>
</tr>
<tr>
<td bgcolor="#e0e0e0"><b>Firma</b><br>Nombre Cliente.</td>
<td colspan="3"></td>
</tr>
</table>
</div>
<br>
<div style="text-align: center" class="fs">(<b>"SUSCRIPTOR"</b>)<br>
(Las <b>"Partes"</b>. A referirse a <b>"Parte"</b> Será una referencia a uno de ellos según lo determinado.
por el contexto.)<br>Para el período y los servicios seleccionados a continuación
</div>



<div class="fs">
<h4><b><br>
{% set active_services = loader.getServicesByTypeAndStatus('internet', 'active') %}
{% for active in active_services %}
Servicio: {{ active.type }}<br>
Tarifa: {{ active.description }}<br>
Costo Mensual: {{ active.unit_price }}<br>
Fecha de Inicio del Servicio: {{ active.start_date }}<br>
{% endfor %}
{% set active_voice = loader.getServicesByTypeAndStatus('voice', 'active') %}
{% for voice in active_voice %}
{% if voice is not empty %} 
Servicio: {{ voice.type }}<br>
Tarifa: {{ voice.description }}<br>
Costo Mensual: {{ voice.unit_price }}<br>
Fecha de Inicio del Servicio: {{ voice.start_date }}<br>
{% endif %}
{% endfor %}
Meganet Agradece su Preferencia, Estamos comprometidos con brindar un servicio de Calidad y agradeceremos cualquier observacion o comentario acerca del servicio {{ loader.customer.additionalAttributes.contract_term }}
</h4></b>
</div>
<div style="position: fixed; bottom: 0; width: 100%;" class="fs">
<div style="text-align: right"></div>
<hr style="width: 100%"/>
<div>Meganet Telecomunicaciones S.A. de C.V. Banda Ancha </div>
<div style="text-align: right">Pagina 1 de 8</div>
</div>
<div style="page-break-before: always;"></div>

<div style="text-align: justify"><h4>TERMINOS Y CONDICIONES DE LOS SERVICIOS DE MEGANET TELECOMUNICACIONES </h4>
Este Acuerdo de Suscriptor ("Acuerdo") se realiza entre el Suscriptor y MEGANET TELECOMUNICACIONES Limitado. ("MEGANET TELECOMUNICACIONES"), para la provisión y el uso del acceso a Internet "MEGANET TELECOMUNICACIONES" (el "Servicio").
Ahora, por lo tanto, teniendo en cuenta las promesas mutuas y los convenios contenidos en este documento, la adecuación de los cuales se reconoce en este documento, y tiene la intención de estar legalmente vinculado, el Suscriptor y MEGANET TELECOMUNICACIONES acuerdan lo siguiente:
<h4> 1. ACUERDO. </h4> El suscriptor acuerda estar obligado por este Acuerdo y utilizar el Servicio de conformidad con los términos de este Acuerdo y con la Política de Uso Aceptable de MEGANET TELECOMUNICACIONES y cualquier modificación que se realice en el mismo cada cierto tiempo.
<h4> 2. EL SUSCRIPTOR. </h4> El Suscriptor tiene al menos 18 años de edad, tiene capacidad legal para celebrar contratos y es responsable de la cuenta de este Suscriptor. El Suscriptor pagará todas las tarifas, impuestos, cargos y otros gastos incurridos en relación con la cuenta.
<h4> 3. AGRADECIMIENTOS DE SUSCRIPCIÓN AL SERVICIO. </h4>
(a) El Servicio consiste en una conexión a internet por Fibra Optica o Inalambrico. Mientras MEGANET TELECOMUNICACIONES realizará todos los esfuerzos comerciales razonables para brindar el servicio indicado, el Suscriptor reconoce que la velocidad del servicio puede variar según la distancia, el tráfico de Internet y otros factores que están fuera del control de MEGANET TELECOMUNICACIONES. El Servicio puede contener material que no es adecuado para menores de edad y el Suscriptor reconoce que MEGANET TELECOMUNICACIONES no filtra el contenido y no puede hacerlo.
<br>
(b) El Suscriptor reconoce que para proporcionar el Servicio, MEGANET TELECOMUNICACIONES ha contratado a los operadores de comunicaciones y redes para el acceso a Internet. El Suscriptor también reconoce que MEGANET TELECOMUNICACIONES solo proporcionará un Servicio continuo e ininterrumpido al Suscriptor de conformidad con este Acuerdo en la medida en que MEGANET TELECOMUNICACIONES reciba dicho servicio de las comunicaciones vinculadas y los operadores de red.
<br>
(c) El Suscriptor reconoce y acepta que de vez en cuando se le puede solicitar a MEGANET TELECOMUNICACIONES que suspenda temporalmente el Servicio para que el suscriptor verifique el cumplimiento de las licencias, autorizaciones aplicables y los parámetros técnicos y operativos de la red. En tales circunstancias, MEGANET TELECOMUNICACIONES utilizará todos los esfuerzos razonables para minimizar la interrupción del Servicio, incluidos los esfuerzos razonables para que dicha suspensión se realice fuera del horario comercial normal.
<br>
(d) El Suscriptor acepta que MEGANET TELECOMUNICACIONES puede cambiar o retirar cualquier elemento del Servicio de vez en cuando y hará todos los esfuerzos razonables para notificar al Suscriptor de cualquier cambio necesario en los Servicios.
<br>
(e) El Suscriptor reconoce que el Servicio es un "siempre abierto" La conexión a Internet mientras el equipo está encendido y que es la ÚNICA RESPONSABILIDAD del Suscriptor para instalar, configurar y mantener las medidas de seguridad adecuadas para proteger la computadora y el equipo del Suscriptor del acceso no autorizado o malicioso desde Internet. Cualquier consejo o equipo proporcionado por MEGANET TELECOMUNICACIONES se proporciona "tal cual" y MEGANET TELECOMUNICACIONES no asume ninguna responsabilidad u obligación por la seguridad de los sistemas del Suscriptor.
<div style="position: fixed; bottom: 0; width: 100%;" class="fs">
<div style="text-align: right"></div>
<hr style="width: 100%"/>
<div>MEGANET TELECOMUNICACIONES CONTRATO DE SERVICIO DE BANDA ANCHA</div>
<div style="text-align: right">Page 2 de 8</div>
</div>
<div style="page-break-before: always;"></div>
<h4>4. EQUIPO.</h4>
(a) Desde la activación del servicio, MEGANET TELECOMUNICACIONES  prestará cierto módem ONT y equipo asociado, en lo sucesivo denominado "Equipo", al Suscriptor para acceder al servicio. Este Equipo en todo momento sigue siendo propiedad exclusiva de BEST ISP y el Suscriptor acepta proporcionar acceso y permiso de MEGANET TELECOMUNICACIONES  para recuperar dicho equipo bajo demanda sin demora, obstrucción o interferencia.
<br>
(b) El Suscriptor acepta utilizar el Equipo de acuerdo con las instrucciones de MEGANET TELECOMUNICACIONES  y restringir el acceso al Equipo solo a los representantes y agentes autorizados por MEGANET TELECOMUNICACIONES. El Suscriptor se compromete a tomar medidas razonables para proteger el Equipo contra daños, pérdidas o robos.
<br>
(c) El Suscriptor se compromete a notificar a MEGANET TELECOMUNICACIONES tan pronto como sea razonablemente posible una vez que tenga conocimiento de cualquier daño al equipo o defecto en la operación del equipo por teléfono o correo electrónico de MEGANET TELECOMUNICACIONES a los números o direcciones publicadas periódicamente. , o inving9378@hotmail.com
<br>
(d) En la terminación o cancelación del Contrato de Servicio por cualquier motivo, es responsabilidad del Suscriptor devolver por correo certificado en buenas condiciones y adecuadamente empaquetado, el Módem ont y cualquier otro equipo relacionado proporcionado por MEGANET TELECOMUNICACIONES. Si no devuelve el equipo dentro de los 14 días posteriores a la fecha de finalización / cancelación del servicio, se generará un cargo automático por el equipo de 99 USD, incluido el IVA, que se deducirá de la cuenta de los Suscriptores y el Suscriptor acuerda al mismo.
<br>
<h4> 5. PLAZO. </h4>
(a) Este Acuerdo es por un período inicial de 6, 12 o 18 meses, según lo definen las reglas de su plan de precios, y se renovará automáticamente para el período del mes siguiente, hasta que se cancele de acuerdo con este Acuerdo. Después del Plazo inicial, el Suscriptor puede rescindir este Acuerdo con treinta (30) días de aviso por escrito al MEGANET TELECOMUNICACIONES.
<br>
(b) En caso de que el Suscriptor termine este acuerdo por cualquier motivo durante el término inicial, se cobrará de inmediato un cargo por cancelación equivalente al resto del término del contrato, y el Suscriptor reconoce y acepta pagar dicho cargo y devolver el equipo proporcionado.
<br>
(c) El Suscriptor puede actualizar el servicio en cualquier momento a un servicio superior y acepta que se aplique un nuevo contrato a partir de la fecha en que se aplique la actualización.
<br>
(d) MEGANET TELECOMUNICACIONES puede, a su entera discreción, rescindir este Acuerdo en cualquier momento. En el caso de que MEGANET TELECOMUNICACIONES finalice este Acuerdo por razones distintas al incumplimiento de este Acuerdo por parte del Suscriptor, MEGANET TELECOMUNICACIONES procurará en la medida en que sea razonablemente posible proporcionar un aviso de 30 días a El Suscriptor. El Suscriptor es responsable en virtud de este Acuerdo de todos los aranceles y cargos hasta que el Acuerdo haya finalizado. EL SUSCRIPTOR ENTIENDE QUE MEGANET TELECOMUNICACIONES RECIBE A MENOS QUE LA NOTIFICACIÓN ESCRITA DESPUÉS DEL TÉRMINO INICIAL, EL SERVICIO CONTINUARÁ Y EL SUSCRIPTOR CONTINUARÁ PARA SER RESPONSABLE DEL PAGO DE LAS TARIFAS DE SERVICIO APLICABLES.
<br>
Enviar comentarios
Historia
Salvado
Comunidad
<div style="position: fixed; bottom: 0; width: 100%;" class="fs">
<div style="text-align: right"></div>
<hr style="width: 100%"/>
<div>MEGANET TELECOMUNICACIONES CONTRATO DE SERVICIO DE BANDA ANCHA</div>
<div style="text-align: right">Page 3 of 8</div>
</div>
<div style="page-break-before: always;"></div>
<h4> 6. TERMINACIÓN. </h4> <br>
(a) Si el Suscriptor no está satisfecho con el Servicio o con los términos, condiciones, reglas, políticas, pautas o prácticas relacionadas, y si estos problemas no pueden resolverse a través del procedimiento de Quejas del Cliente de MEGANET TELECOMUNICACIONES, el El único remedio es interrumpir el uso del Servicio, cancelar la cuenta y pagar las tarifas de cancelación que correspondan. Para cancelar el Servicio, el Suscriptor debe enviar una solicitud por escrito de la cancelación por correo electrónico a MEGANET TELECOMUNICACIONES y debe ser firmado por un representante autorizado de El Suscriptor para que llegue al menos 5 días hábiles antes de que finalice el plazo de facturación actual. En caso de que el Suscriptor termine este acuerdo durante el término inicial por cualquier motivo que no sea un incumplimiento por parte de MEGANET TELECOMUNICACIONES para proporcionar el servicio de acceso a Internet por un período de más de 5 días, se cobrará una tarifa de cancelación equivalente al resto del término del contrato, y El Suscriptor reconoce y acepta pagar dicha tarifa.
<br>
(b) Tras la cancelación o la rescisión de este Acuerdo, los servicios de correo electrónico y alojamiento relacionados se cancelarán y todos los archivos del Suscriptor almacenados en los servidores MEGANET TELECOMUNICACIONES podrán eliminarse. MEGANET TELECOMUNICACIONES puede rescindir este Acuerdo, su contraseña, su cuenta o su uso de los Servicios por cualquier motivo, incluyendo, sin limitación, si MEGANET TELECOMUNICACIONES, a su entera discreción, cree que ha infringido los Acuerdos o si el Suscriptor no lo hace. pagar cualquier cargo a su vencimiento.
<br>
(c) Las Secciones 11, 20, 21 y 22 de este Acuerdo sobrevivirán a la terminación de este Acuerdo.
<br>
(d) MEGANET TELECOMUNICACIONES puede rescindir este acuerdo inmediatamente si el suscriptor está sujeto a quiebra, insolvencia, examen, administración judicial, liquidación o cualquier otro procedimiento similar, o si la opinión exclusiva de MEGANET TELECOMUNICACIONES no puede pagar las tarifas debidas a MEGANET TELECOMUNICACIONES .
<br>
<h4> 7. TARIFAS Y PAGO </h4> <br>
(a) El Suscriptor pagará una tarifa mensual por el servicio y todas las demás tarifas, cargos, impuestos y otros montos aplicables por el Servicio a las tarifas vigentes para el período de facturación actual. MEGANET TELECOMUNICACIONES puede aumentar o disminuir la tarifa de servicio mensual. MEGANET TELECOMUNICACIONES hará todos los esfuerzos razonables para proporcionar al Suscriptor con treinta (30) días de anticipación o más. Si dichos cambios a la tarifa del servicio mensual básico son para el detrimento de los Suscriptores (por ejemplo, un aumento de precio), el Suscriptor puede rescindir este acuerdo. al dar un aviso por escrito con treinta (30) días, y el Suscriptor seguirá siendo responsable solo de cualquier saldo en la cuenta.
<br>
(b) El pago se debe en su totalidad con tarjeta de crédito o débito directo al comienzo de cada mes de facturación. Todos los cargos se consideran válidos a menos que se cuestionen por escrito dentro de los treinta (30) días de la fecha de facturación. No se realizarán ajustes por cargos que tengan más de 30 días de antigüedad. Si algún pago tiene más de 7 días de retraso o lo devuelve el banco sin pagar, el Servicio puede suspenderse con efecto inmediato y permanecer suspendido hasta que las cantidades adeudadas se paguen por completo. Al Suscriptor no se le exime de la obligación de pagar la tarifa de servicio mensual mientras se suspende una cuenta. MEGANET TELECOMUNICACIONES puede, a su entera discreción, rescindir el Servicio y este Acuerdo para cualquier cuenta que tenga 14 días de vencimiento o más. Es posible que se requiera un cargo o depósito de reactivación antes de que se reactive el Servicio después de la suspensión o terminación. Los saldos de las cuentas de crédito no devengarán intereses. El Suscriptor se compromete a pagar los costos razonables de cualquier agencia de cobro, abogado o tribunal utilizado por MEGANET TELECOMUNICACIONES para cobrar los montos vencidos o para hacer cumplir este Acuerdo. Los cheques devueltos o los débitos directos incurrirán en una tarifa de administración de $249.
<br>
(c) Un cargo de $29 incluido el IVA se aplica a todas las facturas para clientes que no son de Débito Directo. (d) Cuando un paquete se baja en el contrato, se aplica una tarifa de $50 que incluye la reducción del IVA.
<br>
<br>
(c) Un cargo de $199 incluido el IVA se aplica cuando el cliente decide cambiar el paquete obtenido mediante una promocion por tanto el cliente pierde los beneficios de la promocion con la que inicio el contrato
<br>
<estilo div = "posición: fija; parte inferior: 0; ancho: 100%;" class = "fs">
<div style = "text-align: right"> </div>
<hr style = "width: 100%" />
<div> ACUERDO DE SERVICIO DE BANDA ANCHA DE MEGANET TELECOMUNICACIONES </div>
<div style = "text-align: right"> Página 4 de 8 </div>
</div>
<div style = "page-break-before: always;"> </div>
<h4> 8. CUENTA DE SUSCRIPTOR. </h4> <br>
<br> (a) El Suscriptor recibirá un nombre de usuario, contraseña, referencia de cuenta y varios otros detalles de la cuenta. El Suscriptor es el único responsable del uso del Servicio y de garantizar que su información se mantenga confidencial. El Suscriptor debe notificar MEGANET TELECOMUNICACIONES inmediatamente después de descubrir cualquier uso no autorizado de su cuenta.
<br> (b) El (A) Suscriptor reconoce que los nombres de usuario, las contraseñas y las direcciones IP pueden cambiar o cambiarse de vez en cuando, y específicamente que las direcciones IP fijas no están garantizadas, excepto en el caso de los servicios personalizados, donde esto incluye específicamente parte del contrato de servicios.
<br>
<h4> 9. POLÍTICA DE ACCESO JUSTO. </h4> <br>
Para garantizar un acceso igualitario a Internet para todos los suscriptores, MEGANET TELECOMUNICACIONES aplica una política de acceso justo. El acceso justo establece un equilibrio equitativo en el acceso a Internet a través de servicios de Internet de alta velocidad para todos
<div style="page-break-before: always;"></div>
<h4>11. INSTALLATION.</h4>
(a)Â (a) La instalación, el uso, la inspección, el mantenimiento, la reparación y la extracción del equipo pueden ocasionar una interrupción del servicio o un daño potencial a su computadora. El Suscriptor es el único responsable de hacer una copia de seguridad de todos los archivos y datos informáticos existentes. MEGANET TELECOMUNICACIONES y sus empleados, agentes, contratistas y representantes no tendrán responsabilidad alguna por cualquier daño o pérdida o destrucción de cualquiera de sus hardware, software, archivos, datos o periféricos.
<br>
(b) MEGANET TELECOMUNICACIONES se esforzará por proporcionar el Servicio a todos los solicitantes elegibles, sujeto a la viabilidad técnica y comercial. MEGANET TELECOMUNICACIONES puede, a su entera discreción, determinar que no puede o no prestará servicios a un sitio o suscriptor en particular, y se reserva el derecho de cancelar el proceso de instalación y reembolsar cualquier dinero que haya pagado el Suscriptor. MEGANET TELECOMUNICACIONES le notificará su intención de cancelar tan pronto como sea razonablemente posible. Puede tomar hasta 90 días o más para determinar si MEGANET TELECOMUNICACIONES puede proporcionar servicio en ciertas ubicaciones. MEGANET TELECOMUNICACIONES no tendrá responsabilidad alguna por reclamos que surjan de su falla o rechazo para completar la instalación o proporcionar el Servicio.
<h4> 12. DERECHOS DE AUTOR Y LICENCIAS. </h4>
El contenido del Servicio está protegido por la ley de derechos de autor aplicable. Queda prohibida cualquier copia, modificación, distribución, publicación u otro uso por parte del Suscriptor, o por parte de cualquier usuario de la cuenta del Suscriptor, de dicho contenido, excepto en los casos expresamente permitidos por el titular de los derechos de autor aplicables.
<h4> 13. SIN APOYO. </h4> <br>
MEGANET TELECOMUNICACIONES no respalda ni de ninguna manera garantiza la exactitud o integridad de ningún contenido disponible a través del Servicio. MEGANET TELECOMUNICACIONES no recomienda que EL SUSCRIPTOR confíe en dicho contenido sin una verificación adecuada.
<h4> 14. CONDUCTA DEL SUSCRIPTOR. </h4>
El Suscriptor cumplirá con todas las leyes, reglas, regulaciones y obligaciones legales relacionadas con el Servicio y con todas las políticas y procedimientos de uso aceptable establecidos de vez en cuando por MEGANET TELECOMUNICACIONES. El Suscriptor no utilizará el Servicio para llevar a cabo ninguna actividad comercial o actividad o para solicitar el desempeño de cualquier actividad que esté prohibida por cualquier ley, norma, regulación u obligación legal. El Suscriptor no debe interceptar el correo electrónico de manera no autorizada ni participar en "spamming" o cualquier conducta similar.
<h4> 15. ACCESO DE TERCEROS </h4>
(a) El Suscriptor no debe revender, compartir, arrendar, contratar o permitir el acceso al Servicio a ningún tercero, incluyendo, entre otros, la conexión de un tercero al Servicio mediante el uso de una conexión directa por cable, la red Conexión, redes inalámbricas, o cualquier otro medio.
<br>
(b) MEGANET TELECOMUNICACIONES se reserva el derecho de suspender el Servicio en espera de una investigación en la que sospeche razonablemente que la cláusula anterior es violada por el Suscriptor y se reserva el derecho de terminar con el efecto inmediato del Servicio y de este Acuerdo donde se haya producido dicha infracción.
<div style="position: fixed; bottom: 0; width: 100%;" class="fs">
<div style="text-align: right"></div>
<hr style="width: 100%"/>
<div>ACUERDO DE SERVICIO DE BANDA ANCHA DE MEGANET TELECOMUNICACIONES</div>
<div style="text-align: right">Page 6 of 8</div>
</div>
<div style="page-break-before: always;"></div>
<h4>16. SERVICIO MONITOREADO.</h4><br>
MEGANET TELECOMUNICACIONES no tiene la obligación de monitorear el Servicio, pero puede hacerlo y divulgar información sobre el uso de los Servicios por cualquier motivo si MEGANET TELECOMUNICACIONES, a su entera discreción, cree que es razonable hacerlo, incluso para: cumplir con las leyes, regulaciones, o solicitudes gubernamentales o legales; operar el Servicio correctamente; O protegerse a sí mismo y sus suscriptores. MEGANET TELECOMUNICACIONES puede eliminar inmediatamente su material o información de los servidores de MEGANET TELECOMUNICACIONES, en su totalidad o en parte, que MEGANET TELECOMUNICACIONES, a su entera y absoluta discreción, determine infringir los derechos de propiedad de otros o violar el Uso Aceptable de MEGANET TELECOMUNICACIONES. Política.
<br>
<h4> 17. EQUIPO DE SUSCRIPTOR. </h4> <br>
El Suscriptor mantendrá y operará el equipo de terminal y los dispositivos de comunicación adecuados y totalmente compatibles necesarios para acceder al servicio. MEGANET TELECOMUNICACIONES no hace ninguna representación o garantía, ya sea expresa o implícita, con respecto a dicho equipo del Suscriptor.
<br>
<h4> 18. RENUNCIA DE GARANTÍAS. </h4> <br>
El acceso al servicio no está garantizado. El servicio se distribuye en un "tal como es" y "como disponible" base sin garantías de ningún tipo, ya sea expresa o implícita, incluidas, entre otras, garantías de título o garantías implícitas de comercialización o adecuación para un propósito particular o de otro tipo.
<br>
<h4> 19. LIMITACIÓN DE RESPONSABILIDAD. </h4> <br>
Ni MEGANET TELECOMUNICACIONES ni ninguno de sus proveedores de información o contenido, proveedores de servicios, licenciantes, empleados o agentes serán responsables de ningún daño directo, indirecto, incidental, especial, punitivo o consecuente que surja del uso del Servicio por parte del Suscriptor. incapacidad para utilizar el servicio o cualquier incumplimiento de cualquier representación o garantía. En cualquier caso, ninguna responsabilidad de ese tipo excederá el monto total pagado en realidad por el Suscriptor por los servicios prestados en virtud de este acuerdo durante el período de seis meses anterior.
<br>
<h4> 20. INDEMNIZACIÓN. </h4> <br>
El Suscriptor asume todos los riesgos y responsabilidades por cualquier uso del Servicio. El Suscriptor se compromete a indemnizar a MEGANET TELECOMUNICACIONES por todas las reclamaciones, responsabilidades, daños, costos y gastos, incluidos, entre otros, los honorarios legales razonables que surjan de o estén relacionados con el uso del Servicio por parte del Suscriptor.
<br>
<div style="position: fixed; bottom: 0; width: 100%;" class="fs">
<div style="text-align: right"></div>
<hr style="width: 100%"/>
<div>MEGANET TELECOMUNICACIONES CONTRATO DE BANDA ANCHA</div>
<div style="text-align: right">Page 7 of 8</div>
</div>
<div style="page-break-before: always;"></div>
<h4>21. BENEFICIARIOS DE TERCEROS.</h4>
Las disposiciones de las Secciones 18, 19 y 20 son para el beneficio de MEGANET TELECOMUNICACIONES y sus respectivos contratistas, proveedores de información o contenido, proveedores de servicios, licenciadores, empleados y agentes; y cada uno tendrá el derecho de hacer valer y hacer cumplir tales disposiciones directamente en su propio nombre.
<h4> 22. SERVICIOS DE APOYO. </h4>
El Suscriptor dirigirá todas las consultas y problemas relacionados con el servicio a los puntos de contacto de Ventas y Soporte para Clientes de MEGANET TELECOMUNICACIONES, según se defina en su sitio web de vez en cuando o directamente por correo electrónico a office@bestisp.com.
<h4> 23. LEYES APLICABLES. </h4>
El presente Acuerdo se regirá por las leyes de la República de Mexicana. Cualquier causa de acción del Suscriptor, o de los usuarios de la cuenta del Suscriptor, con respecto al Servicio o este Acuerdo debe ser instituida dentro de los seis (6) meses posteriores a la fecha en que haya surgido o se haya excluido la reclamación o la causa de la acción. Se reconoce que este es un contrato de servicios y no un contrato para la venta de bienes.
<h4> 24. GENERAL. </h4>
(a) Este Acuerdo constituye el acuerdo completo entre las partes en relación con el tema que se encuentra a continuación, y reemplaza cualquiera y todas las declaraciones orales y / o escritas, discusiones, declaraciones y acuerdos realizados por cualquiera de las partes a la otra, y no pueden ser asignados. sin el consentimiento expreso por escrito de MEGANET TELECOMUNICACIONES. Ninguna modificación de este Acuerdo será vinculante para ninguna de las partes a menos que esté por escrito y firmada por ambas partes. La falta de parte de MEGANET TELECOMUNICACIONES para hacer cumplir cualquier disposición de este Acuerdo no se interpretará como una renuncia general o la renuncia al derecho para hacer cumplir dicha disposición. Si alguna disposición se considera inaplicable, la legalidad de validez y la exigibilidad de las disposiciones restantes no se verán afectadas de ninguna manera, y la intención de la disposición inejecutable se promulgará en la medida máxima exigible.
<br>
(b) Publicidad. MEGANET TELECOMUNICACIONES puede identificar a El Suscriptor como un usuario de los servicios de MEGANET TELECOMUNICACIONES en informes, anuncios y otros folletos promocionales o formas de publicación. El Suscriptor debe notificar a MEGANET TELECOMUNICACIONES por escrito si no desea ser identificado.
<br>
(c) Estos Términos y Condiciones pueden ser modificados por MEGANET TELECOMUNICACIONES de vez en cuando, la versión actual y aplicable siempre estará disponible en formato electrónico en la sección correspondiente del sitio web de MEGANET TELECOMUNICACIONES en www.megfanetweb.com MEGANET TELECOMUNICACIONES hará todos los intentos razonables por correo electrónico u otra comunicación, que incluye, entre otros, la prensa nacional, para informar al Suscriptor cuando se modifiquen los Términos y Condiciones del Servicio. Si alguna modificación causa un deterioro razonable en el nivel del Servicio, El suscriptor puede esperar razonablemente, su único remedio es terminar el servicio por escrito dentro de los 30 días de dicho cambio. En caso de que el Suscriptor continúe utilizando el Servicio 30 días después de la fecha de notificación de una modificación realizada a los Términos y Condiciones, se considerará que el Suscriptor ha aceptado los términos modificados.
<br>
(d) Encabezados por conveniencia. Todos los encabezados de los párrafos y subpárrafos se han insertado solo por conveniencia de referencia, y no se debe confiar en ellos para determinar el significado de los derechos y obligaciones de MEGANET TELECOMUNICACIONES o Suscriptor.
</div>
<div style="position: fixed; bottom: 0; width: 100%;" class="fs">
<div style="text-align: right"></div>
<hr style="width: 100%"/>
<div>MEGANET TELECOMUNICACIONES CONTRATO DE BANDA ANCHA</div>
<div style="text-align: right">Page 8 of 8</div>
</div>
</body>
</html>



