/// Formateadores de fecha en español que NO dependen de
/// `intl/date_symbol_data_local.dart`. Se usan en toda la app para evitar
/// la `LocaleDataException` que se ha visto en builds release cuando el
/// símbolo del locale 'es' no se inicializa a tiempo.

const _mesesCortos = [
  'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
  'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic',
];

const _mesesLargos = [
  'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
  'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
];

const _dias = [
  'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo',
];

String _pad(int n) => n.toString().padLeft(2, '0');

/// "15 May 2026"
String fechaCorta(DateTime d) => '${d.day} ${_mesesCortos[d.month - 1]} ${d.year}';

/// "15 mayo 2026"
String fechaLarga(DateTime d) => '${d.day} ${_mesesLargos[d.month - 1]} ${d.year}';

/// "Jueves 15 May, 09:30"
String fechaConDia(DateTime d) =>
    '${_dias[d.weekday - 1]} ${d.day} ${_mesesCortos[d.month - 1]}, ${_pad(d.hour)}:${_pad(d.minute)}';

/// "15 May 09:30"
String fechaCortaConHora(DateTime d) =>
    '${d.day} ${_mesesCortos[d.month - 1]} ${_pad(d.hour)}:${_pad(d.minute)}';

/// "09:30"
String hora(DateTime d) => '${_pad(d.hour)}:${_pad(d.minute)}';
